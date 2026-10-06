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
 * base_tool.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\read\tool;

defined('MOODLE_INTERNAL') || die;

/**
 * Class base_tool.
 */
abstract class base_tool implements \local_mcp\read\tool_interface {
    /**
     * Method object_schema.
     *
     * @param array $properties Parameter properties.
     * @param array $required Parameter required.
     * @return array Return value.
     */
    protected function object_schema(array $properties, array $required = []): array {
        return ['type' => 'object', 'properties' => $properties, 'required' => $required, 'additionalProperties' => false];
    }

    /**
     * Method course_context.
     *
     * @param int $courseid Parameter courseid.
     * @return \context_course Return value.
     */
    protected function course_context(int $courseid): \context_course {
        return \context_course::instance($courseid, MUST_EXIST);
    }

    /**
     * Method user_context.
     *
     * @param int $userid Parameter userid.
     * @return \context_user Return value.
     */
    protected function user_context(int $userid): \context_user {
        return \context_user::instance($userid, MUST_EXIST);
    }
}
