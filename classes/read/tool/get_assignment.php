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
 * get_assignment.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\read\tool;
use context;
use context_module;
use local_mcp\security\authenticated_identity;

/**
 * Class get_assignment.
 */
final class get_assignment extends base_tool {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string {
        return 'get_assignment';
    }

    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string {
        return 'Get assignment';
    }

    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string {
        return 'Return one assignment activity.';
    }

    /**
     * Method get_required_capability.
     *
     * @return string Return value.
     */
    public function get_required_capability(): string {
        return 'mod/assign:view';
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
        [$course, $cm] = get_course_and_cm_from_cmid((int)$arguments['cmid'], 'assign');
        global $DB;
        $a = $DB->get_record('assign', ['id' => $cm->instance], 'id,name,intro,introformat,duedate,cutoffdate,grade', MUST_EXIST);
        return ['cmid' => (int)$cm->id, 'id' => (int)$a->id, 'name' => $a->name,
            'intro' => format_module_intro('assign', $a, $cm->id), 'duedate' => (int)$a->duedate, 'cutoffdate' => (int)$a->cutoffdate, 'grade' => $a->grade];
    }
}
