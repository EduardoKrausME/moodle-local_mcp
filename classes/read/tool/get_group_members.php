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
 * List group members tool.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\read\tool;

use context;
use context_course;
use local_mcp\course\group_service;
use local_mcp\security\authenticated_identity;

/**
 * Read authorized group members and a membership fingerprint.
 */
final class get_group_members extends base_tool {
    public function get_name(): string {
        return "get_group_members";
    }

    public function get_title(): string {
        return "List group members";
    }

    public function get_description(): string {
        return "Read authorized group members and a membership fingerprint.";
    }

    public function get_required_capability(): string {
        return "moodle/course:managegroups";
    }

    public function get_input_schema(): array {
        return $this->object_schema(["courseid" => ["type" => "integer", "minimum" => 1], "groupid" => ["type" => "integer", "minimum" => 1], "limit" => ["type" => "integer", "minimum" => 1, "maximum" => 50], "offset" => ["type" => "integer", "minimum" => 0]], ["courseid", "groupid"]);
    }

    public function resolve_context(array $arguments): context {
        return context_course::instance(
            (int)$arguments["courseid"], MUST_EXIST);
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        return group_service::members($arguments, $identity);
    }
}
