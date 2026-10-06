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
 * get_calendar_events.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\read\tool;
use context;
use context_course;
use local_mcp\security\authenticated_identity;

defined('MOODLE_INTERNAL') || die;

/**
 * Class get_calendar_events.
 */
final class get_calendar_events extends base_tool {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string {
        return 'get_calendar_events';
    }

    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string {
        return 'Get calendar events';
    }

    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string {
        return 'Return course calendar events in a time range.';
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
            'courseid' => ['type' => 'integer', 'minimum' => 1], 'timestart' => ['type' => 'integer'], 'timeend' => ['type' => 'integer']
        ], ['courseid']);
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
        global $DB;
        $start = (int)($arguments['timestart'] ?? time());
        $end = (int)($arguments['timeend'] ?? ($start + 30 * DAYSECS));
        $rows = $DB->get_records_select('event', 'courseid = :courseid AND timestart >= :start AND timestart <= :end',
            ['courseid' => (int)$arguments['courseid'], 'start' => $start, 'end' => $end], 'timestart ASC',
            'id,name,eventtype,timestart,timeduration,modulename,instance');
        $out = [];
        foreach ($rows as $e) {
            $out[] = ['id' => (int)$e->id, 'name' => $e->name, 'eventtype' => $e->eventtype, 'timestart' => (int)$e->timestart,
                'timeduration' => (int)$e->timeduration, 'modulename' => $e->modulename, 'instance' => (int)$e->instance];
        }
        return ['events' => $out];
    }
}
