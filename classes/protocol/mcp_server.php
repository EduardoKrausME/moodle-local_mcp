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
use local_mcp\diagnostics;
use core\session\manager;
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
use local_mcp\write\tool_interface;
use stdClass;
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
     * Obtain the server version directly from the plugin release.
     *
     * @return string
     */
    private static function plugin_release(): string {
        $plugin = new stdClass();
        require dirname(__DIR__, 2) . '/version.php';
        return (string)$plugin->release;
    }

    /**
     * Method handle.
     *
     * @return never Return value.
     */
    public function handle(): never {
        $startedrequest = microtime(true);
        diagnostics::event('request_received', [
            'side' => $this->side,
            'http_method' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
            'auth_source' => (isset($_GET['toke']) || isset($_GET['token']))
                ? 'url_token' : (isset($_SERVER['HTTP_AUTHORIZATION']) ? 'header' : 'none'),
        ]);
        try {
            // Stateless Streamable HTTP: clients can use POST without an SSE session.
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                header('Allow: POST');
                throw new api_exception('method_not_allowed', 405);
            }
            [$authheader, $rawtoken] = http::bearer($this->side === 'server');
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
            manager::set_user($user);
            diagnostics::event('authenticated', [
                'side' => $this->side,
                'userid' => $identity->userid,
                'tokenid' => $identity->tokenid,
                'clientid' => $identity->clientid,
                'connectionid' => $identity->connectionid,
                'scopes' => $identity->scopes,
            ]);
            if ($this->side !== 'server') {
                $requiredscope = $this->side === 'read' ? scope::READ : scope::WRITE;
                if (!$identity->has_scope($requiredscope)) {
                    throw new api_exception('insufficient_scope', 403);
                }
                $limit = (int)get_config('local_mcp', $this->side === 'read' ? 'rateread' : 'ratewrite');
                rate_limiter::check('mcp_' . $this->side,
                    $identity->type . ':' . ($identity->tokenid ?? $identity->userid),
                    $limit ?: ($this->side === 'read'
                        ? rate_limiter::DEFAULT_READ_PER_MINUTE
                        : rate_limiter::DEFAULT_WRITE_PER_MINUTE));
            }

            $request = http::request_json();
            $id = $request['id'] ?? null;
            $method = $request['method'] ?? '';
            $params = $request['params'] ?? [];
            diagnostics::event('protocol_method', [
                'side' => $this->side,
                'method' => (string)$method,
                'userid' => $identity->userid,
            ]);

            if ($method === 'server/discover') {
                diagnostics::event('discovery_completed', ['side' => $this->side]);
                http::json(['jsonrpc' => '2.0', 'id' => $id,
                    'result' => discovery::result($this->side, self::plugin_release())]);
            }
            if ($method === 'initialize') {
                diagnostics::event('initialize_completed', ['side' => $this->side]);
                http::json(['jsonrpc' => '2.0', 'id' => $id,
                    'result' => discovery::initialize_result($this->side, self::plugin_release())]);
            }
            if ($method === 'notifications/initialized') {
                http::accepted();
            }
            $modern = discovery::is_modern(
                is_array($params) ? $params : [],
                (string)($_SERVER['HTTP_MCP_PROTOCOL_VERSION'] ?? '')
            );
            if ($method === 'tools/list') {
                $definitions = $this->tool_definitions($identity);
                diagnostics::event('tools_listed', [
                    'side' => $this->side,
                    'userid' => $identity->userid,
                    'count' => count($definitions),
                    'catalog_sha256' => hash('sha256', json_encode($definitions,
                        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                    'protocol' => $modern ? '2026-07-28' : 'legacy',
                ]);
                http::json(['jsonrpc' => '2.0', 'id' => $id, 'result' => discovery::decorate_result(
                    ['tools' => $definitions], $modern, true)]);
            }
            if ($method === 'tools/call') {
                $toolname = (string)($params['name'] ?? '');
                $argumentkeys = is_array($params['arguments'] ?? null)
                    ? array_keys($params['arguments']) : [];
                diagnostics::event('tool_call_received', [
                    'side' => $this->side,
                    'tool' => $toolname,
                    'userid' => $identity->userid,
                    'args_keys' => $argumentkeys,
                ]);
                try {
                    $result = $this->call_tool(
                        (string)($params['name'] ?? ''),
                        (array)($params['arguments'] ?? []),
                        $params,
                        $identity,
                        $rawtoken
                    );
                    // Images are sent as visual MCP content, not embedded into textual JSON.
                    $additionalcontent = $result['_mcp_content'] ?? [];
                    unset($result['_mcp_content']);
                    $content = [[
                        'type' => 'text',
                        'text' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]];
                    if (is_array($additionalcontent)) {
                        foreach ($additionalcontent as $block) {
                            if (is_array($block) && ($block['type'] ?? '') === 'image'
                                && !empty($block['data']) && !empty($block['mimeType'])) {
                                $content[] = $block;
                            }
                        }
                    }
                    diagnostics::event('tool_response_ready', [
                        'tool' => $toolname,
                        'userid' => $identity->userid,
                        'status' => !empty($result['confirmation_required'])
                            ? 'confirmation_pending' : 'completed',
                        'duration_ms' => (int)round((microtime(true) - $startedrequest) * 1000),
                    ]);
                    http::json(['jsonrpc' => '2.0', 'id' => $id, 'result' => [
                        'content' => $content,
                        'structuredContent' => $result,
                        'isError' => false,
                    ] + ($modern ? ['resultType' => 'complete'] : [])]);
                } catch (api_exception $e) {
                    diagnostics::event('tool_call_failed', [
                        'side' => $this->side,
                        'tool' => $toolname,
                        'userid' => $identity->userid,
                        'status' => 'error',
                        'error_code' => $e->machinecode,
                        'httpstatus' => $e->httpstatus,
                        'duration_ms' => (int)round((microtime(true) - $startedrequest) * 1000),
                    ], 'WARNING');
                    diagnostics::exception($e, ['side' => $this->side, 'tool' => $toolname,
                        'userid' => $identity->userid], 'WARNING');
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
                    ] + ($modern ? ['resultType' => 'complete'] : [])]);
                } catch (Throwable $e) {
                    // Keep internal details out of responses, but make 500-like
                    // failures diagnosable in the Moodle/PHP server log.
                    $reference = diagnostics::request_id();
                    diagnostics::event('tool_call_failed', [
                        'side' => $this->side,
                        'tool' => $toolname,
                        'userid' => $identity->userid,
                        'status' => 'error',
                        'error_code' => 'internal_error',
                        'httpstatus' => 500,
                        'duration_ms' => (int)round((microtime(true) - $startedrequest) * 1000),
                    ], 'ERROR');
                    diagnostics::exception($e, ['side' => $this->side,
                        'tool' => $toolname, 'userid' => $identity->userid]);
                    $error = [
                        'error' => 'internal_error',
                        'message' => 'The Moodle tool failed internally. Check the server PHP log '
                            . 'for reference ' . $reference . '.',
                        'reference' => $reference,
                    ];
                    http::json(['jsonrpc' => '2.0', 'id' => $id, 'result' => [
                        'content' => [[
                            'type' => 'text',
                            'text' => json_encode($error, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ]],
                        'structuredContent' => $error,
                        'isError' => true,
                    ] + ($modern ? ['resultType' => 'complete'] : [])]);
                }
            }
            diagnostics::event('unknown_method', [
                'side' => $this->side,
                'method' => (string)$method,
                'userid' => $identity->userid,
            ], 'WARNING');
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
            $iswrite = $tool instanceof tool_interface;
            $requiredscope = $iswrite ? scope::WRITE : scope::READ;
            if (!$identity->has_scope($requiredscope)) {
                continue;
            }
            $schema = $tool->get_input_schema();
            if ($iswrite && ($tool->requires_confirmation() || $tool->is_destructive())) {
                $schema['properties']['confirmation_token'] = [
                    'type' => 'string',
                    'description' => 'One-time token returned when confirmation_required is true. '
                        . 'Never retry with this token until the user explicitly approves the preview. '
                        . 'Then repeat the same tool with identical original arguments plus this token.',
                ];
            }
            if ($iswrite && $tool->supports_dry_run()) {
                $schema['properties']['dry_run'] = [
                    'type' => 'boolean',
                    'description' => 'Preview without changing Moodle data.',
                ];
            }
            // Validate provider schemas here because client-side MCP errors never reach PHP.
            $issues = [];
            $schema = tool_schema::normalize($schema, $issues);
            $invalid = false;
            foreach ($issues as $issue) {
                $repaired = $issue['repaired'];
                diagnostics::event('tool_schema_invalid', [
                    'side' => $this->side,
                    'tool' => $tool->get_name(),
                    'provider' => get_class($tool),
                    'schema_path' => $issue['path'],
                    'expected' => $issue['expected'],
                    'actual' => $issue['actual'],
                    'resolution' => $repaired ? 'normalized' : 'excluded',
                ], $repaired ? 'WARNING' : 'ERROR');
                if (!$repaired) {
                    $invalid = true;
                }
            }
            if ($invalid) {
                continue;
            }
            $item = [
                'name' => $tool->get_name(),
                'title' => $tool->get_title(),
                'description' => $tool->get_description()
                    . ($iswrite && ($tool->requires_confirmation() || $tool->is_destructive())
                        ? ' The first call returns a preview and confirmation_required=true without changes. '
                            . 'Show the preview and obtain explicit user approval before retrying with confirmation_token.'
                        : ''),
                'inputSchema' => $schema,

                'annotations' => [
                    'readOnlyHint' => !$iswrite,
                    'destructiveHint' => $iswrite && $tool->is_destructive(),
                    'idempotentHint' => !$iswrite,
                    'openWorldHint' => false,
                ],
            ];
            if ($identity->type === 'oauth') {
                $item['securitySchemes'] = [['type' => 'oauth2', 'scopes' => [$requiredscope]]];
            }
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
        $iswrite = $tool instanceof tool_interface;
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
                $limit ?: ($iswrite
                    ? rate_limiter::DEFAULT_WRITE_PER_MINUTE
                    : rate_limiter::DEFAULT_READ_PER_MINUTE));
        }
        $confirmation = (string)($arguments['confirmation_token'] ?? ($params['confirmation_token'] ?? ''));
        $dryrun = !empty($arguments['dry_run']) || !empty($params['dry_run']);
        unset($arguments['confirmation_token'], $arguments['dry_run']);
        $context = $tool->resolve_context($arguments);
        capability_guard::check($tool->get_required_capability(), $context, $identity->userid);

        diagnostics::event('tool_permission_granted', [
            'tool' => $name,
            'side' => $iswrite ? 'write' : 'read',
            'userid' => $identity->userid,
            'contextid' => (int)$context->id,
            'capability' => $tool->get_required_capability(),
            'dry_run' => $dryrun,
            'confirmation' => $confirmation !== '',
        ]);
        if (!$iswrite) {
            $started = microtime(true);
            $result = $tool->execute($arguments, $identity);
            logger::record('tool_called', $identity, $name, 'read', $context, [],
                false, false, false, 'ok', (int)round((microtime(true) - $started) * 1000));
            diagnostics::event('tool_read_completed', [
                'tool' => $name, 'contextid' => (int)$context->id,
                'duration_ms' => (int)round((microtime(true) - $started) * 1000),
            ]);
            return $result;
        }

        if ($dryrun) {
            if (!$tool->supports_dry_run()) {
                throw new api_exception('dry_run_not_supported', 400);
            }
            $preview = $tool->preview($arguments, $identity);
            logger::record('tool_called', $identity, $name, 'write', $context, [],
                $tool->is_destructive(), true, false, 'preview');
            diagnostics::event('tool_dry_run_completed', [
                'tool' => $name, 'contextid' => (int)$context->id,
            ]);
            return ['dry_run' => true, 'preview' => $preview];
        }

        if ($tool->requires_confirmation() || $tool->is_destructive()) {
            if ($confirmation === '') {
                $preview = $tool->preview($arguments, $identity);
                $token = confirmation_service::issue(
                    $identity, $rawtoken, $name, $arguments, $context);
                diagnostics::event('confirmation_issued', [
                    'tool' => $name, 'userid' => $identity->userid,
                    'contextid' => (int)$context->id,
                    'status' => 'confirmation_pending',
                ]);
                // A pending approval is a normal tool result, not an MCP failure.
                // Never write until a second call supplies the valid one-time token.
                return [
                    'confirmation_required' => true,
                    'status' => 'confirmation_pending',
                    'tool' => $name,
                    'preview' => $preview,
                    'confirmation_token' => $token,
                    'message' => 'No changes have been made. Show this preview to the user and '
                        . 'request explicit approval. Only if approved, call the same tool again '
                        . 'with identical original arguments plus confirmation_token.',
                ];
            }
            confirmation_service::consume(
                $confirmation, $identity, $rawtoken, $name, $arguments, $context);
            diagnostics::event('confirmation_accepted', [
                'tool' => $name, 'userid' => $identity->userid,
                'contextid' => (int)$context->id,
                'status' => 'approved',
            ]);
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
        diagnostics::event('tool_write_completed', [
            'tool' => $name, 'userid' => $identity->userid,
            'contextid' => (int)$context->id,
            'status' => 'completed',
            'duration_ms' => (int)round((microtime(true) - $started) * 1000),
        ]);
        return $result;
    }
}
