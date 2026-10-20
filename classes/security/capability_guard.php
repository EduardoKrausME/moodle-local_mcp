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

use context;
use local_mcp\exception\api_exception;
use local_mcp\diagnostics;

/**
 * Class capability_guard.
 */
final class capability_guard {
    /**
     * Method check.
     *
     * @param string $capability Parameter capability.
     * @param context $context Parameter context.
     * @param int $userid Parameter userid.
     * @return void Return value.
     */
    public static function check(string $capability, context $context, int $userid): void {
        $allowed = has_capability($capability, $context, $userid);

        // moodle/course:view permits privileged observers, but active enrolment is
        // the ordinary way students and teachers access their own courses.
        if (!$allowed && $capability === "moodle/course:view" && $context instanceof \context_course) {
            $allowed = can_access_course(get_course((int)$context->instanceid), $userid, "", true);
        }

        // Reading one's own profile and enrolments is not the same as viewing
        // another user's profile, which continues to require Moodle permission.
        if (!$allowed && $capability === "moodle/user:viewdetails" && $context instanceof \context_user) {
            $allowed = (int)$context->instanceid === $userid;
        }

        if (!$allowed) {
            diagnostics::event('capability_denied', [
                'userid' => $userid, 'capability' => $capability,
                'contextid' => (int)$context->id,
            ], 'WARNING');
            throw new api_exception('permission_denied', 403);
        }
    }
}
