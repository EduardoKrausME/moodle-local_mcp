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
 * get_course_participants.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\read\tool;

defined('MOODLE_INTERNAL') || die;

/**
 * Class get_course_participants.
 */
final class get_course_participants extends base_tool {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string { return 'get_course_participants'; }
    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string { return 'Get course participants'; }
    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string { return 'Return enrolled users in a course.'; }
    /**
     * Method get_required_capability.
     *
     * @return string Return value.
     */
    public function get_required_capability(): string { return 'moodle/course:viewparticipants'; }
    /**
     * Method get_input_schema.
     *
     * @return array Return value.
     */
    public function get_input_schema(): array { return $this->object_schema(['courseid'=>['type'=>'integer','minimum'=>1]], ['courseid']); }
    /**
     * Method resolve_context.
     *
     * @param array $arguments Parameter arguments.
     * @return \context Return value.
     */
    public function resolve_context(array $arguments): \context { return $this->course_context((int)$arguments['courseid']); }
    /**
     * Method execute.
     *
     * @param array $arguments Parameter arguments.
     * @param \local_mcp\security\authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        $ctx = $this->course_context((int)$arguments['courseid']);
        $users = get_enrolled_users($ctx, '', 0, 'u.id,u.firstname,u.lastname,u.email', 'u.lastname,u.firstname', 0, 200);
        $out = [];
        foreach ($users as $user) { $out[] = ['id'=>(int)$user->id,'fullname'=>fullname($user),'email'=>$user->email]; }
        return ['participants'=>$out];
    }
}
