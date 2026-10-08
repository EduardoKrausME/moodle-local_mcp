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
 * OAuth resource indicators.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\oauth;

use local_mcp\exception\api_exception;

/**
 * Bind access tokens to the exact MCP endpoint which requested them.
 */
final class resource {
    /**
     * Return the canonical URL for a READ, WRITE or combined MCP server.
     *
     * @param string $side read, write or server
     * @return string
     */
    public static function endpoint(string $side): string {
        global $CFG;
        if (!in_array($side, ['read', 'write', 'server'], true)) {
            throw new api_exception('invalid_target', 400);
        }
        return rtrim($CFG->wwwroot, '/') . '/local/mcp/' . $side . '.php';
    }

    /**
     * Validate an RFC 8707 resource indicator, rejecting untrusted audiences.
     *
     * @param string $value Resource URI.
     * @return string Validated URI.
     */
    public static function validate(string $value): string {
        foreach (['read', 'write', 'server'] as $side) {
            $expected = self::endpoint($side);
            if (hash_equals($expected, $value)) {
                return $expected;
            }
        }
        throw new api_exception('invalid_target', 400);
    }
}
