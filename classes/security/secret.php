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
 * secret.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\security;

/**
 * Class secret.
 */
final class secret {
    /**
     * Method generate.
     *
     * @param string $prefix Parameter prefix.
     * @param int $bytes Parameter bytes.
     * @return string Return value.
     */
    public static function generate(string $prefix = 'mcp_', int $bytes = 32): string {
        return $prefix . self::base64url(random_bytes($bytes));
    }

    /**
     * Method hash.
     *
     * @param string $value Parameter value.
     * @return string Return value.
     */
    public static function hash(string $value): string {
        return hash('sha256', $value);
    }

    /**
     * Method prefix.
     *
     * @param string $value Parameter value.
     * @return string Return value.
     */
    public static function prefix(string $value): string {
        return substr($value, 0, 16);
    }

    /**
     * Method equals_hash.
     *
     * @param string $value Parameter value.
     * @param string $hash Parameter hash.
     * @return bool Return value.
     */
    public static function equals_hash(string $value, string $hash): bool {
        return hash_equals($hash, self::hash($value));
    }

    /**
     * Method base64url.
     *
     * @param string $value Parameter value.
     * @return string Return value.
     */
    public static function base64url(string $value): string {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
