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
 * Set activity access restrictions tool.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;

use context;
use context_module;
use local_mcp\course\activity_settings_service;
use local_mcp\security\authenticated_identity;

/**
 * Replace or clear AND conditions for dates, group, completion and grade.
 */
final class set_activity_restrictions extends base_tool {
    public function get_name(): string {
        return "set_activity_restrictions";
    }

    public function get_title(): string {
        return "Set activity access restrictions";
    }

    public function get_description(): string {
        return "Replace or clear AND conditions for dates, group, completion and grade.";
    }

    public function get_required_capability(): string {
        return "moodle/course:manageactivities";
    }

    public function get_input_schema(): array {
        return $this->object_schema(["cmid" => ["type" => "integer", "minimum" => 1], "mode" => ["type" => "string", "enum" => ["replace", "clear"]], "expected_availability_sha256" => ["type" => "string", "pattern" => "^[a-f0-9]{64}$"], "conditions" => ["type" => "array", "minItems" => 1, "maxItems" => 20, "items" => ["type" => "object", "properties" => ["type" => ["type" => "string", "enum" => ["date", "group", "completion", "grade"]], "direction" => ["type" => "string", "enum" => ["from", "until"]], "timestamp" => ["type" => "integer", "minimum" => 1], "groupid" => ["type" => "integer", "minimum" => 1], "cmid" => ["type" => "integer", "minimum" => 1], "state" => ["type" => "string", "enum" => ["complete", "incomplete", "passed", "failed"]], "gradeitemid" => ["type" => "integer", "minimum" => 1], "min" => ["type" => "number", "minimum" => 0, "maximum" => 100], "max" => ["type" => "number", "minimum" => 0, "maximum" => 100]], "required" => ["type"], "additionalProperties" => false]]], ["cmid", "mode", "expected_availability_sha256"]);
    }

    public function resolve_context(array $arguments): context {
        return context_module::instance(
            (int)$arguments["cmid"], MUST_EXIST);
    }

    public function supports_dry_run(): bool {
        return true;
    }

    public function preview(array $arguments, authenticated_identity $identity): array {
        return activity_settings_service::restrictions_preview($arguments, $identity);
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        return activity_settings_service::set_restrictions($arguments, $identity);
    }
}
