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
 * get_attempts.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\read\tool;
use context;
use context_module;
use local_mcp\security\authenticated_identity;

defined('MOODLE_INTERNAL') || die;

/**
 * Class get_attempts.
 */
final class get_attempts extends base_tool {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string {
        return 'get_attempts';
    }

    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string {
        return 'Get quiz attempts';
    }

    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string {
        return 'Return quiz attempt metadata for reporting.';
    }

    /**
     * Method get_required_capability.
     *
     * @return string Return value.
     */
    public function get_required_capability(): string {
        return 'mod/quiz:viewreports';
    }

    /**
     * Method get_input_schema.
     *
     * @return array Return value.
     */
    public function get_input_schema(): array {
        return $this->object_schema(['cmid' => ['type' => 'integer', 'minimum' => 1]], ['cmid']);
    }

    /**
     * Method resolve_context.
     *
     * @param array $arguments Parameter arguments.
     * @return context Return value.
     */
    public function resolve_context(array $arguments): context {
        return context_module::instance((int)$arguments['cmid'], MUST_EXIST);
    }

    /**
     * Method execute.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        global $DB;
        [$course, $cm] = get_course_and_cm_from_cmid((int)$arguments['cmid'], 'quiz');
        $rows = $DB->get_records('quiz_attempts', ['quiz' => $cm->instance], 'timestart DESC', 'id,userid,attempt,state,timestart,timefinish,sumgrades');
        $out = [];
        foreach ($rows as $a) {
            $out[] = ['id' => (int)$a->id, 'userid' => (int)$a->userid, 'attempt' => (int)$a->attempt, 'state' => $a->state,
                'timestart' => (int)$a->timestart, 'timefinish' => (int)$a->timefinish, 'sumgrades' => $a->sumgrades];
        }
        return ['attempts' => $out];
    }
}
