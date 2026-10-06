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
 * get_course_contents.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\read\tool;

use context;
use local_mcp\security\authenticated_identity;

/**
 * Class get_course_contents.
 */
class get_course_contents extends base_tool {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string {
        return 'get_course_contents';
    }

    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string {
        return 'Get course contents';
    }

    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string {
        return 'Return visible sections and activities for a course.';
    }

    /**
     * Method get_required_capability.
     *
     * @return string Return value.
     */
    public function get_required_capability(): string {
        return 'moodle/course:view';
    }

    /**
     * Method get_input_schema.
     *
     * @return array Return value.
     */
    public function get_input_schema(): array {
        return $this->object_schema(['courseid' => ['type' => 'integer', 'minimum' => 1]], ['courseid']);
    }

    /**
     * Method resolve_context.
     *
     * @param array $arguments Parameter arguments.
     * @return context Return value.
     */
    public function resolve_context(array $arguments): context {
        return $this->course_context((int)$arguments['courseid']);
    }

    /**
     * Method execute.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        $course = get_course((int)$arguments['courseid']);
        $modinfo = get_fast_modinfo($course, $identity->userid);
        $sections = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            if (!$section->uservisible) {
                continue;
            }
            $activities = [];
            foreach ($modinfo->sections[$section->section] ?? [] as $cmid) {
                $cm = $modinfo->cms[$cmid];
                if (!$cm->uservisible) {
                    continue;
                }
                $activities[] = ['cmid' => (int)$cm->id, 'name' => $cm->name, 'modname' => $cm->modname,
                    'url' => $cm->url ? $cm->url->out(false) : null];
            }
            $sections[] = ['id' => (int)$section->id, 'section' => (int)$section->section,
                'name' => get_section_name($course, $section), 'activities' => $activities];
        }
        return ['courseid' => (int)$course->id, 'sections' => $sections];
    }
}
