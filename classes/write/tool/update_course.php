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
 * update_course.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;

use context;
use context_course;
use context_coursecat;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\security\capability_guard;

/**
 * Class update_course.
 */
final class update_course extends base_tool {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string {
        return 'update_course';
    }

    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string {
        return 'Update course';
    }

    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string {
        return 'Update course name, code, HTML summary, visibility or category using Moodle core APIs.';
    }

    /**
     * Method get_required_capability.
     *
     * @return string Return value.
     */
    public function get_required_capability(): string {
        return 'moodle/course:update';
    }

    /**
     * Method get_input_schema.
     *
     * @return array Return value.
     */
    public function get_input_schema(): array {
        return $this->object_schema([
            'courseid' => ['type' => 'integer', 'minimum' => 1],
            'fullname' => ['type' => 'string'],
            'shortname' => ['type' => 'string'],
            'idnumber' => ['type' => 'string'],
            'summary' => ['type' => 'string', 'description' => 'HTML description (FORMAT_HTML).'],
            'summaryformat' => ['type' => 'integer', 'enum' => [1],
                'description' => '1 = HTML.'],
            'categoryid' => ['type' => 'integer', 'minimum' => 1,
                'description' => 'Target category ID for moving the course.'],
            'visible' => ['type' => 'boolean']
        ], ['courseid']);
    }

    /**
     * Method resolve_context.
     *
     * @param array $arguments Parameter arguments.
     * @return context Return value.
     */
    public function resolve_context(array $arguments): context {
        return context_course::instance((int)$arguments['courseid'], MUST_EXIST);
    }

    /**
     * Method execute.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        if ((int)$arguments['courseid'] === SITEID) {
            throw new api_exception('invalid_course', 400, 'The site course cannot be updated this way.');
        }
        if (isset($arguments['summaryformat']) && (int)$arguments['summaryformat'] !== FORMAT_HTML) {
            throw new api_exception('invalid_summaryformat', 400, 'Only summaryformat=1 (HTML) is supported.');
        }
        $context = $this->resolve_context($arguments);
        $data = (object)['id' => (int)$arguments['courseid']];
        foreach (['fullname', 'shortname', 'idnumber'] as $f) {
            if (isset($arguments[$f])) {
                $data->$f = clean_param($arguments[$f], PARAM_TEXT);
            }
        }
        if (array_key_exists('summary', $arguments)) {
            capability_guard::check('moodle/course:changesummary', $context, $identity->userid);
            $data->summary = clean_param((string)$arguments['summary'], PARAM_CLEANHTML);
        }
        if (array_key_exists('summary', $arguments) || array_key_exists('summaryformat', $arguments)) {
            $data->summaryformat = FORMAT_HTML;
        }
        if (array_key_exists('categoryid', $arguments)) {
            $categoryid = (int)$arguments['categoryid'];
            $oldcourse = get_course((int)$arguments['courseid']);
            if ((int)$oldcourse->category !== $categoryid) {
                capability_guard::check('moodle/course:changecategory', $context, $identity->userid);
                capability_guard::check('moodle/course:create',
                    context_coursecat::instance($categoryid, MUST_EXIST), $identity->userid);
                $data->category = $categoryid;
            }
        }
        if (array_key_exists('visible', $arguments)) {
            capability_guard::check('moodle/course:visibility', $context, $identity->userid);
            $data->visible = (int)(bool)$arguments['visible'];
        }
        update_course($data);
        $course = get_course($data->id);
        return ['id' => (int)$course->id, 'fullname' => $course->fullname,
            'shortname' => $course->shortname, 'idnumber' => $course->idnumber,
            'summary' => $course->summary, 'summaryformat' => (int)$course->summaryformat,
            'categoryid' => (int)$course->category, 'visible' => (bool)$course->visible];
    }
}
