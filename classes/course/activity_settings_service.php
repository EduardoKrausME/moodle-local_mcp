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
 * Activity availability, group-mode and completion configuration service.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\course;

use context_module;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;

/**
 * Update course-module settings without resubmitting incomplete module configuration forms.
 */
final class activity_settings_service {
    /**
     * @return array Course, raw course-module record, module context.
     */
    private static function target(int $cmid, authenticated_identity $identity): array {
        global $DB;
        [$course, $cm] = get_course_and_cm_from_cmid($cmid);
        $context = context_module::instance($cmid);
        if (!has_capability("moodle/course:manageactivities", $context, $identity->userid)) {
            throw new api_exception("permission_denied", 403);
        }
        $record = $DB->get_record("course_modules", ["id" => $cmid], "*", MUST_EXIST);
        return [$course, $record, $context];
    }

    /**
     * @return array Current Moodle JSON access restriction and optimistic concurrency hash.
     */
    public static function restrictions(array $a, authenticated_identity $identity): array {
        [$course, $cm] = self::target((int)$a["cmid"], $identity);
        return ["cmid" => (int)$cm->id, "courseid" => (int)$course->id,
            "availability" => $cm->availability === null ? null : json_decode($cm->availability, true),
            "availability_sha256" => hash("sha256", (string)$cm->availability),
            "groupmode" => (int)$cm->groupmode, "groupingid" => (int)$cm->groupingid];
    }

    /**
     * Validate a bounded set of supported Moodle availability conditions.
     *
     * @return array Proposed JSON and a human-readable summary.
     */
    public static function restrictions_preview(array $a, authenticated_identity $identity): array {
        global $CFG, $DB;
        [$course, $cm] = self::target((int)$a["cmid"], $identity);
        if (empty($CFG->enableavailability)) {
            throw new api_exception("availability_disabled", 409);
        }
        if (!hash_equals(hash("sha256", (string)$cm->availability), (string)$a["expected_availability_sha256"])) {
            throw new api_exception("availability_changed", 409);
        }
        $mode = (string)$a["mode"];
        if ($mode === "clear") {
            return ["cmid" => (int)$cm->id, "previous" => $cm->availability,
                "new_json" => null, "conditions" => [], "mode" => "clear"];
        }
        $input = $a["conditions"] ?? [];
        if (!$input || count($input) > 20) {
            throw new api_exception("invalid_availability_conditions", 400);
        }
        $children = [];
        $summary = [];
        $enabled = \core_plugin_manager::instance()->get_enabled_plugins("availability");
        foreach ($input as $condition) {
            $type = (string)($condition["type"] ?? "");
            if (!in_array($type, ["date", "group", "completion", "grade"], true) ||
                    empty($enabled[$type])) {
                throw new api_exception("availability_condition_unavailable", 409);
            }
            if ($type === "date") {
                $direction = (string)($condition["direction"] ?? "");
                if (!in_array($direction, ["from", "until"], true) ||
                        empty($condition["timestamp"])) {
                    throw new api_exception("invalid_date_condition", 400);
                }
                $d = $direction === "from" ? ">=" : "<";
                $children[] = \availability_date\condition::get_json($d, (int)$condition["timestamp"]);
                $summary[] = ["type" => $type, "direction" => $direction,
                    "timestamp" => (int)$condition["timestamp"]];
            } else if ($type === "group") {
                $groupid = (int)($condition["groupid"] ?? 0);
                if (!$DB->record_exists("groups", ["id" => $groupid, "courseid" => $course->id])) {
                    throw new api_exception("group_not_in_course", 400);
                }
                $children[] = (object)["type" => "group", "id" => $groupid];
                $summary[] = ["type" => $type, "groupid" => $groupid];
            } else if ($type === "completion") {
                $requirescm = (int)($condition["cmid"] ?? 0);
                $state = (string)($condition["state"] ?? "");
                $states = ["complete" => COMPLETION_COMPLETE,
                    "incomplete" => COMPLETION_INCOMPLETE,
                    "passed" => COMPLETION_COMPLETE_PASS, "failed" => COMPLETION_COMPLETE_FAIL];
                if ($requirescm === (int)$cm->id || !isset($states[$state])) {
                    throw new api_exception("invalid_completion_condition", 400);
                }
                $other = $DB->get_record("course_modules", ["id" => $requirescm, "course" => $course->id],
                    "id,completion,deletioninprogress");
                if (!$other || empty($other->completion) || !empty($other->deletioninprogress) ||
                        empty($course->enablecompletion) || empty($CFG->enablecompletion)) {
                    throw new api_exception("completion_condition_unavailable", 409);
                }
                $children[] = \availability_completion\condition::get_json($requirescm, $states[$state]);
                $summary[] = ["type" => $type, "cmid" => $requirescm, "state" => $state];
            } else {
                $gradeid = (int)($condition["gradeitemid"] ?? 0);
                $grade = $DB->get_record("grade_items", ["id" => $gradeid, "courseid" => $course->id],
                    "id,itemmodule,iteminstance,itemtype");
                if (!$grade || ($grade->itemtype === "mod" &&
                        (int)$grade->iteminstance === (int)$cm->instance &&
                        $DB->get_field("modules", "name", ["id" => $cm->module]) === $grade->itemmodule)) {
                    throw new api_exception("grade_condition_unavailable", 409);
                }
                $min = array_key_exists("min", $condition) ? (float)$condition["min"] : null;
                $max = array_key_exists("max", $condition) ? (float)$condition["max"] : null;
                if (($min === null && $max === null) ||
                        ($min !== null && ($min < 0 || $min > 100)) ||
                        ($max !== null && ($max < 0 || $max > 100)) ||
                        ($min !== null && $max !== null && $min >= $max)) {
                    throw new api_exception("invalid_grade_condition", 400);
                }
                $children[] = \availability_grade\condition::get_json($gradeid, $min, $max);
                $summary[] = ["type" => $type, "gradeitemid" => $gradeid,
                    "min" => $min, "max" => $max];
            }
        }
        $jsonobj = \core_availability\tree::get_root_json($children, "&", true);
        $tree = new \core_availability\tree($jsonobj);
        if ($tree->is_empty()) {
            throw new api_exception("empty_availability_tree", 400);
        }
        $json = json_encode($tree->save(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return ["cmid" => (int)$cm->id, "previous" => $cm->availability,
            "new_json" => $json, "conditions" => $summary, "mode" => "replace"];
    }

    /**
     * @return array Change only the availability field and invalidate Moodle cache.
     */
    public static function set_restrictions(array $a, authenticated_identity $identity): array {
        global $DB;
        $preview = self::restrictions_preview($a, $identity);
        $cmid = (int)$preview["cmid"];
        $DB->set_field("course_modules", "availability", $preview["new_json"], ["id" => $cmid]);
        $courseid = (int)$DB->get_field("course_modules", "course", ["id" => $cmid], MUST_EXIST);
        rebuild_course_cache($courseid, true);
        return ["updated" => true, "cmid" => $cmid, "mode" => $preview["mode"],
            "availability_sha256" => hash("sha256", (string)$preview["new_json"])];
    }

    /**
     * @return array Grouping mode preflight.
     */
    public static function groupmode_preview(array $a, authenticated_identity $identity): array {
        [$course, $cm] = self::target((int)$a["cmid"], $identity);
        if (!empty($course->groupmodeforce)) {
            throw new api_exception("course_forces_groupmode", 409);
        }
        if ((int)$cm->groupmode !== (int)$a["expected_groupmode"]) {
            throw new api_exception("groupmode_changed", 409);
        }
        return ["cmid" => (int)$cm->id, "old_groupmode" => (int)$cm->groupmode,
            "new_groupmode" => (int)$a["groupmode"]];
    }

    /**
     * @return array Update per-activity group mode using core course API.
     */
    public static function set_groupmode(array $a, authenticated_identity $identity): array {
        global $CFG;
        $preview = self::groupmode_preview($a, $identity);
        require_once($CFG->dirroot . "/course/lib.php");
        $updated = set_coursemodule_groupmode($preview["cmid"], $preview["new_groupmode"]);
        return ["cmid" => $preview["cmid"], "changed" => (bool)$updated,
            "groupmode" => $preview["new_groupmode"]];
    }

    /**
     * @return array Current per-activity completion rules.
     */
    public static function completion(array $a, authenticated_identity $identity): array {
        global $CFG, $DB;
        [$course, $cm] = self::target((int)$a["cmid"], $identity);
        $modname = (string)$DB->get_field("modules", "name", ["id" => $cm->module], MUST_EXIST);
        return ["cmid" => (int)$cm->id, "courseid" => (int)$course->id,
            "enabled_for_course" => !empty($CFG->enablecompletion) && !empty($course->enablecompletion),
            "completion" => (int)$cm->completion,
            "completionview" => (int)$cm->completionview,
            "completiongradeitemnumber" => $cm->completiongradeitemnumber === null
                ? null : (int)$cm->completiongradeitemnumber,
            "completionpassgrade" => (int)$cm->completionpassgrade,
            "existing_student_records" => $DB->count_records("course_modules_completion",
                ["coursemoduleid" => $cm->id]), "modname" => $modname];
    }

    /**
     * @return array Safe change proposal without rewriting student completion history.
     */
    public static function completion_preview(array $a, authenticated_identity $identity): array {
        global $DB, $CFG;
        [$course, $cm] = self::target((int)$a["cmid"], $identity);
        if (empty($CFG->enablecompletion) || empty($course->enablecompletion)) {
            throw new api_exception("completion_disabled", 409);
        }
        if ((int)$cm->completion !== (int)$a["expected_completion"]) {
            throw new api_exception("completion_settings_changed", 409);
        }
        if ($DB->record_exists("course_modules_completion", ["coursemoduleid" => $cm->id])) {
            throw new api_exception("completion_history_locked", 409);
        }
        $mode = (int)$a["completion"];
        $view = $mode === COMPLETION_TRACKING_AUTOMATIC && !empty($a["requireview"]) ? 1 : 0;
        $grade = $mode === COMPLETION_TRACKING_AUTOMATIC && !empty($a["requiregrade"]);
        $number = null;
        if ($grade) {
            $modname = $DB->get_field("modules", "name", ["id" => $cm->module], MUST_EXIST);
            $item = $DB->get_record("grade_items", ["courseid" => $course->id,
                "itemtype" => "mod", "itemmodule" => $modname,
                "iteminstance" => $cm->instance, "itemnumber" => 0], "id");
            if (!$item) {
                throw new api_exception("completion_gradeitem_unavailable", 409);
            }
            $number = 0;
        }
        if ($mode === COMPLETION_TRACKING_AUTOMATIC && !$view && !$grade) {
            throw new api_exception("automatic_completion_needs_rule", 400);
        }
        return ["cmid" => (int)$cm->id, "before" => [
            "completion" => (int)$cm->completion, "completionview" => (int)$cm->completionview,
            "completiongradeitemnumber" => $cm->completiongradeitemnumber],
            "after" => ["completion" => $mode, "completionview" => $view,
                "completiongradeitemnumber" => $number, "completionpassgrade" => 0]];
    }

    /**
     * @return array Write supported Moodle completion flags while preserving other module fields.
     */
    public static function set_completion(array $a, authenticated_identity $identity): array {
        global $DB;
        $preview = self::completion_preview($a, $identity);
        $id = $preview["cmid"];
        $flags = $preview["after"];
        $DB->update_record("course_modules", (object)[
            "id" => $id,
            "completion" => $flags["completion"], "completionview" => $flags["completionview"],
            "completiongradeitemnumber" => $flags["completiongradeitemnumber"],
            "completionpassgrade" => $flags["completionpassgrade"],
        ]);
        $courseid = (int)$DB->get_field("course_modules", "course", ["id" => $id], MUST_EXIST);
        rebuild_course_cache($courseid, true);
        \cache::make("core", "completion")->purge();
        return ["updated" => true, "cmid" => $id, "completion" => $flags["completion"],
            "completionview" => $flags["completionview"],
            "completiongradeitemnumber" => $flags["completiongradeitemnumber"]];
    }
}
