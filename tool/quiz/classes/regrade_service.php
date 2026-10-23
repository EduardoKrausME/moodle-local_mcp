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
 * One-attempt quiz regrade operations with optimistic concurrency.
 *
 * @package   mcptool_quiz
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_quiz;

use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use mod_quiz\quiz_settings;

/**
 * Regrade a single finished attempt through Moodle's report engine.
 */
final class regrade_service {
    /**
     * @return array Current attempt state and concurrency fingerprint.
     */
    public static function preview(array $a, authenticated_identity $identity): array {
        [$quiz, $cm, $attempt] = quiz_service::attempt((int)$a["cmid"],
            (int)$a["attemptid"], $identity, "mod/quiz:regrade");
        if ($attempt->state !== "finished") {
            throw new api_exception("attempt_not_finished", 409);
        }
        return ["quizid" => (int)$quiz->id, "attemptid" => (int)$attempt->id,
            "userid" => (int)$attempt->userid, "sumgrades" => (float)$attempt->sumgrades,
            "timemodified" => (int)$attempt->timemodified, "regrade_scope" => "single_attempt",
            "will_update_gradebook" => true];
    }

    /**
     * @return array Regrade receipt with before/after grades.
     */
    public static function execute(array $a, authenticated_identity $identity): array {
        global $DB, $CFG;
        [$quiz, $cm, $attempt] = quiz_service::attempt((int)$a["cmid"],
            (int)$a["attemptid"], $identity, "mod/quiz:regrade");
        if ($attempt->state !== "finished" ||
                (int)$attempt->timemodified !== (int)$a["expected_timemodified"] ||
                abs((float)$attempt->sumgrades - (float)$a["expected_sumgrades"]) > 0.000001) {
            throw new api_exception("attempt_changed_refresh_preview", 409);
        }
        require_once($CFG->dirroot . "/mod/quiz/locallib.php");
        require_once($CFG->dirroot . "/mod/quiz/report/overview/report.php");
        require_once($CFG->dirroot . "/question/engine/lib.php");
        $transaction = $DB->start_delegated_transaction();
        $report = new regrade_report(\context_module::instance($cm->id));
        $messages = $report->regrade_attempt($attempt, false, null);
        if ($messages) {
            throw new api_exception("quiz_regrade_validation_failed", 409);
        }
        $usage = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);
        $new = (float)$usage->get_total_mark();
        $DB->update_record("quiz_attempts", (object)[
            "id" => $attempt->id, "sumgrades" => $new, "timemodified" => time(),
        ]);
        quiz_settings::create((int)$quiz->id)->get_grade_calculator()
            ->recompute_final_grade((int)$attempt->userid);
        $transaction->allow_commit();
        return ["regraded" => true, "quizid" => (int)$quiz->id,
            "attemptid" => (int)$attempt->id, "old_sumgrades" => (float)$attempt->sumgrades,
            "new_sumgrades" => $new, "gradebook_recomputed" => true];
    }
}
