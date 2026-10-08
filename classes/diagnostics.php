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
 * Structured diagnostic messages for the PHP error log.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use local_mcp\exception\api_exception;
use Throwable;

/**
 * Log MCP request milestones and failures without spilling bearer tokens or payloads.
 */
final class diagnostics {
    /** @var string|null A stable log identifier throughout the current PHP request. */
    private static ?string $requestid = null;

    /** Explicit allowlist: unrecognised metadata never reaches the error log. */
    private const FIELDS = [
        'side', 'method', 'tool', 'http_method', 'auth_source', 'userid', 'tokenid',
        'clientid', 'connectionid', 'scopes', 'contextid', 'capability', 'phase',
        'count', 'duration_ms', 'status', 'error_code', 'error_class', 'file',
        'line', 'message', 'httpstatus', 'limit', 'used', 'dry_run',
        'confirmation', 'args_keys', 'protocol', 'read', 'write',
    ];

    /**
     * Correlate diagnostic messages for one MCP request without logging its token.
     *
     * @return string
     */
    public static function request_id(): string {
        if (self::$requestid === null) {
            self::$requestid = bin2hex(random_bytes(6));
        }
        return self::$requestid;
    }

    /**
     * Serialize an event using a strict allowlist.
     *
     * @param string $level Severity, e.g. INFO, WARNING, ERROR.
     * @param string $event Stable event code.
     * @param array $fields Scalar diagnostic metadata only.
     * @return string JSON log record on one line.
     */
    public static function format(string $level, string $event, array $fields = []): string {
        $record = [
            'request' => self::request_id(),
            'level' => self::clean($level),
            'event' => self::clean($event),
        ];
        foreach ($fields as $key => $value) {
            if (!in_array($key, self::FIELDS, true) || $value === null) {
                continue;
            }
            if (is_bool($value) || is_int($value) || is_float($value)) {
                $record[$key] = $value;
            } else if (is_string($value)) {
                $record[$key] = self::clean($value);
            } else if ($key === 'args_keys' || $key === 'scopes') {
                // Log only short argument NAMES or known scope names, never values.
                if (is_array($value)) {
                    $record[$key] = array_map(
                        static fn($item): string => self::clean((string)$item),
                        array_slice(array_values($value), 0, 60)
                    );
                }
            }
        }
        return '[local_mcp] ' . json_encode($record,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * Always record a significant MCP lifecycle event using PHP error_log().
     *
     * @param string $event Event name.
     * @param array $fields Event properties.
     * @param string $level Severity.
     * @return void
     */
    public static function event(string $event, array $fields = [], string $level = 'INFO'): void {
        error_log(self::format($level, $event, $fields));
    }

    /**
     * Record an exception class, message, safe source location, and HTTP code.
     * Neither exception context arrays nor tool arguments are logged.
     *
     * @param Throwable $e Exception being reported.
     * @param array $fields Additional safe metadata.
     * @param string $level Severity.
     * @return void
     */
    public static function exception(Throwable $e, array $fields = [], string $level = 'ERROR'): void {
        $details = [
            'error_class' => get_class($e),
            'error_code' => $e instanceof api_exception ? $e->machinecode : (string)$e->getCode(),
            'httpstatus' => $e instanceof api_exception ? $e->httpstatus : 500,
            'message' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
        ];
        self::event('exception', array_merge($fields, $details), $level);
    }

    /**
     * Keep diagnostic scalar values bounded, single-line and free of sensitive data.
     *
     * @param string $value Raw scalar, never an entire request body.
     * @return string
     */
    private static function clean(string $value): string {
        // Strip all URL strings (query parameters frequently carry MCP credentials).
        $value = preg_replace('~https?://[^\\s<>"\\x27]+~i', '[url]', $value);
        // Access and confirmation tokens use the same "mcp_" prefix.
        $value = preg_replace('/mcp_[A-Za-z0-9_-]{12,}/', '[token]', $value);
        $value = preg_replace('/\\b(Bearer|authorization|password|secret|toke|token)\\s*[:=]\\s*[^\\s&]+/i',
            '$1=[redacted]', $value);
        // Avoid accidental Base64 and HTML blobs from third-party exception messages.
        $value = preg_replace('~(?:data:image/[^\\s]+)~i', '[image]', $value);
        $value = preg_replace('~[A-Za-z0-9+/]{96,}={0,2}~', '[data]', $value);
        $value = preg_replace('~<[^>]*>~', '[html]', $value);
        $value = preg_replace('/[\\r\\n\\t\\x00-\\x1f]+/', ' ', $value);
        return substr($value, 0, 400);
    }
}
