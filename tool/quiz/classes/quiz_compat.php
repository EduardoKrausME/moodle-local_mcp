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
 * Version-specific Quiz APIs in one place, shared by the MCP quiz tools.
 *
 * @package mcptool_quiz
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mcptool_quiz;

final class quiz_compat {
    /**
     * mod_quiz\quiz_settings is available from Moodle 4.2 onwards.
     *
     * @param int $quizid Native quiz ID.
     * @return \mod_quiz\structure
     */
    public static function structure(int $quizid): \mod_quiz\structure {
        global $CFG;
        if (class_exists(\mod_quiz\quiz_settings::class)) {
            return \mod_quiz\structure::create_for_quiz(
                \mod_quiz\quiz_settings::create($quizid));
        }
        require_once($CFG->dirroot . '/mod/quiz/attemptlib.php');
        return \mod_quiz\structure::create_for_quiz(\quiz::create($quizid));
    }

    /**
     * Recalculate total quiz marks using the native API of the installed version.
     */
    public static function recompute_sumgrades(int $quizid): void {
        global $CFG, $DB;
        if (class_exists(\mod_quiz\quiz_settings::class)) {
            \mod_quiz\quiz_settings::create($quizid)->get_grade_calculator()->recompute_quiz_sumgrades();
            return;
        }
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
        quiz_update_sumgrades($quiz);
    }
    /**
     * Update the one student's final grade through the version's native Quiz API.
     */
    public static function recompute_final_grade(int $quizid, int $userid): void {
        global $CFG, $DB;
        if (class_exists(\mod_quiz\quiz_settings::class)) {
            \mod_quiz\quiz_settings::create($quizid)->get_grade_calculator()
                ->recompute_final_grade($userid);
            return;
        }
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
        quiz_save_best_grade($quiz, $userid);
    }
}
