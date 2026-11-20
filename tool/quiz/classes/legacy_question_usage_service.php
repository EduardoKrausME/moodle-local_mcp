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
 * Read-only Moodle 3.10/3.11 Quiz question usage.
 *
 * @package mcptool_quiz
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mcptool_quiz;

use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\security\untrusted_content;

final class legacy_question_usage_service {
    private static function entry(array $a, authenticated_identity $identity): array {
        global $DB;
        [$quiz] = quiz_service::target((int)$a['cmid'], $identity, 'mod/quiz:manage');
        $slot = $DB->get_record('quiz_slots',
            ['id' => (int)$a['slotid'], 'quizid' => $quiz->id], '*', MUST_EXIST);
        if (empty($slot->questionid)) {
            throw new api_exception('slot_is_not_fixed_question', 409);
        }
        $question = $DB->get_record('question',
            ['id' => (int)$slot->questionid], 'id,name,qtype,hidden', MUST_EXIST);
        if ($question->qtype === 'random') {
            throw new api_exception('slot_is_not_fixed_question', 409);
        }
        return [$quiz, $slot, $question];
    }

    public static function versions(array $a, authenticated_identity $identity): array {
        [$quiz, $slot, $question] = self::entry($a, $identity);
        // Moodle 3.x has no native historical question versions.
        return [
            'cmid' => (int)$a['cmid'], 'slotid' => (int)$slot->id,
            'questionbankentryid' => (int)$question->id,
            'versions' => [[
                'version' => 1, 'questionid' => (int)$question->id,
                'status' => $question->hidden ? 'hidden' : 'ready',
                'name' => untrusted_content::text($question->name),
                'qtype' => $question->qtype,
            ]],
            'version_limit' => 1,
        ];
    }

    public static function usage(array $a, authenticated_identity $identity): array {
        global $DB;
        [$quiz, $slot, $question] = self::entry($a, $identity);
        $sql = 'SELECT qs.id AS slotid, qs.quizid, qs.slot, qz.name
                  FROM {quiz_slots} qs
                  JOIN {quiz} qz ON qz.id = qs.quizid
                 WHERE qs.questionid = :questionid AND qz.course = :course
              ORDER BY qs.quizid ASC, qs.slot ASC';
        $records = $DB->get_records_sql($sql, [
            'questionid' => (int)$question->id, 'course' => (int)$quiz->course,
        ], 0, 100);
        $rows = [];
        foreach ($records as $record) {
            $cm = get_coursemodule_from_instance('quiz', $record->quizid, $quiz->course, false);
            if (!$cm) {
                continue;
            }
            try {
                quiz_service::target((int)$cm->id, $identity, 'mod/quiz:manage');
            } catch (api_exception $exception) {
                continue;
            }
            $rows[] = [
                'cmid' => (int)$cm->id, 'quizid' => (int)$record->quizid,
                'slotid' => (int)$record->slotid, 'slot' => (int)$record->slot,
                'quiz_name' => untrusted_content::text($record->name),
            ];
        }
        return [
            'cmid' => (int)$a['cmid'], 'slotid' => (int)$slot->id,
            'questionbankentryid' => (int)$question->id,
            'authorized_course_references' => $rows, 'limit' => 100,
            'cross_course_usage_excluded' => true,
        ];
    }
}
