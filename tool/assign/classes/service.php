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
 * Assignment administration and assessment domain service.
 *
 * @package   mcptool_assign
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_assign;

use assign;
use context_course;
use context_module;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\security\untrusted_content;

/**
 * Delegate mutations to Moodle assignment services; never bypass Moodle gradebook or file APIs.
 */
final class service {
    /**
     * @return array{0: assign, 1: object}
     */
    public static function assignment(int $cmid, authenticated_identity $identity): array {
        global $CFG;
        require_once($CFG->dirroot . "/mod/assign/locallib.php");
        [$course, $cm] = get_course_and_cm_from_cmid($cmid, "assign");
        $context = context_module::instance($cm->id);
        if (!has_capability("mod/assign:grade", $context, $identity->userid) ||
                !get_fast_modinfo($course, $identity->userid)->get_cm($cm->id)->uservisible) {
            throw new api_exception("permission_denied", 403);
        }
        return [new assign($context, $cm, $course), $cm];
    }

    /**
     * Enforce student, group and anonymous-marking restrictions, even for read tools.
     *
     * @return assign
     */
    public static function student(int $cmid, int $userid, authenticated_identity $identity): assign {
        global $DB, $CFG;
        [$assign, $cm] = self::assignment($cmid, $identity);
        $context = $assign->get_context();
        $instance = $assign->get_instance();
        if (!$DB->record_exists("user", ["id" => $userid, "deleted" => 0]) ||
                !is_enrolled($context, $userid, "", true)) {
            throw new api_exception("student_not_found", 404);
        }
        if (!empty($instance->blindmarking) && empty($instance->revealidentities)) {
            throw new api_exception("blind_marking_unsupported", 409);
        }
        if (!empty($instance->teamsubmission)) {
            throw new api_exception("team_submission_unsupported", 409);
        }
        require_once($CFG->libdir . "/grouplib.php");
        if (groups_get_activity_groupmode($cm) === SEPARATEGROUPS &&
                !has_capability("moodle/site:accessallgroups", $context, $identity->userid)) {
            $groups = groups_get_activity_allowed_groups($cm, $identity->userid);
            $member = false;
            foreach (array_keys($groups) as $groupid) {
                if (groups_is_member((int)$groupid, $userid)) {
                    $member = true;
                    break;
                }
            }
            if (!$member) {
                throw new api_exception("permission_denied", 403);
            }
        }
        return $assign;
    }

    /**
     * @return array Assignment attempt history without unverified attachment bodies.
     */
    public static function history(array $a, authenticated_identity $identity): array {
        global $DB;
        $assign = self::student((int)$a["cmid"], (int)$a["userid"], $identity);
        $records = $DB->get_records("assign_submission",
            ["assignment" => $assign->get_instance()->id, "userid" => (int)$a["userid"], "groupid" => 0],
            "attemptnumber ASC, id ASC");
        $items = [];
        foreach ($records as $r) {
            $grade = $assign->get_user_grade((int)$a["userid"], false, (int)$r->attemptnumber);
            $items[] = [
                "submissionid" => (int)$r->id, "attemptnumber" => (int)$r->attemptnumber,
                "status" => $r->status, "latest" => (bool)$r->latest,
                "timecreated" => (int)$r->timecreated, "timemodified" => (int)$r->timemodified,
                "grade" => $grade && (float)$grade->grade >= 0 ? (float)$grade->grade : null,
            ];
        }
        return ["cmid" => (int)$a["cmid"], "userid" => (int)$a["userid"], "attempts" => $items];
    }

    /**
     * @return array Paginated submissions and grade state for permitted students.
     */
    public static function progress(array $a, authenticated_identity $identity): array {
        global $DB;
        [$assign, $cm] = self::assignment((int)$a["cmid"], $identity);
        $instance = $assign->get_instance();
        if ((!empty($instance->blindmarking) && empty($instance->revealidentities)) ||
                !empty($instance->teamsubmission)) {
            return ["unsupported" => "anonymous_or_team_submissions", "submissions" => []];
        }
        $limit = max(1, min(50, (int)($a["limit"] ?? 20)));
        $offset = max(0, (int)($a["offset"] ?? 0));
        $records = $DB->get_records("assign_submission",
            ["assignment" => $instance->id, "latest" => 1, "groupid" => 0],
            "timemodified DESC, id DESC", "*", $offset, $limit);
        $rows = [];
        foreach ($records as $r) {
            try {
                self::student((int)$a["cmid"], (int)$r->userid, $identity);
            } catch (api_exception $exception) {
                continue;
            }
            $grade = $assign->get_user_grade((int)$r->userid, false);
            $flags = $assign->get_user_flags((int)$r->userid, false);
            $rows[] = [
                "userid" => (int)$r->userid, "status" => $r->status,
                "attemptnumber" => (int)$r->attemptnumber,
                "submittedat" => (int)$r->timemodified,
                "late" => (int)$instance->duedate > 0 && (int)$r->timemodified > (int)$instance->duedate,
                "grade" => $grade && (float)$grade->grade >= 0 ? (float)$grade->grade : null,
                "workflowstate" => $flags ? (string)$flags->workflowstate : "",
                "extensionduedate" => $flags ? (int)$flags->extensionduedate : 0,
            ];
        }
        return ["cmid" => (int)$a["cmid"], "submissions" => $rows,
            "offset" => $offset, "next_offset" => $offset + count($records), "limit" => $limit];
    }

    /**
     * @return array Extension dates for an authorized student.
     */
    public static function extensions(array $a, authenticated_identity $identity): array {
        $assign = self::student((int)$a["cmid"], (int)$a["userid"], $identity);
        $flags = $assign->get_user_flags((int)$a["userid"], false);
        return ["cmid" => (int)$a["cmid"], "userid" => (int)$a["userid"],
            "duedate" => (int)$assign->get_instance()->duedate,
            "extensionduedate" => $flags ? (int)$flags->extensionduedate : 0];
    }

    /**
     * @return array Rubric or marking guide definitions; never silently select an older version.
     */
    public static function guide(array $a, authenticated_identity $identity): array {
        global $DB;
        [$assign, $cm] = self::assignment((int)$a["cmid"], $identity);
        $area = $DB->get_record("grading_areas", [
            "contextid" => $assign->get_context()->id, "component" => "mod_assign",
            "areaname" => "submissions",
        ]);
        if (!$area || !$area->activemethod) {
            return ["method" => "simple", "criteria" => []];
        }
        $method = $area->activemethod;
        if (!in_array($method, ["rubric", "guide"], true)) {
            return ["method" => $method, "criteria" => []];
        }
        $defs = $DB->get_records("grading_definitions", ["areaid" => $area->id],
            "id DESC", "*", 0, 1);
        if (!$defs) {
            return ["method" => $method, "criteria" => []];
        }
        $def = reset($defs);
        $criteria = $DB->get_records("gradingform_" . $method . "_criteria",
            ["definitionid" => $def->id], "sortorder ASC, id ASC");
        $rows = [];
        foreach ($criteria as $criterion) {
            $row = ["id" => (int)$criterion->id,
                "description" => untrusted_content::text($criterion->description)];
            if ($method === "guide") {
                $row["shortname"] = untrusted_content::text($criterion->shortname);
                $row["maxscore"] = (float)$criterion->maxscore;
            } else {
                $levels = $DB->get_records("gradingform_rubric_levels",
                    ["criterionid" => $criterion->id], "score ASC, id ASC");
                $row["levels"] = [];
                foreach ($levels as $level) {
                    $row["levels"][] = ["id" => (int)$level->id, "score" => (float)$level->score,
                        "description" => untrusted_content::text($level->definition)];
                }
            }
            $rows[] = $row;
        }
        return ["cmid" => (int)$a["cmid"], "method" => $method,
            "definitionid" => (int)$def->id, "criteria" => $rows];
    }

    /**
     * Extract bounded UTF-8 text from an authorized attachment.
     *
     * @return array Metadata and untrusted text for text/Office files.
     */
    public static function document(array $a, authenticated_identity $identity): array {
        global $CFG;
        $assign = self::student((int)$a["cmid"], (int)$a["userid"], $identity);
        $submission = $assign->get_user_submission((int)$a["userid"], false);
        if (!$submission) {
            throw new api_exception("submission_not_found", 404);
        }
        $plugin = $assign->get_submission_plugin_by_type("file");
        if (!$plugin || !$plugin->is_enabled()) {
            throw new api_exception("submission_files_disabled", 409);
        }
        $file = get_file_storage()->get_file_by_id((int)$a["fileid"]);
        if (!$file || $file->is_directory() ||
                (int)$file->get_contextid() !== (int)$assign->get_context()->id ||
                $file->get_component() !== "assignsubmission_file" ||
                $file->get_filearea() !== "submission_files" ||
                (int)$file->get_itemid() !== (int)$submission->id) {
            throw new api_exception("submission_file_not_found", 404);
        }
        if ($file->get_filesize() > 5000000) {
            throw new api_exception("document_too_large", 413);
        }
        $mime = $file->get_mimetype();
        $max = max(1000, min(100000, (int)($a["maxchars"] ?? 20000)));
        if (in_array($mime, ["text/plain", "text/markdown", "text/csv"], true)) {
            $raw = $file->get_content();
        } else if ($mime === "application/vnd.openxmlformats-officedocument.wordprocessingml.document") {
            if (!class_exists("\\ZipArchive") || !class_exists("\\DOMDocument")) {
                throw new api_exception("document_converter_unavailable", 501);
            }
            $path = tempnam($CFG->tempdir, "mcpdoc");
            try {
                if (file_put_contents($path, $file->get_content()) === false) {
                    throw new api_exception("document_read_failed", 500);
                }
                $zip = new \ZipArchive();
                if ($zip->open($path) !== true) {
                    throw new api_exception("invalid_docx", 415);
                }
                try {
                    $stat = $zip->statName("word/document.xml");
                    if (!$stat || $stat["size"] > 5000000) {
                        throw new api_exception("document_too_large", 413);
                    }
                    $xml = $zip->getFromName("word/document.xml");
                } finally {
                    $zip->close();
                }
                $dom = new \DOMDocument();
                if ($xml === false || !@$dom->loadXML($xml, LIBXML_NONET)) {
                    throw new api_exception("invalid_docx", 415);
                }
                $xpath = new \DOMXPath($dom);
                $xpath->registerNamespace("w",
                    "http://schemas.openxmlformats.org/wordprocessingml/2006/main");
                $parts = [];
                foreach ($xpath->query("//w:p") as $paragraph) {
                    $parts[] = trim($paragraph->textContent);
                }
                $raw = implode("\n", $parts);
            } finally {
                @unlink($path);
            }
        } else if ($mime === "application/pdf" && class_exists("\\Smalot\\PdfParser\\Parser")) {
            $parser = new \Smalot\PdfParser\Parser();
            $raw = $parser->parseContent($file->get_content())->getText();
        } else {
            // No shell commands or arbitrary executable converters on a Moodle server.
            throw new api_exception("document_converter_unavailable", 415);
        }
        if (!mb_check_encoding($raw, "UTF-8")) {
            throw new api_exception("document_encoding_unsupported", 415);
        }
        return ["cmid" => (int)$a["cmid"], "userid" => (int)$a["userid"],
            "fileid" => (int)$a["fileid"], "filename" => $file->get_filename(),
            "mimetype" => $mime, "text" => untrusted_content::text(mb_substr($raw, 0, $max)),
            "truncated" => mb_strlen($raw) > $max];
    }

    /**
     * Group submissions stay group-scoped and are never passed to individual grading tools.
     *
     * @return array Paged, group-scoped submission metadata.
     */
    public static function group_submissions(array $a, authenticated_identity $identity): array {
        global $DB, $CFG;
        [$assign, $cm] = self::assignment((int)$a["cmid"], $identity);
        if (empty($assign->get_instance()->teamsubmission)) {
            throw new api_exception("team_submissions_disabled", 409);
        }
        require_once($CFG->libdir . "/grouplib.php");
        $limit = max(1, min(50, (int)($a["limit"] ?? 20)));
        $offset = max(0, (int)($a["offset"] ?? 0));
        $groups = groups_get_activity_allowed_groups($cm, $identity->userid);
        $all = has_capability("moodle/site:accessallgroups", $assign->get_context(), $identity->userid) ||
            groups_get_activity_groupmode($cm) !== SEPARATEGROUPS;
        $records = $DB->get_records("assign_submission",
            ["assignment" => $assign->get_instance()->id, "latest" => 1, "userid" => 0],
            "timemodified DESC, id DESC", "*", $offset, $limit);
        $result = [];
        foreach ($records as $r) {
            if (!$all && !isset($groups[$r->groupid])) {
                continue;
            }
            $result[] = ["groupid" => (int)$r->groupid,
                "submissionid" => (int)$r->id, "status" => $r->status,
                "attemptnumber" => (int)$r->attemptnumber,
                "timemodified" => (int)$r->timemodified];
        }
        return ["cmid" => (int)$a["cmid"], "submissions" => $result,
            "offset" => $offset, "next_offset" => $offset + count($records)];
    }

    /**
     * Anonymous grading must never expose userid, filename, document body or reverse mappings.
     *
     * @return array De-identified submission progress via existing Moodle blind-marking aliases.
     */
    public static function anonymous_submissions(array $a, authenticated_identity $identity): array {
        global $DB, $CFG;
        [$assign, $cm] = self::assignment((int)$a["cmid"], $identity);
        $instance = $assign->get_instance();
        if (empty($instance->blindmarking) || !empty($instance->revealidentities) ||
                !empty($instance->teamsubmission)) {
            throw new api_exception("anonymous_marking_not_active", 409);
        }
        require_once($CFG->libdir . "/grouplib.php");
        $all = has_capability("moodle/site:accessallgroups", $assign->get_context(), $identity->userid) ||
            groups_get_activity_groupmode($cm) !== SEPARATEGROUPS;
        $groups = $all ? [] : groups_get_activity_allowed_groups($cm, $identity->userid);
        $limit = max(1, min(50, (int)($a["limit"] ?? 20)));
        $offset = max(0, (int)($a["offset"] ?? 0));
        $sql = "SELECT s.id, s.userid, s.status, s.attemptnumber, s.timemodified, m.id AS anonymousid
                  FROM {assign_submission} s
                  JOIN {assign_user_mapping} m ON m.assignment = s.assignment AND m.userid = s.userid
                 WHERE s.assignment = :assignment AND s.latest = 1 AND s.groupid = 0
              ORDER BY s.timemodified DESC, s.id DESC";
        $records = $DB->get_records_sql($sql, ["assignment" => $instance->id], $offset, $limit);
        $rows = [];
        foreach ($records as $r) {
            if (!$all) {
                $allowed = false;
                foreach (array_keys($groups) as $groupid) {
                    if (groups_is_member((int)$groupid, (int)$r->userid)) {
                        $allowed = true;
                        break;
                    }
                }
                if (!$allowed) {
                    continue;
                }
            }
            $rows[] = ["anonymousid" => (int)$r->anonymousid,
                "status" => $r->status, "attemptnumber" => (int)$r->attemptnumber,
                "timemodified" => (int)$r->timemodified];
        }
        return ["cmid" => (int)$a["cmid"], "submissions" => $rows,
            "offset" => $offset, "next_offset" => $offset + count($records),
            "identities_hidden" => true];
    }

    /**
     * Validate simple grading and the concurrency fingerprint before a grade mutation.
     *
     * @return array Assignment, submission and previous feedback.
     */
    public static function grade_target(array $a, authenticated_identity $identity): array {
        $assign = self::student((int)$a["cmid"], (int)$a["userid"], $identity);
        $submission = $assign->get_user_submission((int)$a["userid"], false);
        if (!$submission || $submission->status !== ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
            throw new api_exception("submission_not_final", 409);
        }
        if ((int)$submission->timemodified !== (int)$a["expected_submission_timemodified"]) {
            throw new api_exception("submission_changed", 409);
        }
        if ($assign->grading_disabled((int)$a["userid"])) {
            throw new api_exception("grade_locked", 409);
        }
        return [$assign, $submission];
    }

    /**
     * @return array Mutate only the assignment extension through its official API.
     */
    public static function set_extension(array $a, authenticated_identity $identity): array {
        $assign = self::student((int)$a["cmid"], (int)$a["userid"], $identity);
        if (!has_capability("mod/assign:grantextension", $assign->get_context(), $identity->userid)) {
            throw new api_exception("permission_denied", 403);
        }
        $date = (int)$a["extensionduedate"];
        if (!$assign->save_user_extension((int)$a["userid"], $date)) {
            throw new api_exception("extension_rejected", 409);
        }
        return self::extensions($a, $identity);
    }

    /**
     * @return array Revert a final submission to draft, without inventing a new attempt.
     */
    public static function reopen(array $a, authenticated_identity $identity): array {
        $assign = self::student((int)$a["cmid"], (int)$a["userid"], $identity);
        $submission = $assign->get_user_submission((int)$a["userid"], false);
        if (!$submission || $submission->status !== ASSIGN_SUBMISSION_STATUS_SUBMITTED ||
                (int)$submission->timemodified !== (int)$a["expected_submission_timemodified"]) {
            throw new api_exception("submission_changed", 409);
        }
        if (!$assign->revert_to_draft((int)$a["userid"])) {
            throw new api_exception("reopen_rejected", 409);
        }
        return ["reopened_as_draft" => true, "userid" => (int)$a["userid"],
            "cmid" => (int)$a["cmid"], "new_attempt_created" => false];
    }

    /**
     * @return array Save mark and comments together through assign::save_grade().
     */
    public static function grade_feedback(array $a, authenticated_identity $identity): array {
        global $DB;
        [$assign, $submission] = self::grade_target($a, $identity);
        $instance = $assign->get_instance();
        $area = $DB->get_record("grading_areas", ["contextid" => $assign->get_context()->id,
            "component" => "mod_assign", "areaname" => "submissions"]);
        if ($area && !empty($area->activemethod)) {
            throw new api_exception("advanced_grading_use_rubric_tool", 409);
        }
        $grade = (float)$a["grade"];
        if ($grade < 0 || $grade > (float)$instance->grade || (float)$instance->grade <= 0) {
            throw new api_exception("invalid_grade", 400);
        }
        $plugin = $assign->get_feedback_plugin_by_type("comments");
        if (!$plugin || !$plugin->is_enabled()) {
            throw new api_exception("comments_feedback_disabled", 409);
        }
        $current = \local_mcp\assignment\feedback_service::current_feedback($assign, (int)$a["userid"]);
        if (!hash_equals($current["sha256"], $a["expected_feedback_sha256"])) {
            throw new api_exception("feedback_changed", 409);
        }
        $oldgrade = $assign->get_user_grade((int)$a["userid"], false);
        if ($oldgrade) {
            $files = get_file_storage()->get_area_files($assign->get_context()->id,
                "assignfeedback_comments", "feedback", $oldgrade->id, "id", false);
            if ($files) {
                throw new api_exception("feedback_attachments_present", 409);
            }
        }
        $data = (object)[
            "grade" => $grade, "attemptnumber" => -1, "addattempt" => 0, "applytoall" => 0,
            "sendstudentnotifications" => false,
            "assignfeedbackcomments_editor" => [
                "text" => trim((string)$a["feedback"]), "format" => FORMAT_PLAIN, "itemid" => 0,
            ],
        ];
        if ($instance->markingworkflow) {
            $data->workflowstate = ASSIGN_MARKING_WORKFLOW_STATE_INMARKING;
        }
        if ($data->assignfeedbackcomments_editor["text"] === "" ||
                !$assign->save_grade((int)$a["userid"], $data)) {
            throw new api_exception("grade_save_failed", 409);
        }
        return ["graded" => true, "cmid" => (int)$a["cmid"],
            "userid" => (int)$a["userid"], "grade" => $grade,
            "feedback" => \local_mcp\assignment\feedback_service::current_feedback(
                $assign, (int)$a["userid"]),
            "markingworkflow" => (bool)$instance->markingworkflow,
            "note" => $instance->markingworkflow ? "Not released; awaiting workflow approval"
                : "Moodle makes grades visible according to course gradebook visibility settings"];
    }

    /**
     * @return array Persist a rubric filling as an incomplete grading instance, without publishing a grade.
     */
    public static function rubric_draft(array $a, authenticated_identity $identity): array {
        global $CFG, $DB;
        [$assign, $submission] = self::grade_target($a, $identity);
        if (empty($assign->get_instance()->markingworkflow)) {
            throw new api_exception("marking_workflow_required_for_drafts", 409);
        }
        require_once($CFG->dirroot . "/grade/grading/lib.php");
        $manager = get_grading_manager($assign->get_context(), "mod_assign", "submissions");
        if ($manager->get_active_method() !== "rubric") {
            throw new api_exception("rubric_not_active", 409);
        }
        $definition = self::guide($a, $identity);
        if (empty($definition["criteria"])) {
            throw new api_exception("rubric_not_configured", 409);
        }
        $filling = [];
        foreach ($a["criteria"] as $choice) {
            $id = (int)$choice["criterionid"];
            if (isset($filling[$id])) {
                throw new api_exception("duplicate_rubric_criterion", 400);
            }
            $filling[$id] = ["levelid" => (int)$choice["levelid"],
                "remark" => clean_param((string)($choice["remark"] ?? ""), PARAM_TEXT)];
        }
        foreach ($definition["criteria"] as $criterion) {
            $id = (int)$criterion["id"];
            $valid = array_column($criterion["levels"], "id");
            if (!isset($filling[$id]) || !in_array($filling[$id]["levelid"], $valid, true)) {
                throw new api_exception("invalid_rubric_levels", 400);
            }
        }
        if (count($filling) !== count($definition["criteria"])) {
            throw new api_exception("invalid_rubric_criteria", 400);
        }
        $grade = $assign->get_user_grade((int)$a["userid"], true);
        $controller = $manager->get_controller("rubric");
        if (!$controller->is_form_available()) {
            throw new api_exception("rubric_not_available", 409);
        }
        $draft = $controller->get_or_create_instance(0, $identity->userid, $grade->id);
        $draft->update(["criteria" => $filling]);
        return ["draft_saved" => true, "published" => false,
            "cmid" => (int)$a["cmid"], "userid" => (int)$a["userid"],
            "gradinginstanceid" => (int)$draft->get_id(),
            "definitionid" => (int)$definition["definitionid"]];
    }

    /**
     * @return array Update marking workflow and refresh gradebook via native assign API.
     */
    public static function workflow(array $a, authenticated_identity $identity): array {
        global $DB;
        $assign = self::student((int)$a["cmid"], (int)$a["userid"], $identity);
        if (empty($assign->get_instance()->markingworkflow)) {
            throw new api_exception("marking_workflow_disabled", 409);
        }
        $state = (string)$a["state"];
        $caps = ["inmarking" => "mod/assign:grade",
            "readyforreview" => "mod/assign:grade", "inreview" => "mod/assign:reviewgrades",
            "readyforrelease" => "mod/assign:reviewgrades", "released" => "mod/assign:releasegrades"];
        if (!isset($caps[$state]) ||
                (!has_capability($caps[$state], $assign->get_context(), $identity->userid) &&
                 !has_capability("mod/assign:managegrades", $assign->get_context(), $identity->userid))) {
            throw new api_exception("permission_denied", 403);
        }
        $grade = $assign->get_user_grade((int)$a["userid"], false);
        if (!$grade || (float)$grade->grade < 0 || $assign->grading_disabled((int)$a["userid"])) {
            throw new api_exception("grade_missing_or_locked", 409);
        }
        $flags = $assign->get_user_flags((int)$a["userid"], true);
        if ((string)$flags->workflowstate !== (string)$a["expected_state"]) {
            throw new api_exception("workflow_changed", 409);
        }
        $flags->workflowstate = $state;
        if ($state === "released") {
            $flags->mailed = 0;
        }
        if (!$assign->update_user_flags($flags)) {
            throw new api_exception("workflow_update_failed", 500);
        }
        $assign->update_grade($grade);
        $user = $DB->get_record("user", ["id" => (int)$a["userid"]], "*", MUST_EXIST);
        \mod_assign\event\workflow_state_updated::create_from_user($assign, $user, $state)->trigger();
        return ["cmid" => (int)$a["cmid"], "userid" => (int)$a["userid"],
            "state" => $state, "released" => $state === "released"];
    }
}
