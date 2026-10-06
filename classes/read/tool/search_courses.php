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
 * search_courses.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\read\tool;

use context;
use context_course;
use context_system;
use local_mcp\security\authenticated_identity;

defined('MOODLE_INTERNAL') || die;

/**
 * Class search_courses.
 */
final class search_courses extends base_tool {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string {
        return 'search_courses';
    }

    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string {
        return 'Search courses';
    }

    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string {
        return 'Search courses visible to the connected Moodle user.';
    }

    /**
     * Method get_required_capability.
     *
     * @return string Return value.
     */
    public function get_required_capability(): string {
        return 'moodle/course:view';
    }

    /**
     * Method get_input_schema.
     *
     * @return array Return value.
     */
    public function get_input_schema(): array {
        return $this->object_schema([
            'query' => ['type' => 'string'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
        ]);
    }

    /**
     * Method resolve_context.
     *
     * @param array $arguments Parameter arguments.
     * @return context Return value.
     */
    public function resolve_context(array $arguments): context {
        return context_system::instance();
    }

    /**
     * Method execute.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        $query = trim((string)($arguments['query'] ?? ''));
        $limit = min(100, max(1, (int)($arguments['limit'] ?? 20)));
        $out = [];
        foreach (get_courses('all', 'c.sortorder ASC', 'c.id,c.fullname,c.shortname,c.visible') as $course) {
            if ((int)$course->id === SITEID) {
                continue;
            }
            $ctx = context_course::instance($course->id);
            if (!has_capability('moodle/course:view', $ctx, $identity->userid)) {
                continue;
            }
            if ($query !== '' && stripos($course->fullname . ' ' . $course->shortname, $query) === false) {
                continue;
            }
            $out[] = ['id' => (int)$course->id, 'fullname' => $course->fullname, 'shortname' => $course->shortname, 'visible' => (bool)$course->visible];
            if (count($out) >= $limit) {
                break;
            }
        }
        return ['courses' => $out];
    }
}
