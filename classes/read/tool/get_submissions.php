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
 * get_submissions.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\read\tool;
use assign;
use context;
use context_module;
use local_mcp\security\authenticated_identity;

/**
 * Class get_submissions.
 */
final class get_submissions extends base_tool {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string {
        return 'get_submissions';
    }

    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string {
        return 'Get assignment submissions';
    }

    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string {
        return 'Return assignment submission metadata for grading/reporting.';
    }

    /**
     * Method get_required_capability.
     *
     * @return string Return value.
     */
    public function get_required_capability(): string {
        return 'mod/assign:grade';
    }

    /**
     * Method get_input_schema.
     *
     * @return array Return value.
     */
    public function get_input_schema(): array {
        return $this->object_schema(['cmid' => ['type' => 'integer', 'minimum' => 1]], ['cmid']);
    }

    /**
     * Method resolve_context.
     *
     * @param array $arguments Parameter arguments.
     * @return context Return value.
     */
    public function resolve_context(array $arguments): context {
        return context_module::instance((int)$arguments['cmid'], MUST_EXIST);
    }

    /**
     * Method execute.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        [$course, $cm] = get_course_and_cm_from_cmid((int)$arguments['cmid'], 'assign');
        $assign = new assign(context_module::instance($cm->id), $cm, $course);
        $subs = $DB->get_records('assign_submission', ['assignment' => $assign->get_instance()->id], 'timemodified DESC',
            'id,userid,status,timecreated,timemodified,attemptnumber');
        $out = [];
        foreach ($subs as $s) {
            $out[] = ['id' => (int)$s->id, 'userid' => (int)$s->userid, 'status' => $s->status,
                'timecreated' => (int)$s->timecreated, 'timemodified' => (int)$s->timemodified, 'attemptnumber' => (int)$s->attemptnumber];
        }
        return ['submissions' => $out];
    }
}
