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
 * Attach an existing question bank question to a quiz.
 *
 * @package   mcptool_quiz
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_quiz\write;

use coding_exception;
use context;
use context_module;
use dml_exception;
use local_mcp\exception\api_exception;
use local_mcp\security\input_guard;
use local_mcp\security\authenticated_identity;
use local_mcp\write\tool\base_tool;
use mod_quiz\quiz_settings;

/**
 * Attach a bank question by ID and recalculate quiz totals.
 */
final class add_question extends base_tool {
    /**
     * get_name
     *
     * @return string
     */
    public function get_name(): string {
        return 'quiz_add_question';
    }

    /**
     * get_title
     *
     * @return string
     */
    public function get_title(): string {
        return 'Quiz: add bank question';
    }

    /**
     * get_description
     *
     * @return string
     */
    public function get_description(): string {
        return 'Add an existing ready question from the question bank to a quiz, checking question-use permissions.';
    }

    /**
     * get_required_capability
     *
     * @return string
     */
    public function get_required_capability(): string {
        return 'mod/quiz:manage';
    }

    /**
     * get_input_schema
     *
     * @return array
     */
    public function get_input_schema(): array {
        return $this->object_schema([
            'cmid' => ['type' => 'integer', 'minimum' => 1],
            'questionid' => ['type' => 'integer', 'minimum' => 1],
            'page' => ['type' => 'integer', 'minimum' => 0],
        ], ['cmid', 'questionid']);
    }

    /**
     * resolve_context
     *
     * @param array $a
     * @return context
     */
    public function resolve_context(array $a): context {
        return input_guard::module((int)$a["cmid"], "quiz");
    }

    /**
     * Verify that the quiz can be changed and the question is ready and usable.
     *
     * @param array $a Tool arguments.
     * @return array Course module, quiz record and question ID.
     */
    private function validate_target(array $a): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/quiz/locallib.php");
        $context = $this->resolve_context($a);
        $cm = get_coursemodule_from_id("quiz", (int)$context->instanceid, 0, false, MUST_EXIST);
        $quiz = input_guard::record("quiz", (int)$cm->instance, "quiz_not_found");
        $quiz->cmid = $cm->id;
        if (quiz_has_attempts($quiz->id)) {
            throw new api_exception("quiz_has_attempts", 409);
        }
        $questionid = (int)$a["questionid"];
        input_guard::record("question", $questionid, "question_not_found");
        if (!$DB->record_exists("question_versions", ["questionid" => $questionid, "status" => "ready"])) {
            throw new api_exception("question_not_ready", 400);
        }
        quiz_require_question_use($questionid);
        return [$cm, $quiz, $questionid];
    }

    /**
     * Validate real question bank and quiz state before issuing a confirmation token.
     *
     * @param array $a Tool arguments.
     * @param authenticated_identity $identity Authenticated user.
     * @return array
     */
    public function preview(array $a, authenticated_identity $identity): array {
        [$cm, $quiz, $questionid] = $this->validate_target($a);
        return ["operation" => $this->get_name(), "cmid" => (int)$cm->id,
            "quizid" => (int)$quiz->id, "questionid" => $questionid,
            "page" => (int)($a["page"] ?? 0)];
    }

    /**
     * Add a validated bank question and recalculate quiz grades.
     *
     * @param array $a Tool arguments.
     * @param authenticated_identity $identity Authenticated user.
     * @return array
     */
    public function execute(array $a, authenticated_identity $identity): array {
        [$cm, $quiz, $questionid] = $this->validate_target($a);
        $added = quiz_add_quiz_question($questionid, $quiz, (int)($a["page"] ?? 0));
        if ($added === false) {
            throw new api_exception("question_already_in_quiz", 409);
        }
        quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
        return ["cmid" => (int)$cm->id, "quizid" => (int)$quiz->id,
            "questionid" => $questionid, "added" => true];
    }
}
