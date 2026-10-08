<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * mcp_server.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\protocol;

use coding_exception;
use local_mcp\audit\logger;
use local_mcp\event\destructive_operation_executed;
use local_mcp\event\write_operation_executed;
use local_mcp\oauth\resource;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\security\capability_guard;
use local_mcp\security\confirmation_service;
use local_mcp\security\rate_limiter;
use local_mcp\security\scope;
use local_mcp\security\token_resolver;
use local_mcp\write\registry;
use Throwable;

/**
 * Class mcp_server.
 */
final class mcp_server {
    /**
     * Method __construct.
     *
     * @param string $side Parameter side.
     */
    public function __construct(private readonly string $side) {
        if (!in_array($side, ['read', 'write', 'server'], true)) {
            throw new coding_exception('Invalid MCP side.');
        }
    }

    /**
     * Method handle.
     *
     * @return never Return value.
     */
    public function handle(): never {
        try {
            // Stateless Streamable HTTP: clients can use POST without an SSE session.
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                header('Allow: POST');
                throw new api_exception('method_not_allowed', 405);
            }
            [$authheader, $rawtoken] = http::bearer();
            $identity = token_resolver::from_bearer($authheader, resource::endpoint($this->side));
            // Moodle APIs frequently rely on the current user rather than a userid parameter.
            // Authentication is performed by the bearer token in this cookie-free request.
            global $DB;
            $user = $DB->get_record('user', [
                'id' => $identity->userid, 'deleted' => 0, 'suspended' => 0,
            ]);
            if (!$user || empty($user->confirmed) || isguestuser($user)) {
                throw new api_exception('invalid_user', 401);
            }
            \core\session\manager::set_user($user);
            if ($this->side !== 'server') {
                $requiredscope = $this->side === 'read' ? scope::READ : scope::WRITE;
                if (!$identity->has_scope($requiredscope)) {
                    throw new api_exception('insufficient_scope', 403);
                }
                $limit = (int)get_config('local_mcp', $this->side === 'read' ? 'rateread' : 'ratewrite');
                rate_limiter::check('mcp_' . $this->side,
                    $identity->type . ':' . ($identity->tokenid ?? $identity->userid),
                    $limit ?: ($this->side === 'read' ? 120 : 30));
            }

            $request = http::request_json();
            $id = $request['id'] ?? null;
            $method = $request['method'] ?? '';
            $params = $request['params'] ?? [];

            if ($method === 'initialize') {
                http::json(['jsonrpc' => '2.0', 'id' => $id, 'result' => [
                    'protocolVersion' => '2025-06-18',
                    'capabilities' => ['tools' => (object)[]],
                    'serverInfo' => ['name' => 'Moodle MCP ' . strtoupper($this->side), 'version' => '1.1.0'],
                ]]);
            }
            if ($method === 'notifications/initialized') {
                http::accepted();
            }
            if ($method === 'tools/list') {
                http::json(['jsonrpc' => '2.0', 'id' => $id, 'result' => ['tools' => $this->tool_definitions($identity)]]);
            }
            if ($method === 'tools/call') {
                try {
                    $result = $this->call_tool(
                        (string)($params['name'] ?? ''),
                        (array)($params['arguments'] ?? []),
                        $params,
                        $identity,
                        $rawtoken
                    );
                    http::json(['jsonrpc' => '2.0', 'id' => $id, 'result' => [
                        'content' => [[
                            'type' => 'text',
                            'text' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ]],
                        'structuredContent' => $result,
                        'isError' => false,
                    ]]);
                } catch (api_exception $e) {
                    $error = [
                        'error' => $e->machinecode,
                        'message' => $e->getMessage(),
                        'details' => $e->details,
                    ];
                    http::json(['jsonrpc' => '2.0', 'id' => $id, 'result' => [
                        'content' => [[
                            'type' => 'text',
                            'text' => json_encode($error, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ]],
                        'structuredContent' => $error,
                        'isError' => true,
                    ]]);
                }
            }
            http::json([
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => ['code' => -32601, 'message' => 'Method not found'],
            ]);
        } catch (Throwable $e) {
            http::error($e, $this->side);
        }
    }

    /**
     * Method tool_definitions.
     *
     * @return array Return value.
     */
    private function tool_definitions(authenticated_identity $identity): array {
        $tools = $this->get_tools();
        $out = [];
        foreach ($tools as $tool) {
            $iswrite = $tool instanceof \local_mcp\write\tool_interface;
            $requiredscope = $iswrite ? scope::WRITE : scope::READ;
            if (!$identity->has_scope($requiredscope)) {
                continue;
            }
            $schema = $tool->get_input_schema();
            if ($iswrite && ($tool->requires_confirmation() || $tool->is_destructive())) {
                $schema['properties']['confirmation_token'] = [
                    'type' => 'string',
                    'description' => 'One-time token returned by the preceding preview. '
                        . 'Only send after the user explicitly approves the operation.',
                ];
            }
            if ($iswrite && $tool->supports_dry_run()) {
                $schema['properties']['dry_run'] = [
                    'type' => 'boolean',
                    'description' => 'Preview without changing Moodle data.',
                ];
            }
            $item = [
                'name' => $tool->get_name(),
                'title' => $tool->get_title(),
                'description' => $tool->get_description(),
                'inputSchema' => $schema,
                'securitySchemes' => [['type' => 'oauth2', 'scopes' => [$requiredscope]]],
                'annotations' => [
                    'readOnlyHint' => !$iswrite,
                    'destructiveHint' => $iswrite && $tool->is_destructive(),
                    'idempotentHint' => !$iswrite,
                    'openWorldHint' => false,
                ],
            ];
            $out[] = $item;
        }
        return $out;
    }

    /**
     * Look up tools only from the registry sides enabled by this endpoint.
     *
     * @return array
     */
    private function get_tools(): array {
        $read = $this->side !== 'write' ? \local_mcp\read\registry::get_tools() : [];
        $write = $this->side !== 'read' ? registry::get_tools() : [];
        foreach ($write as $name => $tool) {
            if (isset($read[$name])) {
                throw new coding_exception('Duplicate MCP tool: ' . $name);
            }
        }
        return array_merge($read, $write);
    }

    /**
     * Method call_tool.
     *
     * @param string $name Parameter name.
     * @param array $arguments Parameter arguments.
     * @param array $params Parameter params.
     * @param authenticated_identity $identity Parameter identity.
     * @param string $rawtoken Parameter rawtoken.
     * @return array Return value.
     */
    private function call_tool(string                                     $name, array $arguments, array $params,
                               authenticated_identity $identity, string $rawtoken): array {
        $tools = $this->get_tools();
        if (!isset($tools[$name])) {
            throw new api_exception('tool_not_found', 404);
        }
        $tool = $tools[$name];
        $iswrite = $tool instanceof \local_mcp\write\tool_interface;
        $requiredscope = $iswrite ? scope::WRITE : scope::READ;
        if (!$identity->has_scope($requiredscope)) {
            throw new api_exception('insufficient_scope', 403);
        }
        // The unified server still enforces the independent WRITE rate limit.
        if ($this->side === 'server') {
            $configkey = $iswrite ? 'ratewrite' : 'rateread';
            $limit = (int)get_config('local_mcp', $configkey);
            rate_limiter::check('mcp_' . ($iswrite ? 'write' : 'read'),
                $identity->type . ':' . ($identity->tokenid ?? $identity->userid),
                $limit ?: ($iswrite ? 30 : 120));
        }
        $confirmation = (string)($arguments['confirmation_token'] ?? ($params['confirmation_token'] ?? ''));
        $dryrun = !empty($arguments['dry_run']) || !empty($params['dry_run']);
        unset($arguments['confirmation_token'], $arguments['dry_run']);
        $context = $tool->resolve_context($arguments);
        capability_guard::check($tool->get_required_capability(), $context, $identity->userid);

        if (!$iswrite) {
            $started = microtime(true);
            $result = $tool->execute($arguments, $identity);
            logger::record('tool_called', $identity, $name, 'read', $context, [],
                false, false, false, 'ok', (int)round((microtime(true) - $started) * 1000));
            return $result;
        }

        if ($dryrun) {
            if (!$tool->supports_dry_run()) {
                throw new api_exception('dry_run_not_supported', 400);
            }
            $preview = $tool->preview($arguments, $identity);
            logger::record('tool_called', $identity, $name, 'write', $context, [],
                $tool->is_destructive(), true, false, 'preview');
            return ['dry_run' => true, 'preview' => $preview];
        }

        if ($tool->requires_confirmation() || $tool->is_destructive()) {
            if ($confirmation === '') {
                $preview = $tool->preview($arguments, $identity);
                $token = confirmation_service::issue(
                    $identity, $rawtoken, $name, $arguments, $context);
                throw new api_exception('confirmation_required', 409, '', [
                    'preview' => $preview, 'confirmation_token' => $token,
                ]);
            }
            confirmation_service::consume(
                $confirmation, $identity, $rawtoken, $name, $arguments, $context);
        }
        $started = microtime(true);
        $result = $tool->execute($arguments, $identity);
        logger::record('tool_called', $identity, $name, 'write', $context, [],
            $tool->is_destructive(), false, ($tool->requires_confirmation() || $tool->is_destructive()),
            'ok', (int)round((microtime(true) - $started) * 1000));
        $eventclass = $tool->is_destructive()
            ? destructive_operation_executed::class
            : write_operation_executed::class;
        $eventclass::create([
            'context' => $context,
            'relateduserid' => $identity->userid,
            'other' => ['tool' => $name],
        ])->trigger();
        return $result;
    }
}
