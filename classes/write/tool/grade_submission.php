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
 * grade_submission.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;

use assign;
use context;
use context_module;
use local_mcp\security\authenticated_identity;
use local_mcp\security\input_guard;
use local_mcp\exception\api_exception;
use context_course;

/**
 * Class grade_submission.
 */
final class grade_submission extends base_tool {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string {
        return 'grade_submission';
    }

    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string {
        return 'Grade assignment submission';
    }

    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string {
        return 'Grade an assignment submission through the assignment API.';
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
        return $this->object_schema([
            'cmid' => ['type' => 'integer', 'minimum' => 1], 'userid' => ['type' => 'integer', 'minimum' => 1], 'grade' => ['type' => 'number']
        ], ['cmid', 'userid', 'grade']);
    }

    /**
     * Method resolve_context.
     *
     * @param array $arguments Parameter arguments.
     * @return context Return value.
     */
    public function resolve_context(array $arguments): context {
        return input_guard::module((int)$arguments["cmid"], "assign");
    }

    /**
     * Validate the actual assignment module, grading target and grade before approval.
     *
     * @param array $arguments Tool arguments.
     * @return array Validated course module, assignment and user.
     */
    private function validate_target(array $arguments): array {
        global $CFG;
        require_once($CFG->dirroot . "/mod/assign/locallib.php");
        $this->resolve_context($arguments);
        [$course, $cm] = get_course_and_cm_from_cmid((int)$arguments["cmid"], "assign");
        $user = input_guard::record("user", (int)$arguments["userid"], "user_not_found");
        if ($user->deleted) {
            throw new api_exception("user_not_found", 404);
        }
        if (!is_enrolled(context_course::instance((int)$course->id), (int)$user->id)) {
            throw new api_exception("user_not_enrolled", 400);
        }
        $assignment = new assign(context_module::instance($cm->id), $cm, $course);
        $maxgrade = (float)$assignment->get_instance()->grade;
        $grade = (float)$arguments["grade"];
        if (!is_finite($grade) || $grade < -1 || ($maxgrade > 0 && $grade > $maxgrade)) {
            throw new api_exception("invalid_grade", 400);
        }
        return [$cm, $assignment, $user];
    }

    /**
     * Check all identifiers on the preview path; never issue approval for the wrong activity.
     *
     * @param array $arguments Tool arguments.
     * @param authenticated_identity $identity Authenticated Moodle user.
     * @return array
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        [$cm, $assignment, $user] = $this->validate_target($arguments);
        return ["operation" => $this->get_name(), "cmid" => (int)$cm->id,
            "assignmentid" => (int)$assignment->get_instance()->id, "userid" => (int)$user->id,
            "grade" => (float)$arguments["grade"]];
    }

    /**
     * Persist a grade only after revalidating the exact assignment and target user.
     *
     * @param array $arguments Tool arguments.
     * @param authenticated_identity $identity Authenticated Moodle user.
     * @return array
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        [$cm, $assignment, $user] = $this->validate_target($arguments);
        $grade = (object)[
            "grade" => (float)$arguments["grade"],
            "attemptnumber" => -1,
            "addattempt" => 0,
            "workflowstate" => "",
            "applytoall" => 0,
        ];
        $assignment->save_grade((int)$user->id, $grade, false);
        return ["graded" => true, "userid" => (int)$user->id, "grade" => (float)$arguments["grade"]];
    }
}
