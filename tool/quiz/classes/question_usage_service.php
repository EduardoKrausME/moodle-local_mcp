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
 * Question bank version and quiz reuse inspection.
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

/**
 * Read question bank versions and authorized quiz references, without answer keys.
 */
final class question_usage_service {
    /**
     * @return array Quiz and question bank entry attached to this exact slot.
     */
    private static function entry(array $a, authenticated_identity $identity): array {
        global $DB;
        [$quiz] = quiz_service::target((int)$a["cmid"], $identity, "mod/quiz:manage");
        $slot = $DB->get_record("quiz_slots", ["id" => (int)$a["slotid"],
            "quizid" => $quiz->id], "*", MUST_EXIST);
        $ref = $DB->get_record("question_references",
            ["component" => "mod_quiz", "questionarea" => "slot", "itemid" => $slot->id]);
        if (!$ref) {
            throw new api_exception("slot_is_not_fixed_question", 409);
        }
        return [$quiz, $slot, $ref];
    }

    /**
     * @return array Versions and statuses, with no question body or correct answers.
     */
    public static function versions(array $a, authenticated_identity $identity): array {
        global $DB;
        [$quiz, $slot, $ref] = self::entry($a, $identity);
        $versions = $DB->get_records("question_versions",
            ["questionbankentryid" => $ref->questionbankentryid], "version DESC", "*", 0, 50);
        $rows = [];
        foreach ($versions as $version) {
            $q = $DB->get_record("question", ["id" => $version->questionid], "id,name,qtype");
            $rows[] = ["version" => (int)$version->version, "questionid" => (int)$version->questionid,
                "status" => $version->status, "name" => $q ? untrusted_content::text($q->name) : null,
                "qtype" => $q ? $q->qtype : null];
        }
        return ["cmid" => (int)$a["cmid"], "slotid" => (int)$slot->id,
            "questionbankentryid" => (int)$ref->questionbankentryid,
            "versions" => $rows, "version_limit" => 50];
    }

    /**
     * @return array Only authorized reuse inside the same Moodle course.
     */
    public static function usage(array $a, authenticated_identity $identity): array {
        global $DB;
        [$quiz, $slot, $ref] = self::entry($a, $identity);
        $sql = "SELECT qs.id AS slotid, qs.quizid, qs.slot, qz.name
                  FROM {question_references} qr
                  JOIN {quiz_slots} qs ON qs.id = qr.itemid
                  JOIN {quiz} qz ON qz.id = qs.quizid
                 WHERE qr.component = :component AND qr.questionarea = :area
                   AND qr.questionbankentryid = :bankentry AND qz.course = :course
              ORDER BY qs.quizid ASC, qs.slot ASC";
        $records = $DB->get_records_sql($sql, [
            "component" => "mod_quiz", "area" => "slot",
            "bankentry" => $ref->questionbankentryid, "course" => $quiz->course,
        ], 0, 100);
        $rows = [];
        foreach ($records as $record) {
            $cm = get_coursemodule_from_instance("quiz", $record->quizid, $quiz->course, false);
            if (!$cm) {
                continue;
            }
            try {
                quiz_service::target((int)$cm->id, $identity, "mod/quiz:manage");
            } catch (api_exception $exception) {
                continue;
            }
            $rows[] = ["cmid" => (int)$cm->id, "quizid" => (int)$record->quizid,
                "slotid" => (int)$record->slotid, "slot" => (int)$record->slot,
                "quiz_name" => untrusted_content::text($record->name)];
        }
        return ["cmid" => (int)$a["cmid"], "slotid" => (int)$slot->id,
            "questionbankentryid" => (int)$ref->questionbankentryid,
            "authorized_course_references" => $rows, "limit" => 100,
            "cross_course_usage_excluded" => true];
    }
}
