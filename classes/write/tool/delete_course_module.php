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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Confirmed, identity-checked deletion of an individual course module.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;

use context;
use context_course;
use context_module;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;

/**
 * Delete only the exact module reviewed during confirmation.
 */
final class delete_course_module extends base_tool {
    public function get_name(): string {
        return "delete_course_module";
    }

    public function get_title(): string {
        return "Delete an explicitly verified course module";
    }

    public function get_description(): string {
        return "Permanently remove one course module after confirming its course, section, module type, "
            . "and exact name. Requires a fresh destructive-operation confirmation.";
    }

    public function get_required_capability(): string {
        return "moodle/course:manageactivities";
    }

    public function is_destructive(): bool {
        return true;
    }

    public function get_input_schema(): array {
        return $this->object_schema([
            "courseid" => ["type" => "integer", "minimum" => 1],
            "cmid" => ["type" => "integer", "minimum" => 1],
            "expectedsectionnum" => ["type" => "integer", "minimum" => 0],
            "expectedmodname" => ["type" => "string", "minLength" => 1],
            "expectedname" => ["type" => "string", "minLength" => 1],
        ], ["courseid", "cmid", "expectedsectionnum", "expectedmodname", "expectedname"]);
    }

    public function resolve_context(array $arguments): context {
        return context_course::instance((int)$arguments["courseid"], MUST_EXIST);
    }

    private function verified_module(array $a, authenticated_identity $identity): \cm_info {
        global $DB;
        $courseid = (int)$a["courseid"];
        $cmid = (int)$a["cmid"];

        $record = $DB->get_record("course_modules", ["id" => $cmid], "id, course", MUST_EXIST);
        if ((int)$record->course !== $courseid) {
            throw new api_exception("course_module_mismatch", 409);
        }
        if (!has_capability("moodle/course:manageactivities",
                context_module::instance($cmid, MUST_EXIST), $identity->userid)) {
            throw new api_exception("permission_denied", 403);
        }

        $cm = get_fast_modinfo($courseid)->get_cm($cmid);
        if ($cm->modname !== $a["expectedmodname"] ||
            $cm->name !== $a["expectedname"] ||
            (int)$cm->sectionnum !== (int)$a["expectedsectionnum"]) {
            throw new api_exception("course_module_changed", 409);
        }
        return $cm;
    }

    public function preview(array $arguments, authenticated_identity $identity): array {
        $cm = $this->verified_module($arguments, $identity);
        return [
            "operation" => $this->get_name(),
            "destructive" => true,
            "courseid" => (int)$arguments["courseid"],
            "cmid" => (int)$cm->id,
            "sectionnum" => (int)$cm->sectionnum,
            "modname" => $cm->modname,
            "name" => $cm->name,
        ];
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/course/lib.php");
        $cm = $this->verified_module($arguments, $identity);
        $cmid = (int)$cm->id;
        course_delete_module($cmid, false);
        if ($DB->record_exists("course_modules", ["id" => $cmid])) {
            throw new api_exception("course_module_deletion_incomplete", 500);
        }
        return ["deleted" => true, "courseid" => (int)$arguments["courseid"], "cmid" => $cmid];
    }
}
