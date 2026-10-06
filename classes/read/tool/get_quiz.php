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
 * get_quiz.php
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
 * Class get_quiz.
 */
final class get_quiz extends base_tool {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string {
        return 'get_quiz';
    }

    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string {
        return 'Get quiz';
    }

    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string {
        return 'Return quiz metadata.';
    }

    /**
     * Method get_required_capability.
     *
     * @return string Return value.
     */
    public function get_required_capability(): string {
        return 'mod/quiz:view';
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
        $q = $DB->get_record('quiz', ['id' => $cm->instance], 'id,name,intro,introformat,timeopen,timeclose,timelimit,grade,attempts', MUST_EXIST);
        return ['cmid' => (int)$cm->id, 'id' => (int)$q->id, 'name' => $q->name, 'intro' => format_module_intro('quiz', $q, $cm->id),
            'timeopen' => (int)$q->timeopen, 'timeclose' => (int)$q->timeclose, 'timelimit' => (int)$q->timelimit, 'grade' => $q->grade, 'attempts' => (int)$q->attempts];
    }
}
