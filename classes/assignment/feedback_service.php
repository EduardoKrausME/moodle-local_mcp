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
 * Assignment submission and feedback access for MCP tools.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\assignment;

use assign;
use context_module;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\security\untrusted_content;

/**
 * Coordinate read-only grading context and explicitly confirmed feedback writes.
 */
final class feedback_service {
    /**
     * Load the native assignment and verify that the authenticated grader may access this learner.
     *
     * @param int $cmid Course module id.
     * @param int $userid Student id.
     * @param authenticated_identity $identity Connection identity.
     * @return array Assignment, course module, and current submission.
     */
    public static function target(int $cmid, int $userid, authenticated_identity $identity): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/mod/assign/locallib.php");
        require_once($CFG->libdir . "/grouplib.php");

        [$course, $cm] = get_course_and_cm_from_cmid($cmid, "assign");
        $context = context_module::instance($cm->id);
        if (!has_capability("mod/assign:grade", $context, $identity->userid)) {
            throw new api_exception("permission_denied", 403);
        }
        $modinfo = get_fast_modinfo($course, $identity->userid);
        if (!$modinfo->get_cm($cm->id)->uservisible) {
            throw new api_exception("permission_denied", 403);
        }
        if (!$DB->record_exists("user", ["id" => $userid, "deleted" => 0]) ||
                !is_enrolled($context, $userid)) {
            throw new api_exception("student_not_found", 404);
        }

        $assign = new assign($context, $cm, $course);
        $instance = $assign->get_instance();
        // A user id must never be used to bypass the Moodle anonymous grading identifier.
        if (!empty($instance->blindmarking) && empty($instance->revealidentities)) {
            throw new api_exception("blind_marking_unsupported", 409);
        }
        // Group assignments require a different submission/grade mapping. Never grade a member's
        // record as though it represented an individual submission.
        if (!empty($instance->teamsubmission)) {
            throw new api_exception("team_submission_unsupported", 409);
        }
        if (groups_get_activity_groupmode($cm) === SEPARATEGROUPS &&
                !has_capability("moodle/site:accessallgroups", $context, $identity->userid)) {
            $allowed = groups_get_activity_allowed_groups($cm, $identity->userid);
            $member = false;
            foreach (array_keys($allowed) as $groupid) {
                if (groups_is_member((int)$groupid, $userid)) {
                    $member = true;
                    break;
                }
            }
            if (!$member) {
                throw new api_exception("permission_denied", 403);
            }
        }

        $submission = $assign->get_user_submission($userid, false);
        if (!$submission || (int)$submission->assignment !== (int)$instance->id) {
            throw new api_exception("submission_not_found", 404);
        }
        return [$assign, $cm, $submission];
    }

    /**
     * Return student work and assessment criteria without modifying grades or publishing feedback.
     *
     * @param int $cmid Course module id.
     * @param int $userid Student id.
     * @param authenticated_identity $identity Grader.
     * @param int $maxchars Maximum text characters exported.
     * @return array Grading context.
     */
    public static function context_data(int $cmid, int $userid,
            authenticated_identity $identity, int $maxchars = 20000): array {
        global $DB;
        [$assign, $cm, $submission] = self::target($cmid, $userid, $identity);
        $instance = $assign->get_instance();
        $context = $assign->get_context();
        $maxchars = max(1000, min(100000, $maxchars));

        $online = null;
        $onlineplugin = $assign->get_submission_plugin_by_type("onlinetext");
        if ($onlineplugin && $onlineplugin->is_enabled()) {
            $record = $DB->get_record("assignsubmission_onlinetext", ["submission" => $submission->id]);
            if ($record) {
                $online = untrusted_content::text((string)$record->onlinetext);
            }
        }

        $files = [];
        $fileplugin = $assign->get_submission_plugin_by_type("file");
        if ($fileplugin && $fileplugin->is_enabled()) {
            $storedfiles = get_file_storage()->get_area_files(
                $context->id, "assignsubmission_file", "submission_files", $submission->id, "id ASC", false
            );
            foreach ($storedfiles as $file) {
                if (count($files) >= 30) {
                    break;
                }
                $entry = [
                    "filename" => $file->get_filename(),
                    "mimetype" => $file->get_mimetype(),
                    "size" => $file->get_filesize(),
                    "filepath" => $file->get_filepath(),
                ];
                // Safe text files can be reviewed directly; PDFs and office formats are only
                // listed because their binary bytes need a dedicated, explicitly approved reader.
                if (in_array($file->get_mimetype(), ["text/plain", "text/markdown", "text/csv"], true)
                        && $file->get_filesize() <= 100000) {
                    $text = $file->get_content();
                    if (mb_check_encoding($text, "UTF-8")) {
                        $entry["text"] = mb_substr($text, 0, $maxchars);
                    }
                }
                $files[] = $entry;
            }
        }

        $remaining = $maxchars;
        $onlinetruncated = false;
        if ($online !== null) {
            $onlinetruncated = mb_strlen($online) > $remaining;
            $online = mb_substr($online, 0, $remaining);
            $remaining -= mb_strlen($online);
        }
        foreach ($files as &$file) {
            if (!isset($file["text"])) {
                continue;
            }
            $text = $file["text"];
            $file["truncated"] = mb_strlen($text) > $remaining;
            $file["text"] = mb_substr($text, 0, $remaining);
            $remaining -= mb_strlen($file["text"]);
        }
        unset($file);

        return [
            "cmid" => $cmid,
            "assignmentid" => (int)$instance->id,
            "userid" => $userid,
            "assignment" => [
                "name" => $instance->name,
                "instructions" => untrusted_content::text(
                    format_module_intro("assign", $instance, $cm->id)
                ),
                "duedate" => (int)$instance->duedate,
                "grade" => (float)$instance->grade,
            ],
            "submission" => [
                "id" => (int)$submission->id,
                "status" => $submission->status,
                "attemptnumber" => (int)$submission->attemptnumber,
                "timemodified" => (int)$submission->timemodified,
                "online_text" => $online,
                "online_text_truncated" => $onlinetruncated,
                "files" => $files,
            ],
            "grading" => self::criteria($context->id),
            "feedback" => self::current_feedback($assign, $userid),
            "limits" => ["maxchars" => $maxchars, "attachment_count" => count($files)],
        ];
    }

    /**
     * Read rubric criteria without triggering creation of grading area definitions.
     *
     * @param int $contextid Module context id.
     * @return array Current grading method and criteria.
     */
    private static function criteria(int $contextid): array {
        global $DB;
        $out = ["method" => "simple", "criteria" => []];
        $area = $DB->get_record("grading_areas",
            ["contextid" => $contextid, "component" => "mod_assign", "areaname" => "submissions"]);
        if (!$area || empty($area->activemethod)) {
            return $out;
        }
        $out["method"] = $area->activemethod;
        if ($area->activemethod !== "rubric") {
            return $out;
        }
        $definitions = $DB->get_records("grading_definitions",
            ["areaid" => $area->id], "id DESC", "*", 0, 1);
        if (!$definitions) {
            return $out;
        }
        $definition = reset($definitions);
        $criteria = $DB->get_records("gradingform_rubric_criteria",
            ["definitionid" => $definition->id], "sortorder ASC, id ASC");
        foreach ($criteria as $criterion) {
            $levels = $DB->get_records("gradingform_rubric_levels",
                ["criterionid" => $criterion->id], "score ASC, id ASC");
            $data = [
                "id" => (int)$criterion->id,
                "description" => untrusted_content::text($criterion->description),
                "levels" => [],
            ];
            foreach ($levels as $level) {
                $data["levels"][] = [
                    "id" => (int)$level->id,
                    "description" => untrusted_content::text($level->definition),
                    "score" => (float)$level->score,
                ];
            }
            $out["criteria"][] = $data;
        }
        return $out;
    }

    /**
     * Return current feedback and a concurrency fingerprint.
     *
     * @param assign $assign Assignment.
     * @param int $userid Student id.
     * @return array Feedback snapshot.
     */
    public static function current_feedback(assign $assign, int $userid): array {
        global $DB;
        $grade = $assign->get_user_grade($userid, false);
        $comments = $grade ? $DB->get_record("assignfeedback_comments", ["grade" => $grade->id]) : false;
        $raw = $comments ? (string)$comments->commenttext : "";
        return [
            "text" => untrusted_content::text($raw),
            "sha256" => hash("sha256", $raw),
            "has_feedback" => $raw !== "",
            "grade" => $grade ? (float)$grade->grade : null,
        ];
    }

    /**
     * Check optimistic concurrency and assignment feedback availability.
     *
     * @param array $arguments Tool input.
     * @param authenticated_identity $identity Grader.
     * @return array The current assignment, submission, feedback snapshot.
     */
    public static function verified_feedback_target(array $arguments, authenticated_identity $identity): array {
        $cmid = (int)$arguments["cmid"];
        $userid = (int)$arguments["userid"];
        [$assign, $cm, $submission] = self::target($cmid, $userid, $identity);
        if ($submission->status !== ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
            throw new api_exception("submission_not_final", 409);
        }
        if ((int)$submission->timemodified !== (int)$arguments["expected_submission_timemodified"]) {
            throw new api_exception("submission_changed", 409);
        }
        $feedbackplugin = $assign->get_feedback_plugin_by_type("comments");
        if (!$feedbackplugin || !$feedbackplugin->is_enabled()) {
            throw new api_exception("comments_feedback_disabled", 409);
        }
        $current = self::current_feedback($assign, $userid);
        if (!hash_equals($current["sha256"], (string)$arguments["expected_feedback_sha256"])) {
            throw new api_exception("feedback_changed", 409);
        }
        $gradeitem = $assign->get_grade_item();
        if ($gradeitem && $gradeitem->is_locked($userid)) {
            throw new api_exception("grade_locked", 409);
        }
        return [$assign, $submission, $current, $feedbackplugin];
    }

    /**
     * Persist a reviewed comment using the assignment feedback subplugin API, without changing marks.
     *
     * @param array $arguments Tool input.
     * @param authenticated_identity $identity Grader.
     * @return array Result.
     */
    public static function save_feedback(array $arguments, authenticated_identity $identity): array {
        [$assign, $submission, $current, $feedbackplugin] =
            self::verified_feedback_target($arguments, $identity);

        $grade = $assign->get_user_grade((int)$arguments["userid"], true);
        // Avoid removing attachments from an existing rich-text feedback entry.
        $oldfiles = get_file_storage()->get_area_files(
            $assign->get_context()->id, "assignfeedback_comments", "feedback", $grade->id, "id", false
        );
        if ($oldfiles) {
            throw new api_exception("feedback_attachments_present", 409);
        }
        $content = trim((string)$arguments["feedback"]);
        if ($content === "") {
            throw new api_exception("feedback_empty", 400);
        }
        $data = (object)[
            "assignfeedbackcomments_editor" => [
                "text" => $content,
                "format" => FORMAT_PLAIN,
                "itemid" => 0,
            ],
        ];
        if (!$feedbackplugin->save($grade, $data)) {
            throw new api_exception("feedback_save_failed", 500);
        }
        return [
            "saved" => true,
            "cmid" => (int)$arguments["cmid"],
            "userid" => (int)$arguments["userid"],
            "submissionid" => (int)$submission->id,
            "grade_unchanged" => true,
            "feedback" => self::current_feedback($assign, (int)$arguments["userid"]),
        ];
    }
}
