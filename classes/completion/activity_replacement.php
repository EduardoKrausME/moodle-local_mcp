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
 * Safe activity criterion replacement.
 * @package local_mcp
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\completion;

use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;

/**
 * Replace equivalent view-only activity criteria, preserving existing historical evidence.
 */
final class activity_replacement {
    /** Hash all criteria and aggregation settings, including their stable IDs. */
    public static function fingerprint(int $courseid): string {
        global $DB;
        $criteria = $DB->get_records("course_completion_criteria", ["course" => $courseid], "id ASC");
        $aggregation = $DB->get_records("course_completion_aggr_methd", ["course" => $courseid], "id ASC");
        return hash("sha256", json_encode([$criteria, $aggregation]));
    }

    /** Validate recursively referenced completion conditions without disclosing user data. */
    private static function references($node, int $cmid): bool {
        if (!is_array($node)) {
            return false;
        }
        if (($node["type"] ?? null) === "completion" && (int)($node["cm"] ?? 0) === $cmid) {
            return true;
        }
        foreach ($node as $value) {
            if (is_array($value) && self::references($value, $cmid)) {
                return true;
            }
        }
        return false;
    }

    /** Check that the two activities use the supported native view-only rule. */
    private static function view_only(\stdClass $cm): bool {
        return (int)$cm->completion === COMPLETION_TRACKING_AUTOMATIC && (int)$cm->completionview === 1 &&
            $cm->completiongradeitemnumber === null && empty($cm->completionpassgrade);
    }

    /** Read-only migration plan; reject unsupported configurations or missing historical evidence. */
    public static function preview(array $a, authenticated_identity $identity): array {
        global $DB, $CFG;
        require_once($CFG->libdir . "/completionlib.php");
        $courseid = (int)$a["courseid"];
        $sourceid = (int)$a["sourcecmid"];
        $targetid = (int)$a["targetcmid"];
        $ctx = \context_course::instance($courseid, MUST_EXIST);
        require_capability("moodle/course:update", $ctx, $identity->userid);
        require_capability("moodle/course:manageactivities", $ctx, $identity->userid);
        if ($sourceid === $targetid || empty($a["preservehistory"]) ||
                !hash_equals(self::fingerprint($courseid), $a["expected_criteria_sha256"])) {
            throw new api_exception("completion_criteria_changed", 409);
        }
        $course = get_course($courseid);
        if (empty($CFG->enablecompletion) || empty($course->enablecompletion)) {
            throw new api_exception("completion_disabled", 409);
        }
        $source = $DB->get_record("course_modules", ["id" => $sourceid, "course" => $courseid], "*", MUST_EXIST);
        $target = $DB->get_record("course_modules", ["id" => $targetid, "course" => $courseid], "*", MUST_EXIST);
        foreach ([$source, $target] as $cm) {
            require_capability("moodle/course:manageactivities", \context_module::instance($cm->id), $identity->userid);
            if (!self::view_only($cm) || !empty($cm->deletioninprogress)) {
                throw new api_exception("completion_rules_not_equivalent", 409);
            }
        }
        $sourcemod = $DB->get_field("modules", "name", ["id" => $source->module], MUST_EXIST);
        $targetmod = $DB->get_field("modules", "name", ["id" => $target->module], MUST_EXIST);
        // Only known view-only modules; never infer equivalence for arbitrary custom rules.
        if ($sourcemod !== "page" || !in_array($targetmod, ["page", "mcp"], true)) {
            throw new api_exception("completion_rules_not_equivalent", 409);
        }
        $sourcecontent = $DB->get_record("page", ["id" => $source->instance], "*", MUST_EXIST);
        $targetcontent = null;
        if ($targetmod === "mcp") {
            $instance = $DB->get_record("mcp", ["id" => $target->instance], "*", MUST_EXIST);
            if (!empty($instance->completioninteractive)) {
                throw new api_exception("completion_target_interactive", 409, "", ["targetcmid" => $targetid]);
            }
            // mod_mcp only records views of published content.
            if (empty($instance->publishedrevision)) {
                throw new api_exception("completion_target_not_published", 409, "", ["targetcmid" => $targetid]);
            }
            $revision = $DB->get_record("mcp_revisions", ["id" => $instance->publishedrevision,
                "mcpid" => $instance->id], "id,content,sha256");
            if (!$revision) {
                throw new api_exception("completion_target_revision_missing", 409, "", [
                    "targetcmid" => $targetid, "revisionid" => (int)$instance->publishedrevision,
                ]);
            }
            if (!hash_equals((string)$revision->sha256, hash("sha256", $revision->content))) {
                throw new api_exception("completion_target_checksum_mismatch", 409, "", [
                    "targetcmid" => $targetid, "revisionid" => (int)$revision->id,
                ]);
            }
            if (trim($revision->content) === "") {
                throw new api_exception("completion_target_content_empty", 409, "", ["targetcmid" => $targetid]);
            }
            $targetcontent = ["instance" => (int)$instance->id, "revision" => (int)$revision->id, "sha256" => $revision->sha256];
        } else {
            $targetcontent = $DB->get_record("page", ["id" => $target->instance], "*", MUST_EXIST);
        }
        $section = $DB->get_record("course_sections", ["id" => $target->section, "course" => $courseid], "*", MUST_EXIST);
        $sourcesection = $DB->get_record("course_sections", ["id" => $source->section, "course" => $courseid],
            "*", MUST_EXIST);
        if (empty($target->visible)) {
            throw new api_exception("completion_target_hidden", 409, "", ["targetcmid" => $targetid]);
        }
        if (empty($section->visible)) {
            throw new api_exception("completion_target_section_hidden", 409, "", [
                "targetcmid" => $targetid, "sectionid" => (int)$section->id,
            ]);
        }
        // Organisation and legitimate conditional access do not change the view-only rule.
        $warnings = [];
        $conditions = [
            "completion_source_hidden" => empty($source->visible) || empty($sourcesection->visible),
            "completion_target_stealth" => empty($target->visibleoncoursepage),
            "completion_sections_differ" => (int)$source->section !== (int)$target->section,
            "completion_source_restricted" => !empty($source->availability) || !empty($sourcesection->availability),
            "completion_target_restricted" => !empty($target->availability) || !empty($section->availability),
            "completion_availability_differs" => (string)$source->availability !== (string)$target->availability ||
                (string)$sourcesection->availability !== (string)$section->availability,
        ];
        foreach ($conditions as $code => $applies) {
            if ($applies) {
                $warnings[] = ["code" => $code, "message" => get_string("apiwarning_" . $code, "local_mcp")];
            }
        }
        $criterion = $DB->get_record("course_completion_criteria", ["course" => $courseid,
            "criteriatype" => COMPLETION_CRITERIA_TYPE_ACTIVITY, "moduleinstance" => $sourceid], "*", MUST_EXIST);
        if ($criterion->module !== $sourcemod || $DB->record_exists("course_completion_criteria", ["course" => $courseid,
                "criteriatype" => COMPLETION_CRITERIA_TYPE_ACTIVITY, "moduleinstance" => $targetid])) {
            throw new api_exception("completion_criteria_changed", 409);
        }
        $dependencies = [];
        foreach (["course_modules", "course_sections"] as $table) {
            $rows = $DB->get_records($table, ["course" => $courseid], "id ASC", "id,availability");
            foreach ($rows as $row) {
                if (!empty($row->availability)) {
                    $tree = json_decode($row->availability, true);
                    $targetaccess = ($table === "course_modules" && (int)$row->id === $targetid) ||
                        ($table === "course_sections" && (int)$row->id === (int)$section->id);
                    if (!is_array($tree) || self::references($tree, $sourceid) ||
                            ($targetaccess && self::references($tree, $targetid))) {
                        $dependencies[] = ["table" => $table, "id" => (int)$row->id];
                    }
                }
            }
        }
        // Native activity-based badges can still depend on the old CMID.
        $badges = $DB->get_records_sql("SELECT DISTINCT b.id
            FROM {badge} b
            JOIN {badge_criteria} c ON c.badgeid = b.id
            JOIN {badge_criteria_param} p ON p.critid = c.id
            WHERE b.courseid = :course AND p.name = :paramname",
            ["course" => $courseid, "paramname" => "module_" . $sourceid]);
        foreach ($badges as $badge) {
            $dependencies[] = ["table" => "badge", "id" => (int)$badge->id];
        }
        // Native dependencies are blocking; custom storage cannot be inferred automatically.
        if ($dependencies) {
            throw new api_exception("completion_dependencies_present", 409, "", ["dependencies" => $dependencies],
                get_string("completiondependenciespresent", "local_mcp"));
        }
        // Report external dependency and concurrency limitations without a redundant input flag.
        $warnings[] = ["code" => "completion_custom_dependencies_unverified",
            "message" => get_string("apiwarning_completion_custom_dependencies_unverified", "local_mcp")];
        $params = ["source" => $sourceid, "criterion" => $criterion->id, "course" => $courseid,
            "complete" => COMPLETION_COMPLETE, "pass" => COMPLETION_COMPLETE_PASS];
        $missing = $DB->count_records_sql("SELECT COUNT(1) FROM {course_modules_completion} mc
            WHERE mc.coursemoduleid = :source AND mc.completionstate IN (:complete, :pass)
              AND NOT EXISTS (SELECT 1 FROM {course_completion_crit_compl} cc
                 WHERE cc.criteriaid = :criterion AND cc.course = :course
                   AND cc.userid = mc.userid AND cc.timecompleted > 0)", $params);
        if ($missing) {
            throw new api_exception("completion_history_pending", 409, "", ["pending" => $missing],
                get_string("completionhistorypending", "local_mcp"));
        }
        $plan = ["courseid" => $courseid, "sourcecmid" => $sourceid, "targetcmid" => $targetid,
            "criteriaid" => (int)$criterion->id, "targetmodule" => $targetmod,
            "criteria_sha256" => self::fingerprint($courseid),
            "source_completion_records" => $DB->count_records("course_modules_completion", ["coursemoduleid" => $sourceid]),
            "source_completed_students" => $DB->count_records_select("course_modules_completion",
                "coursemoduleid = :source AND completionstate IN (:complete, :pass)",
                ["source" => $sourceid, "complete" => COMPLETION_COMPLETE, "pass" => COMPLETION_COMPLETE_PASS]),
            "completed_criterion_records" => $DB->count_records_select("course_completion_crit_compl",
                "criteriaid = :criterion AND course = :course AND timecompleted > 0",
                ["criterion" => $criterion->id, "course" => $courseid]),
            "preserved_criterion_records" => $DB->count_records("course_completion_crit_compl",
                ["criteriaid" => $criterion->id, "course" => $courseid]),
            "dependencies" => [], "warnings" => $warnings, "preservehistory" => true,
            "operator_must_coordinate_quiescent_window" => true];
        // Bind the reviewed content and the current evidence counts to confirmation.
        // No student identifiers or authored content are returned by this fingerprint.
        $plan["replacement_sha256"] = hash("sha256", serialize([
            $plan, $source, $target, $sourcesection, $section, $sourcecontent, $targetcontent,
        ]));
        if (isset($a["expected_replacement_sha256"]) &&
                !hash_equals($plan["replacement_sha256"], $a["expected_replacement_sha256"])) {
            throw new api_exception("completion_criteria_changed", 409);
        }
        return $plan;
    }

    /** Update only the existing native criterion, never delete or manufacture student evidence. */
    public static function execute(array $a, authenticated_identity $identity): array {
        global $DB;
        $lock = \core\lock\lock_config::get_lock_factory("local_mcp")->get_lock("completion_replace_" . $a["courseid"], 5);
        if (!$lock) {
            throw new api_exception("completion_busy", 409);
        }
        try {
            $transaction = $DB->start_delegated_transaction();
            try {
                $plan = self::preview($a, $identity);
                $record = $DB->get_record("course_completion_criteria", ["id" => $plan["criteriaid"]], "*", MUST_EXIST);
                $criterion = \completion_criteria::factory((array)$record);
                $criterion->module = $plan["targetmodule"];
                $criterion->moduleinstance = $plan["targetcmid"];
                if (!$criterion->update()) {
                    throw new api_exception("completion_replacement_failed", 409);
                }
                $verified = $DB->get_record("course_completion_criteria", ["id" => $plan["criteriaid"]], "*", MUST_EXIST);
                if ((int)$verified->moduleinstance !== $plan["targetcmid"] || $verified->module !== $plan["targetmodule"]) {
                    throw new api_exception("completion_replacement_failed", 409);
                }
                \cache::make("core", "completion")->purge();
                rebuild_course_cache($plan["courseid"], true);
                $transaction->allow_commit();
            } catch (\Throwable $e) {
                $transaction->rollback($e);
            }
            return $plan + ["updated" => true, "new_criteria_sha256" => self::fingerprint($plan["courseid"])];
        } finally {
            $lock->release();
        }
    }
}
