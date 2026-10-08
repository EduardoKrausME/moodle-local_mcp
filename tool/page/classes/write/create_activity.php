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
 * Create a Moodle page activity in a course section.
 *
 * @package   mcptool_page
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_page\write;

/**
 * Create a real Moodle page instance through the standard core course-module API.
 */
final class create_activity extends \local_mcp\write\tool\base_tool {
    /** @return string */
    public function get_name(): string {
        return 'page_create_activity';
    }

    /** @return string */
    public function get_title(): string {
        return 'page: create activity';
    }

    /** @return string */
    public function get_description(): string {
        return 'Create a page activity in a course section through the Moodle core course API.';
    }

    /** @return string */
    public function get_required_capability(): string {
        return 'moodle/course:manageactivities';
    }

    /** @return array */
    public function get_input_schema(): array {
        return $this->object_schema(['courseid' => ['type' => 'integer', 'minimum' => 1], 'sectionnum' => ['type' => 'integer', 'minimum' => 0], 'name' => ['type' => 'string', 'minLength' => 1], 'content' => ['type' => 'string'], 'intro' => ['type' => 'string']], ['courseid', 'sectionnum', 'name', 'content']);
    }

    /** @return \context */
    public function resolve_context(array $arguments): \context {
        return \context_course::instance((int)$arguments['courseid'], MUST_EXIST);
    }

    /**
     * @param array $arguments Tool input.
     * @param \local_mcp\security\authenticated_identity $identity OAuth identity.
     * @return array
     */
    public function execute(array $arguments,
            \local_mcp\security\authenticated_identity $identity): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/modlib.php');
        $course = get_course((int)$arguments['courseid']);
        if (!has_capability('mod/page:addinstance', \context_course::instance($course->id),
            $identity->userid)) {
            throw new \local_mcp\exception\api_exception('permission_denied', 403);
        }
        [$module, $context, $section, $cm, $info] = prepare_new_moduleinfo_data(
            $course, 'page', (int)$arguments['sectionnum']);
        $info->name = clean_param($arguments['name'], PARAM_TEXT);
        require_once($CFG->libdir . '/resourcelib.php');
        $info->intro = clean_text($arguments['intro'] ?? '', FORMAT_HTML);
        $info->introformat = FORMAT_HTML;
        $info->content = clean_text($arguments['content'], FORMAT_HTML);
        $info->contentformat = FORMAT_HTML;
        $info->display = RESOURCELIB_DISPLAY_OPEN;
        $info->printintro = 0;
        $info->printlastmodified = 0;
        $result = add_moduleinfo($info, $course);
        return [
            'cmid' => (int)$result->coursemodule,
            'instanceid' => (int)$result->instance,
            'courseid' => (int)$course->id,
            'url' => (new \moodle_url('/mod/page/view.php',
                ['id' => $result->coursemodule]))->out(false),
        ];
    }
}
