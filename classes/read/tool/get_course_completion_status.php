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
 * Get user course completion status MCP tool.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\read\tool;

use context;
use context_course;
use local_mcp\completion\completion_report_service;
use local_mcp\security\authenticated_identity;

/**
 * Read course completion, criterion timestamps and visible activity states for a specific enrollee.
 */
final class get_course_completion_status extends base_tool {
    public function get_name(): string {
        return "get_course_completion_status";
    }

    public function get_title(): string {
        return "Get user course completion status";
    }

    public function get_description(): string {
        return "Read course completion, criterion timestamps and visible activity states for a specific enrollee.";
    }

    public function get_required_capability(): string {
        return "moodle/course:view";
    }

    public function get_input_schema(): array {
        return $this->object_schema(["courseid" => ["type" => "integer", "minimum" => 1], "userid" => ["type" => "integer", "minimum" => 1]], ["courseid", "userid"]);
    }

    public function resolve_context(array $arguments): context {
        return context_course::instance((int)$arguments["courseid"], MUST_EXIST);
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        return completion_report_service::user_status($arguments, $identity);
    }
}
