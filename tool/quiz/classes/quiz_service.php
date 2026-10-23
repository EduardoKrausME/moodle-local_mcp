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
 * Quiz access, configuration and question structure services.
 *
 * @package   mcptool_quiz
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_quiz;

use context_module;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\security\untrusted_content;
use mod_quiz\quiz_settings;
use mod_quiz\structure;

/**
 * Resolve authorized quizzes and delegate structure edits to Moodle APIs.
 */
final class quiz_service {
    /**
     * @return array Quiz record, course module, context, course.
     */
    public static function target(int $cmid, authenticated_identity $identity, string $capability): array {
        global $DB;
        [$course, $cm] = get_course_and_cm_from_cmid($cmid, "quiz");
        $context = context_module::instance($cm->id);
        if (!has_capability($capability, $context, $identity->userid) ||
                !get_fast_modinfo($course, $identity->userid)->get_cm($cm->id)->uservisible) {
            throw new api_exception("permission_denied", 403);
        }
        return [$DB->get_record("quiz", ["id" => $cm->instance], "*", MUST_EXIST), $cm, $context, $course];
    }

    /**
     * Restrict a particular student attempt by enrolment and separate groups.
     *
     * @return array Quiz, course module, attempt.
     */
    public static function attempt(int $cmid, int $attemptid,
            authenticated_identity $identity, string $capability): array {
        global $DB, $CFG;
        [$quiz, $cm, $context] = self::target($cmid, $identity, $capability);
        $attempt = $DB->get_record("quiz_attempts", ["id" => $attemptid,
            "quiz" => $quiz->id, "preview" => 0], "*", MUST_EXIST);
        if (!is_enrolled($context, (int)$attempt->userid, "", true)) {
            throw new api_exception("student_not_enrolled", 403);
        }
        require_once($CFG->libdir . "/grouplib.php");
        if (groups_get_activity_groupmode($cm) === SEPARATEGROUPS &&
                !has_capability("moodle/site:accessallgroups", $context, $identity->userid)) {
            $allowed = groups_get_activity_allowed_groups($cm, $identity->userid);
            $cansee = false;
            foreach (array_keys($allowed) as $groupid) {
                if (groups_is_member((int)$groupid, (int)$attempt->userid)) {
                    $cansee = true;
                    break;
                }
            }
            if (!$cansee) {
                throw new api_exception("permission_denied", 403);
            }
        }
        return [$quiz, $cm, $attempt];
    }

    /**
     * @return array Slot positions, page breaks and question metadata (no answer keys).
     */
    public static function slots(array $a, authenticated_identity $identity): array {
        global $CFG;
        [$quiz, $cm, $context] = self::target((int)$a["cmid"], $identity, "mod/quiz:manage");
        require_once($CFG->dirroot . "/mod/quiz/locallib.php");
        $structure = structure::create_for_quiz(quiz_settings::create($quiz->id));
        $items = [];
        foreach ($structure->get_slots() as $slot) {
            $items[] = ["slotid" => (int)$slot->id, "slot" => (int)$slot->slot,
                "page" => (int)$slot->page, "maxmark" => (float)$slot->maxmark,
                "questionid" => isset($slot->questionid) ? (int)$slot->questionid : null,
                "name" => isset($slot->name) ? untrusted_content::text($slot->name) : null];
        }
        return ["cmid" => (int)$cm->id, "quizid" => (int)$quiz->id,
            "editable" => $structure->can_be_edited(), "slots" => $items];
    }

    /**
     * @return array Current and proposed quiz metadata.
     */
    public static function update_preview(array $a, authenticated_identity $identity): array {
        [$quiz] = self::target((int)$a["cmid"], $identity, "mod/quiz:manage");
        $fields = ["name", "intro", "timeopen", "timeclose", "timelimit", "attempts"];
        $before = [];
        $after = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $a)) {
                $before[$field] = $quiz->{$field};
                $after[$field] = $a[$field];
            }
        }
        return ["cmid" => (int)$a["cmid"], "before" => $before, "after" => $after];
    }

    /**
     * Update selected settings, preserving unrelated quiz and module configuration.
     *
     * @return array Updated fields.
     */
    public static function update(array $a, authenticated_identity $identity): array {
        global $DB, $CFG;
        [$quiz, $cm, $context] = self::target((int)$a["cmid"], $identity, "mod/quiz:manage");
        require_once($CFG->dirroot . "/mod/quiz/locallib.php");
        require_once($CFG->dirroot . "/mod/quiz/lib.php");
        $fields = ["name", "intro", "timeopen", "timeclose", "timelimit", "attempts"];
        $changes = (object)["id" => $quiz->id, "timemodified" => time()];
        $changed = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $a)) {
                continue;
            }
            $changes->{$field} = $field === "name" ? clean_param($a[$field], PARAM_TEXT)
                : ($field === "intro" ? clean_text($a[$field], FORMAT_HTML) : (int)$a[$field]);
            $changed[] = $field;
        }
        if (!$changed) {
            throw new api_exception("no_quiz_changes", 400);
        }
        $open = (int)($changes->timeopen ?? $quiz->timeopen);
        $close = (int)($changes->timeclose ?? $quiz->timeclose);
        if ($open && $close && $close <= $open) {
            throw new api_exception("invalid_quiz_dates", 400);
        }
        $DB->update_record("quiz", $changes);
        $updated = $DB->get_record("quiz", ["id" => $quiz->id], "*", MUST_EXIST);
        quiz_update_events($updated);
        if (array_intersect($changed, ["timeopen", "timeclose", "timelimit"])) {
            quiz_update_open_attempts(["quizid" => $quiz->id]);
        }
        quiz_delete_previews($updated);
        rebuild_course_cache($cm->course, true);
        \core\event\course_module_updated::create_from_cm($cm)->trigger();
        return ["updated" => true, "cmid" => (int)$cm->id, "fields" => $changed];
    }

    /**
     * @return array Question removed via the quiz structure API.
     */
    public static function remove(array $a, authenticated_identity $identity): array {
        global $DB, $CFG;
        [$quiz] = self::target((int)$a["cmid"], $identity, "mod/quiz:manage");
        require_once($CFG->dirroot . "/mod/quiz/locallib.php");
        $structure = structure::create_for_quiz(quiz_settings::create($quiz->id));
        $structure->check_can_be_edited();
        $slotid = (int)$a["slotid"];
        $slot = $DB->get_record("quiz_slots", ["id" => $slotid, "quizid" => $quiz->id], "*", MUST_EXIST);
        $structure->remove_slot((int)$slot->slot);
        quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
        return ["removed" => true, "cmid" => (int)$a["cmid"], "slotid" => $slotid];
    }

    /**
     * @return array Move one slot relative to another within a validated page.
     */
    public static function reorder(array $a, authenticated_identity $identity): array {
        global $DB, $CFG;
        [$quiz] = self::target((int)$a["cmid"], $identity, "mod/quiz:manage");
        require_once($CFG->dirroot . "/mod/quiz/locallib.php");
        $structure = structure::create_for_quiz(quiz_settings::create($quiz->id));
        $structure->check_can_be_edited();
        $slotid = (int)$a["slotid"];
        $after = (int)($a["after_slotid"] ?? 0);
        $DB->get_record("quiz_slots", ["id" => $slotid, "quizid" => $quiz->id], "*", MUST_EXIST);
        if ($after) {
            $DB->get_record("quiz_slots", ["id" => $after, "quizid" => $quiz->id], "*", MUST_EXIST);
        }
        $structure->move_slot($slotid, $after, (int)$a["page"]);
        return ["moved" => true, "cmid" => (int)$a["cmid"], "slotid" => $slotid,
            "after_slotid" => $after, "page" => (int)$a["page"]];
    }
}
