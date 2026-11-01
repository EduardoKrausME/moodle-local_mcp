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
 * Add group members tool.
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
 * Add up to 50 actively enrolled users to an existing course group.
 */
final class add_group_members extends base_tool {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string {
        return "add_group_members";
    }

    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string {
        return "Add group members";
    }

    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string {
        return "Add up to 50 actively enrolled users to an existing course group.";
    }

    /**
     * Method get_required_capability.
     *
     * @return string Return value.
     */
    public function get_required_capability(): string {
        return "moodle/course:managegroups";
    }

    /**
     * Method get_input_schema.
     *
     * @return array Return value.
     */
    public function get_input_schema(): array {
        return $this->object_schema(["courseid" => ["type" => "integer", "minimum" => 1], "groupid" => ["type" => "integer", "minimum" => 1], "userids" => ["type" => "array", "minItems" => 1, "maxItems" => 50, "items" => ["type" => "integer", "minimum" => 1], "uniqueItems" => true], "expected_members_sha256" => ["type" => "string", "pattern" => "^[a-f0-9]{64}$"]], ["courseid", "groupid", "userids", "expected_members_sha256"]);
    }

    /**
     * Method resolve_context.
     *
     * @param array $arguments Parameter arguments.
     * @return context Return value.
     */
    public function resolve_context(array $arguments): context {
        return context_course::instance(
            (int)$arguments["courseid"], MUST_EXIST);
    }

    /**
     * Method supports_dry_run.
     *
     * @return bool Return value.
     */
    public function supports_dry_run(): bool {
        return true;
    }

    /**
     * Method preview.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        return group_service::change_preview($arguments, $identity, false);
    }

    /**
     * Method execute.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        return group_service::change($arguments, $identity, false);
    }
}
