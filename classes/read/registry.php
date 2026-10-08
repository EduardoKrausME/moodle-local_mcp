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
 * registry.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\read;

use coding_exception;
use local_mcp\extension\manager;
use local_mcp\read\tool\get_course;
use local_mcp\read\tool\get_course_contents;
use local_mcp\read\tool\get_course_participants;
use local_mcp\read\tool\get_user;
use local_mcp\read\tool\get_user_courses;
use local_mcp\read\tool\search_courses;
use local_mcp\read\tool\search_users;

/**
 * Class registry.
 */
final class registry {
    /** @return tool_interface[] */
    public static function get_tools(): array {
        $tools = [
            new \local_mcp\read\tool\get_assignment(),
            new \local_mcp\read\tool\get_assignments(),
            new \local_mcp\read\tool\get_attempts(),
            new \local_mcp\read\tool\get_calendar_events(),
            new \local_mcp\read\tool\get_course(),
            new \local_mcp\read\tool\get_course_image(),
            new \local_mcp\read\tool\get_course_activities(),
            new \local_mcp\read\tool\get_course_contents(),
            new \local_mcp\read\tool\get_course_participants(),
            new \local_mcp\read\tool\get_course_sections(),
            new \local_mcp\read\tool\get_grades(),
            new \local_mcp\read\tool\list_categories(),
            new \local_mcp\read\tool\get_progress(),
            new \local_mcp\read\tool\get_quiz(),
            new \local_mcp\read\tool\get_quizzes(),
            new \local_mcp\read\tool\get_submissions(),
            new \local_mcp\read\tool\get_user(),
            new \local_mcp\read\tool\get_user_courses(),
            new \local_mcp\read\tool\search_courses(),
            new \local_mcp\read\tool\search_users(),
        ];
        foreach (manager::read_providers() as $provider) {
            foreach ($provider->get_read_tools() as $tool) {
                if (!$tool instanceof tool_interface) {
                    throw new coding_exception('READ provider returned a non-READ tool.');
                }
                $tools[] = $tool;
            }
        }
        return self::index($tools);
    }

    /**
     * Method index.
     *
     * @param array $tools Parameter tools.
     * @return array Return value.
     */
    private static function index(array $tools): array {
        $indexed = [];
        foreach ($tools as $tool) {
            $name = $tool->get_name();
            if (isset($indexed[$name])) {
                throw new coding_exception('Duplicate MCP READ tool: ' . $name);
            }
            $indexed[$name] = $tool;
        }
        return $indexed;
    }
}
