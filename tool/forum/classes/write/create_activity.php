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
 * Create a Moodle forum activity in a course section.
 *
 * @package   mcptool_forum
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_forum\write;

use coding_exception;
use context;
use context_course;
use dml_exception;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\write\tool\base_tool;
use moodle_exception;
use moodle_url;

/**
 * Create a real Moodle forum instance through the standard core course-module API.
 */
final class create_activity extends base_tool {
    /**
     * get_name
     *
     * @return string
     */
    public function get_name(): string {
        return 'forum_create_activity';
    }

    /**
     * get_title
     *
     * @return string
     */
    public function get_title(): string {
        return 'forum: create activity';
    }

    /**
     * get_description
     *
     * @return string
     */
    public function get_description(): string {
        return 'Create a forum activity in a course section through the Moodle core course API.';
    }

    /**
     * get_required_capability
     *
     * @return string
     */
    public function get_required_capability(): string {
        return 'moodle/course:manageactivities';
    }

    /**
     * get_input_schema
     *
     * @return array
     */
    public function get_input_schema(): array {
        return $this->object_schema(['courseid' => ['type' => 'integer', 'minimum' => 1], 'sectionnum' => ['type' => 'integer', 'minimum' => 0], 'name' => ['type' => 'string', 'minLength' => 1], 'intro' => ['type' => 'string']], ['courseid', 'sectionnum', 'name']);
    }

    /**
     * resolve_context
     *
     * @param array $arguments
     * @return context
     */
    public function resolve_context(array $arguments): context {
        return context_course::instance((int)$arguments['courseid'], MUST_EXIST);
    }

    /**
     * execute
     *
     * @param array $arguments
     * @param authenticated_identity $identity
     * @return array
     * @throws coding_exception
     * @throws \core\exception\moodle_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws api_exception
     */
    public function execute(array                  $arguments,
                            authenticated_identity $identity): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/modlib.php');
        $course = get_course((int)$arguments['courseid']);
        if (!has_capability('mod/forum:addinstance', context_course::instance($course->id),
            $identity->userid)) {
            throw new api_exception('permission_denied', 403);
        }
        [$module, $context, $section, $cm, $info] = prepare_new_moduleinfo_data(
            $course, 'forum', (int)$arguments['sectionnum']);
        $info->name = clean_param($arguments['name'], PARAM_TEXT);
        $info->type = 'general';
        $info->cmidnumber = "";
        $info->intro = clean_text($arguments['intro'] ?? '', FORMAT_HTML);
        $info->introformat = FORMAT_HTML;
        // Preserve the HTML introduction when add_moduleinfo() processes the editor draft.
        $info->introeditor["text"] = $info->intro;
        $info->introeditor["format"] = FORMAT_HTML;
        $info->assessed = 0;
        $info->scale = 0;
        $info->grade_forum = 0;
        $info->maxattachments = 0;
        $info->forcesubscribe = 0;
        $info->trackingtype = 0;
        $info->duedate = 0;
        $info->cutoffdate = 0;
        $info->maxbytes = 0;
        $info->maxattachments = 0;
        $result = add_moduleinfo($info, $course);
        return [
            'cmid' => (int)$result->coursemodule,
            'instanceid' => (int)$result->instance,
            'courseid' => (int)$course->id,
            'url' => (new moodle_url('/mod/forum/view.php',
                ['id' => $result->coursemodule]))->out(false),
        ];
    }
}
