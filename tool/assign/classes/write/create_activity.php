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
    /**
     * Method module_name.
     *
     * @return string Return value.
     */
    protected function module_name(): string {
        return "assign";
    }

    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string {
        return "Create assignment activity";
    }

    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string {
        return "Create a Moodle assignment with submission plugins, dates, grade and optional marking workflow.";
    }

    /**
     * Method extra_properties.
     *
     * @return array Return value.
     */
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
            "file_enabled" => ["type" => "boolean"],
            "onlinetext_enabled" => ["type" => "boolean"],
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
        $this->validate_assignment_options($a);
        $this->check_duplicate_assignment($a);
        // Core add_moduleinfo() copies introeditor over intro when that draft exists.
        $info->introeditor["text"] = $info->intro;
        $info->introeditor["format"] = FORMAT_HTML;
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
        $info->assignsubmission_onlinetext_enabled = $this->online_text_enabled($a) ? 1 : 0;
        $info->assignsubmission_file_enabled = $this->file_submissions_enabled($a) ? 1 : 0;
        $info->assignsubmission_file_maxfiles = (int)($a["maxfiles"] ?? 5);
        $info->assignsubmission_file_maxsizebytes = (int)($a["maxbytes"] ?? 0);
        $info->assignfeedback_comments_enabled = 1;
    }


    /**
     * @param array $arguments Assignment options.
     * @return bool Whether file submissions are enabled.
     */
    private function file_submissions_enabled(array $arguments): bool {
        return (bool)($arguments["file_submissions"] ?? $arguments["file_enabled"] ?? true);
    }

    /**
     * Preserve both the subplugin options and legacy input defaults.
     *
     * @param array $arguments Assignment options.
     * @return bool Whether online text submissions are enabled.
     */
    private function online_text_enabled(array $arguments): bool {
        return (bool)($arguments["onlinetext"] ?? $arguments["onlinetext_enabled"] ??
            (array_key_exists("file_enabled", $arguments) ? false : true));
    }

    /**
     * Check dates and submission methods during both preview and execution.
     *
     * @param array $arguments Assignment options.
     */
    private function validate_assignment_options(array $arguments): void {
        $open = (int)($arguments["allowsubmissionsfromdate"] ?? 0);
        $due = (int)($arguments["duedate"] ?? 0);
        $cutoff = (int)($arguments["cutoffdate"] ?? 0);
        if (($open && $due && $due < $open) || ($cutoff && $due && $cutoff < $due) ||
                ($cutoff && $open && $cutoff < $open)) {
            throw new api_exception("invalid_assignment_dates", 400);
        }
        if (!$this->file_submissions_enabled($arguments) && !$this->online_text_enabled($arguments)) {
            throw new api_exception("no_submission_method", 400);
        }
    }

    /**
     * Avoid creating the same assignment twice when the client retries a request.
     *
     * @param array $arguments Assignment options.
     */
    private function check_duplicate_assignment(array $arguments): void {
        $name = clean_param($arguments["name"] ?? "", PARAM_TEXT);
        foreach (get_fast_modinfo((int)$arguments["courseid"])->get_cms() as $cm) {
            if ($cm->modname === "assign" && (int)$cm->sectionnum === (int)$arguments["sectionnum"] &&
                    $cm->name === $name) {
                throw new api_exception("assignment_already_exists", 409);
            }
        }
    }

    /**
     * Method preview.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        $preview = parent::preview($arguments, $identity);
        $this->validate_assignment_options($arguments);
        $this->check_duplicate_assignment($arguments);
        $preview["file_enabled"] = $this->file_submissions_enabled($arguments);
        $preview["onlinetext_enabled"] = $this->online_text_enabled($arguments);
        $preview["dates"] = [
            "opens" => (int)($arguments["allowsubmissionsfromdate"] ?? 0),
            "due" => (int)($arguments["duedate"] ?? 0),
            "cutoff" => (int)($arguments["cutoffdate"] ?? 0),
        ];
        $preview["grade"] = (float)($arguments["grade"] ?? 100);
        return $preview;
    }
}
