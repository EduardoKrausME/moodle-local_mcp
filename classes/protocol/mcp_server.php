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

defined('MOODLE_INTERNAL') || die;

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
        if (!in_array($side, ['read', 'write'], true)) {
            throw new \coding_exception('Invalid MCP side.');
        }
    }

    /**
     * Method handle.
     *
     * @return never Return value.
     */
    public function handle(): never {
        try {
            [$authheader, $rawtoken] = http::bearer();
            $identity = \local_mcp\security\token_resolver::from_bearer($authheader);
            $requiredscope = $this->side === 'read'
                ? \local_mcp\security\scope::READ
                : \local_mcp\security\scope::WRITE;
            if (!$identity->has_scope($requiredscope)) {
                throw new \local_mcp\exception\api_exception('insufficient_scope', 403);
            }
            $limit = (int)get_config('local_mcp', $this->side === 'read' ? 'rateread' : 'ratewrite');
            \local_mcp\security\rate_limiter::check('mcp_' . $this->side,
                $identity->type . ':' . ($identity->tokenid ?? $identity->userid), $limit ?: ($this->side === 'read' ? 120 : 30));

            $request = http::request_json();
            $id = $request['id'] ?? null;
            $method = $request['method'] ?? '';
            $params = $request['params'] ?? [];

            if ($method === 'initialize') {
                http::json(['jsonrpc' => '2.0', 'id' => $id, 'result' => [
                    'protocolVersion' => '2025-06-18',
                    'capabilities' => ['tools' => (object)[]],
                    'serverInfo' => ['name' => 'Moodle MCP ' . strtoupper($this->side), 'version' => '0.1.0'],
                ]]);
            }
            if ($method === 'notifications/initialized') {
                http::accepted();
            }
            if ($method === 'tools/list') {
                http::json(['jsonrpc' => '2.0', 'id' => $id, 'result' => ['tools' => $this->tool_definitions()]]);
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
                } catch (\local_mcp\exception\api_exception $e) {
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
        } catch (\Throwable $e) {
            http::error($e);
        }
    }

    /**
     * Method tool_definitions.
     *
     * @return array Return value.
     */
    private function tool_definitions(): array {
        $tools = $this->side === 'read'
            ? \local_mcp\read\registry::get_tools()
            : \local_mcp\write\registry::get_tools();
        $out = [];
        foreach ($tools as $tool) {
            $item = [
                'name' => $tool->get_name(),
                'title' => $tool->get_title(),
                'description' => $tool->get_description(),
                'inputSchema' => $tool->get_input_schema(),
            ];
            if ($this->side === 'write') {
                $item['annotations'] = [
                    'destructiveHint' => $tool->is_destructive(),
                    'idempotentHint' => false,
                ];
            }
            $out[] = $item;
        }
        return $out;
    }

    /**
     * Method call_tool.
     *
     * @param string $name Parameter name.
     * @param array $arguments Parameter arguments.
     * @param array $params Parameter params.
     * @param \local_mcp\security\authenticated_identity $identity Parameter identity.
     * @param string $rawtoken Parameter rawtoken.
     * @return array Return value.
     */
    private function call_tool(string $name, array $arguments, array $params,
            \local_mcp\security\authenticated_identity $identity, string $rawtoken): array {
        $tools = $this->side === 'read'
            ? \local_mcp\read\registry::get_tools()
            : \local_mcp\write\registry::get_tools();
        if (!isset($tools[$name])) {
            throw new \local_mcp\exception\api_exception('tool_not_found', 404);
        }
        $tool = $tools[$name];
        $context = $tool->resolve_context($arguments);
        \local_mcp\security\capability_guard::check($tool->get_required_capability(), $context, $identity->userid);

        if ($this->side === 'read') {
            $started = microtime(true);
            $result = $tool->execute($arguments, $identity);
            \local_mcp\audit\logger::record('tool_called', $identity, $name, 'read', $context, [],
                false, false, false, 'ok', (int)round((microtime(true) - $started) * 1000));
            return $result;
        }

        $dryrun = !empty($params['dry_run']);
        if ($dryrun) {
            if (!$tool->supports_dry_run()) {
                throw new \local_mcp\exception\api_exception('dry_run_not_supported', 400);
            }
            $preview = $tool->preview($arguments, $identity);
            \local_mcp\audit\logger::record('tool_called', $identity, $name, 'write', $context, [],
                $tool->is_destructive(), true, false, 'preview');
            return ['dry_run' => true, 'preview' => $preview];
        }

        if ($tool->requires_confirmation() || $tool->is_destructive()) {
            $confirmation = (string)($params['confirmation_token'] ?? '');
            if ($confirmation === '') {
                $preview = $tool->preview($arguments, $identity);
                $token = \local_mcp\security\confirmation_service::issue(
                    $identity, $rawtoken, $name, $arguments, $context);
                throw new \local_mcp\exception\api_exception('confirmation_required', 409, '', [
                    'preview' => $preview, 'confirmation_token' => $token,
                ]);
            }
            \local_mcp\security\confirmation_service::consume(
                $confirmation, $identity, $rawtoken, $name, $arguments, $context);
        }
        $started = microtime(true);
        $result = $tool->execute($arguments, $identity);
        \local_mcp\audit\logger::record('tool_called', $identity, $name, 'write', $context, [],
            $tool->is_destructive(), false, ($tool->requires_confirmation() || $tool->is_destructive()),
            'ok', (int)round((microtime(true) - $started) * 1000));
        $eventclass = $tool->is_destructive()
            ? \local_mcp\event\destructive_operation_executed::class
            : \local_mcp\event\write_operation_executed::class;
        $eventclass::create([
            'context' => $context,
            'relateduserid' => $identity->userid,
            'other' => ['tool' => $name],
        ])->trigger();
        return $result;
    }
}
