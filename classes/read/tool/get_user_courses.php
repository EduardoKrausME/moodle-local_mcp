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
 * get_user_courses.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\read\tool;

use context;
use context_course;
use local_mcp\security\authenticated_identity;

defined('MOODLE_INTERNAL') || die;

/**
 * Class get_user_courses.
 */
final class get_user_courses extends base_tool {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string {
        return 'get_user_courses';
    }

    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string {
        return 'Get user courses';
    }

    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string {
        return 'Return courses in which a user is enrolled.';
    }

    /**
     * Method get_required_capability.
     *
     * @return string Return value.
     */
    public function get_required_capability(): string {
        return 'moodle/user:viewdetails';
    }

    /**
     * Method get_input_schema.
     *
     * @return array Return value.
     */
    public function get_input_schema(): array {
        return $this->object_schema(['userid' => ['type' => 'integer', 'minimum' => 1]], ['userid']);
    }

    /**
     * Method resolve_context.
     *
     * @param array $arguments Parameter arguments.
     * @return context Return value.
     */
    public function resolve_context(array $arguments): context {
        return $this->user_context((int)$arguments['userid']);
    }

    /**
     * Method execute.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        $out = [];
        foreach (enrol_get_all_users_courses((int)$arguments['userid'], true, ['id', 'fullname', 'shortname', 'visible']) as $course) {
            $ctx = context_course::instance($course->id);
            if ((int)$arguments['userid'] === $identity->userid || has_capability('moodle/course:viewparticipants', $ctx, $identity->userid)) {
                $out[] = ['id' => (int)$course->id, 'fullname' => $course->fullname, 'shortname' => $course->shortname, 'visible' => (bool)$course->visible];
            }
        }
        return ['courses' => $out];
    }
}
