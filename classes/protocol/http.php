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
 * http.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\protocol;

use local_mcp\exception\api_exception;
use local_mcp\diagnostics;
use moodle_url;
use Throwable;

/**
 * Class http.
 */
final class http {
    /** @var bool Whether MCP JSON-RPC traffic logging is enabled. */
    private static bool $tracemessages = false;

    /** @var string Current MCP endpoint. */
    private static string $traceside = 'server';

    /**
     * Enable tracing for this MCP endpoint (never for OAuth endpoints).
     *
     * @param string $side Endpoint side.
     * @return void
     */
    public static function trace_start(string $side): void {
        self::$traceside = $side;
        self::$tracemessages = message_logger::enabled();
    }

    /**
     * Method json.
     *
     * @param array $data Parameter data.
     * @param int $status Parameter status.
     * @param array $headers Parameter headers.
     * @return never Return value.
     */
    public static function json(array $data, int $status = 200, array $headers = []): never {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        foreach ($headers as $header) {
            header($header);
        }
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (self::$tracemessages) {
            message_logger::log('outgoing', self::$traceside, $data, $status);
        }
        echo $json;
        exit;
    }

    /**
     * Send an empty 202 Accepted response for JSON-RPC notifications.
     *
     * @return never
     */
    public static function accepted(): never {
        if (self::$tracemessages) {
            message_logger::log('outgoing', self::$traceside, ['notice' => 'empty notification response'], 202);
        }
        http_response_code(202);
        header('Cache-Control: no-store');
        exit;
    }

    /**
     * Method request_json.
     *
     * @return array Return value.
     */
    public static function request_json(): array {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '{}', true);
        if (!is_array($data)) {
            if (self::$tracemessages) {
                message_logger::log('incoming', self::$traceside,
                    ['error' => 'invalid_json', 'body_bytes' => strlen((string)$raw)]);
            }
            throw new api_exception('invalid_request', 400);
        }
        if (self::$tracemessages) {
            message_logger::log('incoming', self::$traceside, $data);
        }
        return $data;
    }

    /**
     * Read the OAuth bearer token from a request header.
     *
     * Query-string credentials are rejected; MCP no longer issues manual
     * tokens, and OAuth access tokens must never be sent in a URL.
     *
     * @return array Authorization header and raw access token.
     */
    public static function bearer(): array {
        if (array_key_exists('toke', $_GET) || array_key_exists('token', $_GET)) {
            self::reject_bearer('query_credentials_rejected');
        }

        [$header, $source] = self::authorization_header();
        if ($header === '') {
            self::reject_bearer('missing_authorization_header');
        }
        if (!preg_match('/^Bearer[ \t]+(\S+)$/iD', trim($header), $matches)) {
            self::reject_bearer('malformed_authorization_header');
        }

        diagnostics::event('bearer_received', [
            'phase' => 'bearer_extraction',
            'auth_source' => $source,
        ]);
        return [$header, $matches[1]];
    }

    /**
     * Report where Authorization is available, without exposing its value.
     *
     * @return string Header source or none.
     */
    public static function authorization_source(): string {
        return self::authorization_header()[1];
    }

    /**
     * Look for Authorization in CGI/FastCGI variables and Apache headers.
     *
     * @return array Header value and the safe name of its source.
     */
    private static function authorization_header(): array {
        foreach ([
            'HTTP_AUTHORIZATION' => 'http_authorization',
            'REDIRECT_HTTP_AUTHORIZATION' => 'redirect_http_authorization',
        ] as $key => $source) {
            if (!empty($_SERVER[$key]) && is_string($_SERVER[$key])) {
                return [$_SERVER[$key], $source];
            }
        }
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                foreach ($headers as $name => $value) {
                    if (strcasecmp((string)$name, 'Authorization') === 0 &&
                            is_string($value) && $value !== '') {
                        return [$value, 'webserver_headers'];
                    }
                }
            }
        }
        return ['', 'none'];
    }

    /**
     * Log the bearer extraction failure without logging credentials.
     *
     * @param string $reason Safe, fixed diagnostic reason.
     * @return never
     */
    private static function reject_bearer(string $reason): never {
        diagnostics::event('auth_rejected', [
            'phase' => 'bearer_extraction',
            'auth_source' => self::authorization_source(),
            'http_method' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
            'reason' => $reason,
        ], 'WARNING');
        throw new api_exception('invalid_token', 401);
    }

    /**
     * Method error.
     *
     * @param Throwable $e Parameter e.
     * @param string $side Requested MCP endpoint.
     * @return never Return value.
     */
    public static function error(Throwable $e, string $side = 'server'): never {
        diagnostics::exception($e, ['side' => $side, 'phase' => 'http_dispatch'],
            $e instanceof api_exception ? 'WARNING' : 'ERROR');
        if ($e instanceof api_exception) {
            $headers = [];
            if ($e->httpstatus === 401) {
                $metadata = (new moodle_url('/local/mcp/oauth-resource.php',
                    ['side' => $side]))->out(false);
                $headers[] = 'WWW-Authenticate: Bearer resource_metadata="' . $metadata . '"';
            }
            self::json(['error' => $e->machinecode, 'message' => $e->getMessage(), 'details' => $e->details],
                $e->httpstatus, $headers);
        }
        self::json(['error' => 'internal_error', 'message' => 'The request could not be completed.'], 500);
    }
}
