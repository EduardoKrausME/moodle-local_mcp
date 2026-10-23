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
 * Read quiz attempt responses and aggregate analytics.
 *
 * @package   mcptool_quiz
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_quiz;

use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\security\untrusted_content;

/**
 * Restrict response inspection to authorized teachers and aggregate statistics in bounded samples.
 */
final class attempt_service {
    /**
     * @return array Submitted response data only; no answer keys or teacher-only marking hints.
     */
    public static function responses(array $a, authenticated_identity $identity): array {
        global $CFG;
        [$quiz, $cm, $attempt] = quiz_service::attempt(
            (int)$a["cmid"], (int)$a["attemptid"], $identity, "mod/quiz:viewreports");
        if (!has_capability("mod/quiz:grade", \context_module::instance($cm->id), $identity->userid) ||
                $attempt->state !== "finished") {
            throw new api_exception("attempt_review_unavailable", 403);
        }
        require_once($CFG->dirroot . "/question/engine/lib.php");
        $usage = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);
        $rows = [];
        foreach (array_slice($usage->get_slots(), 0, 60) as $slot) {
            $qa = $usage->get_question_attempt($slot);
            $response = [];
            foreach ($qa->get_last_qt_data() as $name => $value) {
                // Do not export file/blob variables or anything unbounded from a student response.
                if (!is_scalar($value)) {
                    continue;
                }
                $response[(string)$name] = untrusted_content::text(
                    mb_substr((string)$value, 0, 2000));
            }
            $rows[] = ["slot" => (int)$slot, "questionid" => (int)$qa->get_question_id(),
                "state" => (string)$qa->get_state(),
                "fraction" => $usage->get_question_fraction($slot),
                "response" => $response];
        }
        return ["cmid" => (int)$cm->id, "attemptid" => (int)$attempt->id,
            "userid" => (int)$attempt->userid, "state" => $attempt->state,
            "sumgrades" => (float)$attempt->sumgrades,
            "timemodified" => (int)$attempt->timemodified,
            "responses" => $rows, "truncated" => count($usage->get_slots()) > 60];
    }

    /**
     * Read a maximum of 500 finished attempts and filter inaccessible students.
     *
     * @return array Bounded, aggregate quiz results.
     */
    public static function sample(int $cmid, authenticated_identity $identity): array {
        global $DB;
        [$quiz, $cm] = quiz_service::target($cmid, $identity, "mod/quiz:viewreports");
        $records = $DB->get_records("quiz_attempts",
            ["quiz" => $quiz->id, "preview" => 0, "state" => "finished"],
            "id ASC", "*", 0, 500);
        $out = [];
        foreach ($records as $attempt) {
            try {
                quiz_service::attempt($cmid, (int)$attempt->id, $identity, "mod/quiz:viewreports");
            } catch (api_exception $e) {
                continue;
            }
            $out[] = $attempt;
        }
        return [$quiz, $out];
    }

    /**
     * @return array Anonymized descriptive statistics, not the standard Moodle statistics report.
     */
    public static function statistics(array $a, authenticated_identity $identity): array {
        [$quiz, $attempts] = self::sample((int)$a["cmid"], $identity);
        $n = count($attempts);
        $values = array_map(static function($r) {
            return (float)$r->sumgrades;
        }, $attempts);
        $sum = array_sum($values);
        return ["quizid" => (int)$quiz->id, "finished_sample_size" => $n,
            "sample_limit" => 500, "mean_raw_grade" => $n >= 5 ? $sum / $n : null,
            "min_raw_grade" => $n >= 5 ? min($values) : null,
            "max_raw_grade" => $n >= 5 ? max($values) : null,
            "max_possible_raw_grade" => (float)$quiz->sumgrades,
            "low_sample_suppressed" => $n < 5,
            "method" => "sample_descriptive_not_moodle_statistics_report"];
    }

    /**
     * @return array Per-slot difficulty and discrimination correlations over finished attempts.
     */
    public static function question_statistics(array $a, authenticated_identity $identity): array {
        global $CFG;
        [$quiz, $attempts] = self::sample((int)$a["cmid"], $identity);
        if (count($attempts) < 5) {
            return ["quizid" => (int)$quiz->id, "low_sample_suppressed" => true,
                "questions" => []];
        }
        require_once($CFG->dirroot . "/question/engine/lib.php");
        $aggregate = [];
        foreach ($attempts as $attempt) {
            $usage = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);
            foreach (array_slice($usage->get_slots(), 0, 100) as $slot) {
                $fraction = $usage->get_question_fraction($slot);
                if ($fraction === null) {
                    continue;
                }
                $question = $usage->get_question_attempt($slot);
                $key = (int)$question->get_question_id();
                if (!isset($aggregate[$key])) {
                    $aggregate[$key] = ["questionid" => $key, "count" => 0,
                        "sum" => 0.0, "values" => [], "grades" => []];
                }
                $aggregate[$key]["count"]++;
                $aggregate[$key]["sum"] += (float)$fraction;
                $aggregate[$key]["values"][] = (float)$fraction;
                $aggregate[$key]["grades"][] = (float)$attempt->sumgrades;
            }
        }
        $rows = [];
        foreach ($aggregate as $data) {
            $n = $data["count"];
            if ($n < 5) {
                continue;
            }
            $xmean = array_sum($data["values"]) / $n;
            $ymean = array_sum($data["grades"]) / $n;
            $xy = 0.0;
            $x2 = 0.0;
            $y2 = 0.0;
            foreach ($data["values"] as $i => $v) {
                $dx = $v - $xmean;
                $dy = $data["grades"][$i] - $ymean;
                $xy += $dx * $dy;
                $x2 += $dx * $dx;
                $y2 += $dy * $dy;
            }
            $rows[] = ["questionid" => $data["questionid"], "attempts" => $n,
                "mean_fraction" => $xmean,
                "raw_correlation_with_total" => $x2 && $y2 ? $xy / sqrt($x2 * $y2) : null];
        }
        return ["quizid" => (int)$quiz->id, "questions" => $rows, "sample_limit" => 500,
            "low_sample_suppressed" => false,
            "method" => "sampled_raw_correlation_not_corrected_discrimination"];
    }
}
