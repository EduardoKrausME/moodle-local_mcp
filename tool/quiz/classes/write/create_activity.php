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
 * Create a quiz course module.
 *
 * @package   mcptool_quiz
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_quiz\write;

use local_mcp\security\authenticated_identity;
use local_mcp\write\tool\activity_create_base;
use stdClass;

/** Moodle activity creation via standard module hooks. */
final class create_activity extends activity_create_base {
    protected function module_name(): string { return 'quiz'; }
    protected function extra_properties(): array {
        return [
            'timeopen' => ['type' => 'integer', 'minimum' => 0],
            'timeclose' => ['type' => 'integer', 'minimum' => 0],
            'timelimit' => ['type' => 'integer', 'minimum' => 0],
            'attempts' => ['type' => 'integer', 'minimum' => 0],
            'grade' => ['type' => 'number', 'minimum' => 0],
            'questionsperpage' => ['type' => 'integer', 'minimum' => 1],
        ];
    }
    protected function configure(stdClass $info, array $a, authenticated_identity $identity): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/lib.php');
        $info->timeopen = (int)($a['timeopen'] ?? 0);
        $info->timeclose = (int)($a['timeclose'] ?? 0);
        if ($info->timeclose && $info->timeopen && $info->timeclose <= $info->timeopen) {
            throw new \local_mcp\exception\api_exception('invalid_quiz_dates', 400);
        }
        $info->timelimit = (int)($a['timelimit'] ?? 0);
        $info->attempts = (int)($a['attempts'] ?? 0);
        $info->grade = (float)($a['grade'] ?? $CFG->gradepointdefault);
        $info->questionsperpage = (int)($a['questionsperpage'] ?? 1);
        $info->preferredbehaviour = 'deferredfeedback';
        $info->grademethod = QUIZ_GRADEHIGHEST;
        $info->navmethod = 'free';
        $info->overduehandling = 'autosubmit';
        $info->graceperiod = 0;
        $info->quizpassword = '';
        $info->subnet = '';
        $info->browsersecurity = '-';
        $info->delay1 = 0;
        $info->delay2 = 0;
        $info->showuserpicture = 0;
    }
}
