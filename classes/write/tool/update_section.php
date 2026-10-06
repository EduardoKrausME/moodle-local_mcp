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
 * update_section.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;
use context;
use context_course;
use local_mcp\security\authenticated_identity;
use stdClass;

defined('MOODLE_INTERNAL') || die;

/**
 * Class update_section.
 */
final class update_section extends base_tool {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string {
        return 'update_section';
    }

    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string {
        return 'Update section';
    }

    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string {
        return 'Update course section metadata.';
    }

    /**
     * Method get_required_capability.
     *
     * @return string Return value.
     */
    public function get_required_capability(): string {
        return 'moodle/course:update';
    }

    /**
     * Method get_input_schema.
     *
     * @return array Return value.
     */
    public function get_input_schema(): array {
        return $this->object_schema([
            'courseid' => ['type' => 'integer', 'minimum' => 1], 'sectionnum' => ['type' => 'integer', 'minimum' => 0], 'name' => ['type' => 'string'], 'visible' => ['type' => 'boolean']
        ], ['courseid', 'sectionnum']);
    }

    /**
     * Method resolve_context.
     *
     * @param array $arguments Parameter arguments.
     * @return context Return value.
     */
    public function resolve_context(array $arguments): context {
        return context_course::instance((int)$arguments['courseid'], MUST_EXIST);
    }

    /**
     * Method execute.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $data = new stdClass();
        if (isset($arguments['name'])) {
            $data->name = clean_param($arguments['name'], PARAM_TEXT);
        }
        if (array_key_exists('visible', $arguments)) {
            $data->visible = (int)(bool)$arguments['visible'];
        }
        course_update_section((int)$arguments['courseid'], (int)$arguments['sectionnum'], $data);
        return ['updated' => true, 'courseid' => (int)$arguments['courseid'], 'sectionnum' => (int)$arguments['sectionnum']];
    }
}
