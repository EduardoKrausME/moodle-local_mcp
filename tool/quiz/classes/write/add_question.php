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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Attach an existing question bank question to a quiz.
 *
 * @package   mcptool_quiz
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_quiz\write;

use context;
use context_module;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\write\tool\base_tool;

/** Attach a bank question by ID and recalculate quiz totals. */
final class add_question extends base_tool {
    public function get_name(): string { return 'quiz_add_question'; }
    public function get_title(): string { return 'Quiz: add bank question'; }
    public function get_description(): string {
        return 'Add an existing ready question from the question bank to a quiz, checking question-use permissions.';
    }
    public function get_required_capability(): string { return 'mod/quiz:manage'; }
    public function get_input_schema(): array {
        return $this->object_schema([
            'cmid' => ['type' => 'integer', 'minimum' => 1],
            'questionid' => ['type' => 'integer', 'minimum' => 1],
            'page' => ['type' => 'integer', 'minimum' => 0],
        ], ['cmid', 'questionid']);
    }
    public function resolve_context(array $a): context {
        return context_module::instance((int)$a['cmid'], MUST_EXIST);
    }
    public function execute(array $a, authenticated_identity $identity): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $cm = get_coursemodule_from_id('quiz', (int)$a['cmid'], 0, false, MUST_EXIST);
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
        $quiz->cmid = $cm->id;
        if (quiz_has_attempts($quiz->id)) {
            throw new api_exception('quiz_has_attempts', 409);
        }
        $questionid = (int)$a['questionid'];
        $question = $DB->get_record('question', ['id' => $questionid], '*', MUST_EXIST);
        if (!$DB->record_exists('question_versions', ['questionid' => $questionid, 'status' => 'ready'])) {
            throw new api_exception('question_not_ready', 400);
        }
        quiz_require_question_use($questionid);
        $added = quiz_add_quiz_question($questionid, $quiz, (int)($a['page'] ?? 0));
        if ($added === false) {
            throw new api_exception('question_already_in_quiz', 409);
        }
        \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
        return ['cmid' => (int)$cm->id, 'quizid' => (int)$quiz->id, 'questionid' => $questionid, 'added' => true];
    }
}
