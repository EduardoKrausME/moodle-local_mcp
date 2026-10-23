<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
//
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Get gradebook structure MCP tool.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\read\tool;

use context;
use context_course;
use local_mcp\grades\gradebook_service;
use local_mcp\security\authenticated_identity;

/**
 * Read grade categories, grade item settings, pass grades and visibility without student grades.
 */
final class get_gradebook_structure extends base_tool {
    public function get_name(): string {
        return "get_gradebook_structure";
    }

    public function get_title(): string {
        return "Get gradebook structure";
    }

    public function get_description(): string {
        return "Read grade categories, grade item settings, pass grades and visibility without student grades.";
    }

    public function get_required_capability(): string {
        return "moodle/grade:manage";
    }

    public function get_input_schema(): array {
        return $this->object_schema(["courseid" => ["type" => "integer", "minimum" => 1], "limit" => ["type" => "integer", "minimum" => 1, "maximum" => 100], "offset" => ["type" => "integer", "minimum" => 0]], ["courseid"]);
    }

    public function resolve_context(array $arguments): context {
        return context_course::instance((int)$arguments["courseid"], MUST_EXIST);
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        return gradebook_service::structure($arguments, $identity);
    }
}
