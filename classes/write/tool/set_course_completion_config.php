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
 * Safely enable course completion tracking with confirmation.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;

use context;
use context_course;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;

/**
 * Enable completion tracking without disabling or rewriting any student progress.
 */
final class set_course_completion_config extends base_tool {
    public function get_name(): string {
        return "set_course_completion_config";
    }

    public function get_title(): string {
        return "Enable course completion tracking";
    }

    public function get_description(): string {
        return "Enable Moodle course completion tracking using core course update API, with optimistic concurrency, preview and explicit confirmation. Never disables completion or resets student history.";
    }

    public function get_required_capability(): string {
        return "moodle/course:update";
    }

    public function get_input_schema(): array {
        return $this->object_schema([
            "courseid" => ["type" => "integer", "minimum" => 2],
            "expected_enablecompletion" => ["type" => "integer", "enum" => [0, 1]],
        ], ["courseid", "expected_enablecompletion"]);
    }

    public function resolve_context(array $arguments): context {
        return context_course::instance((int)$arguments["courseid"], MUST_EXIST);
    }

    private function checked(array $arguments): \stdClass {
        global $CFG;
        $course = get_course((int)$arguments["courseid"]);
        if (empty($CFG->enablecompletion)) {
            throw new api_exception("Site completion tracking must be enabled first", 409);
        }
        if ((int)$course->enablecompletion !== (int)$arguments["expected_enablecompletion"]) {
            throw new api_exception("completion_settings_changed", 409);
        }
        return $course;
    }

    public function preview(array $arguments, authenticated_identity $identity): array {
        $course = $this->checked($arguments);
        return ["action" => "enable_course_completion_tracking", "courseid" => (int)$course->id,
            "before" => (int)$course->enablecompletion, "after" => 1,
            "existing_progress_preserved" => true, "requires_explicit_confirmation" => true];
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        global $CFG;
        $course = $this->checked($arguments);
        if (empty($course->enablecompletion)) {
            require_once($CFG->dirroot . "/course/lib.php");
            update_course((object)["id" => (int)$course->id, "enablecompletion" => 1]);
        }
        return ["courseid" => (int)$course->id,
            "course_enabled" => !empty(get_course((int)$course->id)->enablecompletion)];
    }
}
