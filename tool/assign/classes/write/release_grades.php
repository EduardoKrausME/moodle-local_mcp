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
 * MCP assignment release grades tool.
 *
 * @package   mcptool_assign
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_assign\write;

use context;
use context_module;
use local_mcp\security\authenticated_identity;
use mcptool_assign\service;

/**
 * Release one previously graded assignment through Moodle marking workflow.
 */
final class release_grades extends \local_mcp\write\tool\base_tool {
    public function get_name(): string {
        return "assign_release_grades";
    }

    public function get_title(): string {
        return "Release reviewed grade";
    }

    public function get_description(): string {
        return "Release one previously graded assignment through Moodle marking workflow.";
    }

    public function get_required_capability(): string {
        return "mod/assign:releasegrades";
    }

    public function get_input_schema(): array {
        return $this->object_schema(["cmid" => ["type" => "integer", "minimum" => 1], "userid" => ["type" => "integer", "minimum" => 1], "expected_state" => ["type" => "string"]], ["cmid", "userid", "expected_state"]);
    }

    public function resolve_context(array $arguments): context {
        return context_module::instance((int)$arguments["cmid"], MUST_EXIST);
    }

    public function supports_dry_run(): bool {
        return true;
    }

    public function preview(array $arguments, authenticated_identity $identity): array {
        service::student((int)$arguments["cmid"],
            (int)$arguments["userid"], $identity);
        return ["operation" => $this->get_name(), "arguments" => $arguments,
            "requires_confirmation" => true];
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        $arguments["state"] = "released";
        return service::workflow($arguments, $identity);
    }
}
