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
    public function get_name(): string {
        return "assign_update_activity";
    }

    public function get_title(): string {
        return "Update assignment settings";
    }

    public function get_description(): string {
        return "Update an existing assignment name, instructions and dates; preserve all unrelated settings.";
    }

    public function get_required_capability(): string {
        return "moodle/course:manageactivities";
    }

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

    public function resolve_context(array $arguments): context {
        return context_module::instance((int)$arguments["cmid"], MUST_EXIST);
    }

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

    public function execute(array $arguments, authenticated_identity $identity): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/course/modlib.php");
        [$course, $cm] = get_course_and_cm_from_cmid((int)$arguments["cmid"], "assign");
        if (!has_capability("moodle/course:manageactivities",
                context_module::instance($cm->id), $identity->userid)) {
            throw new api_exception("permission_denied", 403);
        }
        [$modulecm, $context, $module, $data] = get_moduleinfo_data($cm, $course);
        $instance = $DB->get_record("assign", ["id" => $cm->instance], "*", MUST_EXIST);
        foreach (["name", "allowsubmissionsfromdate", "duedate", "cutoffdate",
                "gradingduedate"] as $field) {
            if (array_key_exists($field, $arguments)) {
                $data->{$field} = $field === "name"
                    ? clean_param($arguments[$field], PARAM_TEXT) : (int)$arguments[$field];
            }
        }
        if (array_key_exists("intro", $arguments)) {
            $data->introeditor["text"] = clean_text($arguments["intro"], FORMAT_HTML);
            $data->introeditor["format"] = FORMAT_HTML;
        }
        $open = (int)$data->allowsubmissionsfromdate;
        $due = (int)$data->duedate;
        $cutoff = (int)$data->cutoffdate;
        if (($open && $due && $due < $open) || ($cutoff && $due && $cutoff < $due) ||
                ($cutoff && $open && $cutoff < $open)) {
            throw new api_exception("invalid_assignment_dates", 400);
        }
        update_moduleinfo($modulecm, $data, $course);
        return ["updated" => true, "cmid" => (int)$cm->id,
            "instanceid" => (int)$instance->id, "changed_fields" => array_keys($arguments)];
    }
}
