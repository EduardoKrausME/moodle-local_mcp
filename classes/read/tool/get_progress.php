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
 * get_progress.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\read\tool;
use completion_info;
use context;
use context_course;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;

/**
 * Class get_progress.
 */
final class get_progress extends base_tool {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string {
        return 'get_progress';
    }

    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string {
        return 'Get course progress';
    }

    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string {
        return 'Return activity completion progress for a user in a course.';
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
        return $this->object_schema([
            'courseid' => ['type' => 'integer', 'minimum' => 1], 'userid' => ['type' => 'integer', 'minimum' => 1]
        ], ['courseid', 'userid']);
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
        require_once($CFG->libdir . '/completionlib.php');
        $course = get_course((int)$arguments['courseid']);
        $ctx = context_course::instance($course->id);
        if ((int)$arguments['userid'] !== $identity->userid && !has_capability('moodle/course:viewparticipants', $ctx, $identity->userid)) {
            throw new api_exception('permission_denied', 403);
        }
        $completion = new completion_info($course);
        $modinfo = get_fast_modinfo($course, (int)$arguments['userid']);
        $items = [];
        $complete = 0;
        $total = 0;
        foreach ($modinfo->cms as $cm) {
            if (!$cm->uservisible || !$completion->is_enabled($cm)) {
                continue;
            }
            $data = $completion->get_data($cm, false, (int)$arguments['userid']);
            $done = in_array((int)$data->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS, COMPLETION_COMPLETE_FAIL], true);
            $items[] = ['cmid' => (int)$cm->id, 'name' => $cm->name, 'complete' => $done];
            $total++;
            if ($done) {
                $complete++;
            }
        }
        return ['courseid' => $course->id, 'userid' => (int)$arguments['userid'], 'completed' => $complete, 'total' => $total,
            'percent' => $total ? round($complete * 100 / $total, 2) : 0, 'activities' => $items];
    }
}
