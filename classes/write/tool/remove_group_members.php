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
 * Remove group members tool.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;

use context;
use context_course;
use local_mcp\course\group_service;
use local_mcp\security\authenticated_identity;

/**
 * Remove up to 50 users from a group, respecting externally managed membership.
 */
final class remove_group_members extends base_tool {
    public function get_name(): string {
        return "remove_group_members";
    }

    public function get_title(): string {
        return "Remove group members";
    }

    public function get_description(): string {
        return "Remove up to 50 users from a group, respecting externally managed membership.";
    }

    public function get_required_capability(): string {
        return "moodle/course:managegroups";
    }

    public function get_input_schema(): array {
        return $this->object_schema(["courseid" => ["type" => "integer", "minimum" => 1], "groupid" => ["type" => "integer", "minimum" => 1], "userids" => ["type" => "array", "minItems" => 1, "maxItems" => 50, "items" => ["type" => "integer", "minimum" => 1], "uniqueItems" => true], "expected_members_sha256" => ["type" => "string", "pattern" => "^[a-f0-9]{64}$"]], ["courseid", "groupid", "userids", "expected_members_sha256"]);
    }

    public function resolve_context(array $arguments): context {
        return context_course::instance(
            (int)$arguments["courseid"], MUST_EXIST);
    }

    public function supports_dry_run(): bool {
        return true;
    }

    public function preview(array $arguments, authenticated_identity $identity): array {
        return group_service::change_preview($arguments, $identity, true);
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        return group_service::change($arguments, $identity, true);
    }
}
