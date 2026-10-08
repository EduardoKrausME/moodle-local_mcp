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
 * get_course.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\read\tool;

use context;
use context_course;
use context_system;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;

/**
 * Fetch complete course metadata, including the Moodle site course (ID 1).
 */
final class get_course extends base_tool {
    public function get_name(): string {
        return 'get_course';
    }

    public function get_title(): string {
        return 'Get course';
    }

    public function get_description(): string {
        return 'Return complete metadata for a course, including its original HTML summary, '
            . 'category and visibility. Works for the Moodle site course (ID 1).';
    }

    public function get_required_capability(): string {
        return 'moodle/course:view';
    }

    public function get_input_schema(): array {
        return $this->object_schema([
            'courseid' => ['type' => 'integer', 'minimum' => 1],
        ], ['courseid']);
    }

    public function resolve_context(array $arguments): context {
        $courseid = (int)$arguments['courseid'];
        return $courseid === SITEID
            ? context_system::instance()
            : context_course::instance($courseid, MUST_EXIST);
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        global $DB;

        $courseid = (int)$arguments['courseid'];
        // Read the complete DB record instead of reusing the possibly partial
        // $SITE/$COURSE global returned by get_course(), particularly for SITEID.
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        if ($courseid !== SITEID && !$course->visible) {
            $context = context_course::instance($courseid, MUST_EXIST);
            if (!has_any_capability([
                    'moodle/course:viewhiddencourses',
                    'moodle/course:update',
                ], $context, $identity->userid)) {
                throw new api_exception('permission_denied', 403);
            }
        }

        return [
            'id' => (int)$course->id,
            'fullname' => $course->fullname,
            'shortname' => $course->shortname,
            'idnumber' => $course->idnumber,
            'categoryid' => (int)$course->category,
            'summary' => $course->summary,
            'summaryformat' => (int)$course->summaryformat,
            'visible' => (bool)$course->visible,
            'startdate' => (int)$course->startdate,
            'enddate' => (int)$course->enddate,
        ];
    }
}
