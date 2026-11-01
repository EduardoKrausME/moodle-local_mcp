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
 * Update a Moodle assignment using the course-module lifecycle API.
 *
 * @package   mcptool_assign
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_assign\write;

use context;
use context_module;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\write\tool\base_tool;

/**
 * Update selected assignment metadata, preserving other existing settings.
 */
final class update_activity extends base_tool {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string {
        return "assign_update_activity";
    }

    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string {
        return "Update assignment settings";
    }

    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string {
        return "Update an existing assignment name, instructions and dates; preserve all unrelated settings.";
    }

    /**
     * Method get_required_capability.
     *
     * @return string Return value.
     */
    public function get_required_capability(): string {
        return "moodle/course:manageactivities";
    }

    /**
     * Method get_input_schema.
     *
     * @return array Return value.
     */
    public function get_input_schema(): array {
        return $this->object_schema([
            "cmid" => ["type" => "integer", "minimum" => 1],
            "name" => ["type" => "string", "minLength" => 1, "maxLength" => 255],
            "intro" => ["type" => "string"],
            "allowsubmissionsfromdate" => ["type" => "integer", "minimum" => 0],
            "duedate" => ["type" => "integer", "minimum" => 0],
            "cutoffdate" => ["type" => "integer", "minimum" => 0],
            "gradingduedate" => ["type" => "integer", "minimum" => 0],
        ], ["cmid"]);
    }

    /**
     * Method resolve_context.
     *
     * @param array $arguments Parameter arguments.
     * @return context Return value.
     */
    public function resolve_context(array $arguments): context {
        return context_module::instance((int)$arguments["cmid"], MUST_EXIST);
    }

    /**
     * Method supports_dry_run.
     *
     * @return bool Return value.
     */
    public function supports_dry_run(): bool {
        return true;
    }

    /**
     * @return array Previously configured fields and proposed modifications.
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        global $DB;
        [$course, $cm] = get_course_and_cm_from_cmid((int)$arguments["cmid"], "assign");
        if (!has_capability("moodle/course:manageactivities",
                context_module::instance($cm->id), $identity->userid)) {
            throw new api_exception("permission_denied", 403);
        }
        $instance = $DB->get_record("assign", ["id" => $cm->instance], "*", MUST_EXIST);
        return [
            "operation" => $this->get_name(), "cmid" => (int)$cm->id,
            "current" => ["name" => $instance->name,
                "allowsubmissionsfromdate" => (int)$instance->allowsubmissionsfromdate,
                "duedate" => (int)$instance->duedate, "cutoffdate" => (int)$instance->cutoffdate],
            "changes" => array_diff_key($arguments, ["cmid" => true]),
        ];
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
        require_once($CFG->dirroot . "/mod/assign/locallib.php");
        [$course, $cm] = get_course_and_cm_from_cmid((int)$arguments["cmid"], "assign");
        $context = context_module::instance($cm->id);
        if (!has_capability("moodle/course:manageactivities", $context, $identity->userid)) {
            throw new api_exception("permission_denied", 403);
        }
        $instance = $DB->get_record("assign", ["id" => $cm->instance], "*", MUST_EXIST);
        $allowed = ["name", "intro", "allowsubmissionsfromdate",
            "duedate", "cutoffdate", "gradingduedate"];
        $change = (object)["id" => $instance->id, "timemodified" => time()];
        $changedfields = [];
        foreach ($allowed as $field) {
            if (!array_key_exists($field, $arguments)) {
                continue;
            }
            $change->{$field} = $field === "name"
                ? clean_param($arguments[$field], PARAM_TEXT)
                : ($field === "intro" ? clean_text($arguments[$field], FORMAT_HTML)
                    : (int)$arguments[$field]);
            $changedfields[] = $field;
        }
        if (!$changedfields) {
            throw new api_exception("no_fields_to_update", 400);
        }
        if (in_array("intro", $changedfields, true)) {
            $change->introformat = FORMAT_HTML;
        }
        $open = (int)($change->allowsubmissionsfromdate ?? $instance->allowsubmissionsfromdate);
        $due = (int)($change->duedate ?? $instance->duedate);
        $cutoff = (int)($change->cutoffdate ?? $instance->cutoffdate);
        if (($open && $due && $due < $open) || ($cutoff && $due && $cutoff < $due) ||
                ($cutoff && $open && $cutoff < $open)) {
            throw new api_exception("invalid_assignment_dates", 400);
        }
        // Only modify explicitly chosen columns. Passing an incomplete form to Moodle's
        // update_moduleinfo() would disable unrelated submission and feedback plugins.
        $DB->update_record("assign", $change);
        $assign = new \assign($context, $cm, $course);
        $assign->update_calendar($cm->id);
        $assign->update_gradebook(false, $cm->id);
        \core_completion\api::update_completion_date_event($cm->id, "assign",
            $DB->get_record("assign", ["id" => $cm->instance], "*", MUST_EXIST),
            (int)$cm->completionexpected);
        rebuild_course_cache($course->id, true);
        \core\event\course_module_updated::create_from_cm($cm)->trigger();
        return ["updated" => true, "cmid" => (int)$cm->id,
            "instanceid" => (int)$instance->id, "changed_fields" => $changedfields];
    }
}
