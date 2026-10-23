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
 * MCP assignment save grade and feedback tool.
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
 * Save simple grade and plain-text feedback in one native assignment operation.
 */
final class save_grade_and_feedback extends \local_mcp\write\tool\base_tool {
    public function get_name(): string {
        return "assign_save_grade_and_feedback";
    }

    public function get_title(): string {
        return "Save grade and feedback";
    }

    public function get_description(): string {
        return "Save simple grade and plain-text feedback in one native assignment operation.";
    }

    public function get_required_capability(): string {
        return "mod/assign:grade";
    }

    public function get_input_schema(): array {
        return $this->object_schema(["cmid" => ["type" => "integer", "minimum" => 1], "userid" => ["type" => "integer", "minimum" => 1], "expected_submission_timemodified" => ["type" => "integer", "minimum" => 0], "grade" => ["type" => "number", "minimum" => 0], "feedback" => ["type" => "string", "minLength" => 1, "maxLength" => 30000], "expected_feedback_sha256" => ["type" => "string", "pattern" => "^[a-f0-9]{64}$"]], ["cmid", "userid", "expected_submission_timemodified", "grade", "feedback", "expected_feedback_sha256"]);
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
        return service::grade_feedback($arguments, $identity);
    }
}
