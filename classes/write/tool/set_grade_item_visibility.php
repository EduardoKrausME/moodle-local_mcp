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
 * Set grade item visibility MCP tool.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;

use context;
use context_course;
use local_mcp\grades\gradebook_service;
use local_mcp\security\authenticated_identity;

/**
 * Hide or reveal an activity gradebook item via native grade_item without altering grade values.
 */
final class set_grade_item_visibility extends base_tool {
    public function get_name(): string {
        return "set_grade_item_visibility";
    }

    public function get_title(): string {
        return "Set grade item visibility";
    }

    public function get_description(): string {
        return "Hide or reveal an activity gradebook item via native grade_item without altering grade values.";
    }

    public function get_required_capability(): string {
        return "moodle/grade:hide";
    }

    public function get_input_schema(): array {
        return $this->object_schema(["courseid" => ["type" => "integer", "minimum" => 1], "itemid" => ["type" => "integer", "minimum" => 1], "hidden" => ["type" => "boolean"], "expected_item_sha256" => ["type" => "string", "pattern" => "^[a-f0-9]{64}$"]], ["courseid", "itemid", "hidden", "expected_item_sha256"]);
    }

    public function resolve_context(array $arguments): context {
        return context_course::instance((int)$arguments["courseid"], MUST_EXIST);
    }

    public function supports_dry_run(): bool {
        return true;
    }

    public function preview(array $arguments, authenticated_identity $identity): array {
        return gradebook_service::visibility_preview($arguments, $identity);
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        return gradebook_service::set_visibility($arguments, $identity);
    }
}
