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
 * Validate tool resource identifiers before Moodle context resolution.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\security;

use context_module;
use local_mcp\exception\api_exception;
use stdClass;

/**
 * Convert invalid client identifiers into stable API errors without masking internal failures.
 */
final class input_guard {
    /**
     * Fetch an existing Moodle record, or reject the supplied identifier.
     *
     * @param string $table Moodle table name.
     * @param int $id Record identifier.
     * @param string $code Machine-readable error.
     * @return stdClass
     */
    public static function record(string $table, int $id, string $code = "resource_not_found"): stdClass {
        global $DB;
        $record = $DB->get_record($table, ["id" => $id]);
        if (!$record) {
            throw new api_exception($code, 404);
        }
        return $record;
    }

    /**
     * Resolve a course module and enforce its actual plugin type.
     *
     * @param int $cmid Course module ID.
     * @param string $module Required module type.
     * @return context_module
     */
    public static function module(int $cmid, string $module): context_module {
        $cm = get_coursemodule_from_id($module, $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            throw new api_exception("activity_not_found", 404);
        }
        return context_module::instance($cm->id, MUST_EXIST);
    }

    /**
     * Resolve an activity instance to its course module.
     *
     * @param string $module Moodle activity name.
     * @param int $instanceid Activity instance ID.
     * @param int $courseid Owning course ID.
     * @return context_module
     */
    public static function instance(string $module, int $instanceid, int $courseid): context_module {
        $cm = get_coursemodule_from_instance($module, $instanceid, $courseid, false, IGNORE_MISSING);
        if (!$cm) {
            throw new api_exception("activity_not_found", 404);
        }
        return context_module::instance($cm->id, MUST_EXIST);
    }
}
