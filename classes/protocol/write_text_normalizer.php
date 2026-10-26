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
 * Normalize ChatGPT-generated typography in incoming MCP WRITE arguments.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\protocol;

/**
 * Replace em dashes in WRITE argument strings without altering JSON value types.
 */
final class write_text_normalizer {
    /**
     * Replace Unicode em dashes with ASCII hyphens, preserving JSON value types.
     *
     * @param mixed $value A decoded JSON value.
     * @return mixed The normalized value.
     */
    public static function normalize(mixed $value): mixed {
        if (is_string($value)) {
            return str_replace("\u{2014}", '-', $value);
        }
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::normalize($item);
            }
        } else if ($value instanceof \stdClass) {
            $value = clone $value;
            foreach ($value as $key => $item) {
                $value->{$key} = self::normalize($item);
            }
        }
        return $value;
    }
}
