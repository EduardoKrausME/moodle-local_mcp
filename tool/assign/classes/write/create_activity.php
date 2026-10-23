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
 * Create a Moodle assignment through course-module lifecycle APIs.
 *
 * @package   mcptool_assign
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_assign\write;

use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\write\tool\activity_create_base;
use stdClass;

/**
 * Create individual assignments with supported submission and feedback plugins.
 */
final class create_activity extends activity_create_base {
    protected function module_name(): string {
        return "assign";
    }

    public function get_title(): string {
        return "Create assignment activity";
    }

    public function get_description(): string {
        return "Create a Moodle assignment with submission plugins, dates, grade and optional marking workflow.";
    }

    protected function extra_properties(): array {
        return [
            "allowsubmissionsfromdate" => ["type" => "integer", "minimum" => 0],
            "duedate" => ["type" => "integer", "minimum" => 0],
            "cutoffdate" => ["type" => "integer", "minimum" => 0],
            "gradingduedate" => ["type" => "integer", "minimum" => 0],
            "grade" => ["type" => "number", "minimum" => 0, "maximum" => 1000],
            "submissiondrafts" => ["type" => "boolean"],
            "maxfiles" => ["type" => "integer", "minimum" => 0, "maximum" => 20],
            "maxbytes" => ["type" => "integer", "minimum" => 0],
            "onlinetext" => ["type" => "boolean"],
            "file_submissions" => ["type" => "boolean"],
            "markingworkflow" => ["type" => "boolean"],
        ];
    }

    /**
     * @param stdClass $info Module form data.
     * @param array $a User inputs.
     * @param authenticated_identity $identity Authenticated user.
     */
    protected function configure(stdClass $info, array $a, authenticated_identity $identity): void {
        global $CFG;
        require_once($CFG->dirroot . "/mod/assign/locallib.php");
        $open = (int)($a["allowsubmissionsfromdate"] ?? 0);
        $due = (int)($a["duedate"] ?? 0);
        $cutoff = (int)($a["cutoffdate"] ?? 0);
        if (($open && $due && $due < $open) || ($cutoff && $due && $cutoff < $due) ||
                ($cutoff && $open && $cutoff < $open)) {
            throw new api_exception("invalid_assignment_dates", 400);
        }
        if (empty($a["onlinetext"]) && array_key_exists("onlinetext", $a) &&
                empty($a["file_submissions"]) && array_key_exists("file_submissions", $a)) {
            throw new api_exception("no_submission_method", 400);
        }
        $info->allowsubmissionsfromdate = $open;
        $info->duedate = $due;
        $info->cutoffdate = $cutoff;
        $info->gradingduedate = (int)($a["gradingduedate"] ?? 0);
        $info->grade = (float)($a["grade"] ?? $CFG->gradepointdefault);
        $info->submissiondrafts = !empty($a["submissiondrafts"]) ? 1 : 0;
        $info->requiresubmissionstatement = 0;
        $info->sendnotifications = 0;
        $info->sendlatenotifications = 0;
        $info->teamsubmission = 0;
        $info->requireallteammemberssubmit = 0;
        $info->blindmarking = 0;
        $info->markingworkflow = !empty($a["markingworkflow"]) ? 1 : 0;
        $info->markingallocation = 0;
        $info->markinganonymous = 0;
        $info->maxattempts = 1;
        $info->attemptreopenmethod = ASSIGN_ATTEMPT_REOPEN_METHOD_NONE;
        $info->assignsubmission_onlinetext_enabled = ($a["onlinetext"] ?? true) ? 1 : 0;
        $info->assignsubmission_file_enabled = ($a["file_submissions"] ?? true) ? 1 : 0;
        $info->assignsubmission_file_maxfiles = (int)($a["maxfiles"] ?? 5);
        $info->assignsubmission_file_maxsizebytes = (int)($a["maxbytes"] ?? 0);
        $info->assignfeedback_comments_enabled = 1;
    }

    public function preview(array $arguments, authenticated_identity $identity): array {
        $preview = parent::preview($arguments, $identity);
        $preview["dates"] = [
            "opens" => (int)($arguments["allowsubmissionsfromdate"] ?? 0),
            "due" => (int)($arguments["duedate"] ?? 0),
            "cutoff" => (int)($arguments["cutoffdate"] ?? 0),
        ];
        $preview["grade"] = (float)($arguments["grade"] ?? 100);
        return $preview;
    }
}
