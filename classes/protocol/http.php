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
use moodle_url;
use Throwable;

/**
 * Class http.
 */
final class http {
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
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send an empty 202 Accepted response for JSON-RPC notifications.
     *
     * @return never
     */
    public static function accepted(): never {
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
            throw new api_exception('invalid_request', 400);
        }
        return $data;
    }

    /**
     * Method bearer.
     *
     * @return array Return value.
     */
    public static function bearer(): array {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(.+)$/i', trim($header), $m)) {
            throw new api_exception('invalid_token', 401);
        }
        return [$header, trim($m[1])];
    }

    /**
     * Method error.
     *
     * @param Throwable $e Parameter e.
     * @param string $side Requested MCP endpoint.
     * @return never Return value.
     */
    public static function error(Throwable $e, string $side = 'server'): never {
        if ($e instanceof api_exception) {
            $headers = [];
            if ($e->httpstatus === 401) {
                $metadata = (new moodle_url('/local/mcp/.well-known/oauth-protected-resource.php',
                    ['side' => $side]))->out(false);
                $headers[] = 'WWW-Authenticate: Bearer resource_metadata="' . $metadata . '"';
            }
            self::json(['error' => $e->machinecode, 'message' => $e->getMessage(), 'details' => $e->details],
                $e->httpstatus, $headers);
        }
        self::json(['error' => 'internal_error', 'message' => 'The request could not be completed.'], 500);
    }
}
