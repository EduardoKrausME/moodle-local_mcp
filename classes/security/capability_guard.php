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
 * capability_guard.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\security;

defined('MOODLE_INTERNAL') || die;

/**
 * Class capability_guard.
 */
final class capability_guard {
    /**
     * Method check.
     *
     * @param string $capability Parameter capability.
     * @param \context $context Parameter context.
     * @param int $userid Parameter userid.
     * @return void Return value.
     */
    public static function check(string $capability, \context $context, int $userid): void {
        if (!has_capability($capability, $context, $userid)) {
            throw new \local_mcp\exception\api_exception('permission_denied', 403);
        }
    }
}
