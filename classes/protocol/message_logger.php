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
 * Safe protocol-level diagnostic logger.
 *
 * @package local_mcp
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\protocol;

use local_mcp\diagnostics;

/**
 * Trace JSON-RPC payloads without logging bearer headers or known secret fields.
 */
final class message_logger {
    private const PART_BYTES = 7000;
    private const MAX_PARTS = 64;
    private const MAX_TEXT_BYTES = 4096;

    /**
     * @return bool Whether sensitive JSON-RPC body tracing is explicitly enabled.
     */
    public static function enabled(): bool {
        if (!diagnostics::enabled()) {
            return false;
        }
        return (string)get_config("local_mcp", "logmessages") === "1";
    }

    /**
     * @param string $direction incoming or outgoing.
     * @param string $side MCP endpoint side.
     * @param array $payload JSON-RPC body.
     * @param int $httpstatus HTTP status (0 on incoming requests).
     * @return void
     */
    public static function log(string $direction, string $side, array $payload, int $httpstatus = 0): void {
        if (!self::enabled()) {
            return;
        }
        foreach (self::format($direction, $side, $payload, $httpstatus) as $line) {
            // $CFG->local_mcp_debugger and local_mcp/logmessages are required to log JSON-RPC message bodies.
            // This helps diagnose protocol errors but can expose personal Moodle data in the PHP log.
            // phpcs:disable moodle.PHP.ForbiddenFunctions.FoundWithAlternative
            error_log($line);
            // phpcs:enable moodle.PHP.ForbiddenFunctions.FoundWithAlternative
        }
    }

    /**
     * Format bounded, numbered lines correlated with the other MCP diagnostics.
     *
     * @param string $direction Message direction.
     * @param string $side Endpoint.
     * @param array $payload Message data.
     * @param int $httpstatus HTTP status.
     * @return string[]
     */
    public static function format(string $direction, string $side, array $payload, int $httpstatus = 0): array {
        $safe = self::redact($payload);
        // Keep the serialized value ASCII-only so fragments cannot split UTF-8 characters.
        $json = json_encode($safe, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            $json = '{"error":"unserializable_message"}';
        }
        $length = strlen($json);
        $parts = max(1, min(self::MAX_PARTS, (int)ceil($length / self::PART_BYTES)));
        $base = json_decode(substr(diagnostics::format('INFO', 'protocol_message', [
            'side' => $side, 'httpstatus' => $httpstatus,
        ]), strlen('[local_mcp] ')), true);
        $base['direction'] = $direction;
        $base['message_bytes'] = $length;
        $base['parts'] = $parts;
        $base['truncated'] = $length > self::PART_BYTES * self::MAX_PARTS;
        $lines = [];
        for ($part = 0; $part < $parts; $part++) {
            $record = $base;
            $record['part'] = $part + 1;
            if ($parts === 1) {
                $record['payload'] = $safe;
            } else {
                $record['payload_fragment'] = substr($json, $part * self::PART_BYTES, self::PART_BYTES);
            }
            $lines[] = '[local_mcp] ' . json_encode($record,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        return $lines;
    }

    /**
     * Mask known secrets and large binaries, including JSON embedded in content[].text.
     *
     * @param mixed $value Source data.
     * @param string $key Field name.
     * @param int $depth Maximum recursion guard.
     * @return mixed
     */
    private static function redact(mixed $value, string $key = '', int $depth = 0): mixed {
        if ($depth > 30) {
            return '[depth limit]';
        }
        if (is_array($value) || is_object($value)) {
            $result = [];
            foreach ($value as $childkey => $child) {
                $name = (string)$childkey;
                if (preg_match('/(?:password|passwd|passphrase|secret|token|authorization|cookie|'
                    . 'code_verifier|private_key|api_key|image_base64|file_base64|audio_base64|credentials)/i', $name)) {
                    $result[$childkey] = '[REDACTED]';
                } else {
                    $result[$childkey] = self::redact($child, $name, $depth + 1);
                }
            }
            return is_object($value) ? (object)$result : $result;
        }
        if (!is_string($value)) {
            return $value;
        }
        if ($key === 'text' && str_starts_with(ltrim($value), '{')) {
            $nested = json_decode($value, true);
            if (is_array($nested)) {
                $value = json_encode(self::redact($nested, '', $depth + 1),
                    JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            }
        }
        if ($key === 'data' && strlen($value) > 128) {
            return '[binary omitted; bytes=' . strlen($value) . ']';
        }
        $value = preg_replace('~data:(?:image|audio|video|application)/[^\s"<>]+~i', '[binary omitted]', $value);
        $value = preg_replace('/\bBearer\s+[^\s"\\\\]+/i', 'Bearer [REDACTED]', $value);
        $value = preg_replace('/\bmcp_[A-Za-z0-9_-]{12,}\b/', '[REDACTED]', $value);
        $value = preg_replace('/("[a-z0-9_]*(?:password|passwd|passphrase|secret|token|authorization|api_key)'
            . '[a-z0-9_]*"\s*:\s*")[^"]*/i',
            '$1[REDACTED]', $value);
        $value = preg_replace('~(https?://[^\s?"<>]+)\?[^\s"<>]+~i', '$1?[query redacted]', $value);
        $value = preg_replace('~[A-Za-z0-9+/]{128,}={0,2}~', '[base64 omitted]', $value);
        if (strlen($value) > self::MAX_TEXT_BYTES) {
            $value = substr($value, 0, self::MAX_TEXT_BYTES) . '[text truncated]';
        }
        return $value;
    }
}
