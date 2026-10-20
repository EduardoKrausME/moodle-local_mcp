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
 * Shared eligibility check for Moodle MCP connections.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\security;

use stdClass;

/**
 * Restrict OAuth authorization and token execution to active non-guest accounts.
 */
final class user_access {
    /**
     * Whether this Moodle account may authorize and use an MCP connection.
     *
     * @param stdClass $user Moodle user record.
     * @return bool
     */
    public static function is_eligible(stdClass $user): bool {
        return !empty($user->id)
            && !empty($user->confirmed)
            && empty($user->deleted)
            && empty($user->suspended)
            && !isguestuser($user);
    }
}
