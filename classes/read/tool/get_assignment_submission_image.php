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
 * Assignment submission and feedback access for MCP tools.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\read\tool;

use context;
use context_module;
use local_mcp\assignment\feedback_service;
use local_mcp\security\authenticated_identity;

/**
 * Expose authorized assignment image submissions as native MCP image content.
 */
final class get_assignment_submission_image extends base_tool {
    public function get_name(): string {
        return "get_assignment_submission_image";
    }

    public function get_title(): string {
        return "Get assignment submission image";
    }

    public function get_description(): string {
        return "Return a PNG, JPEG, GIF or WebP image submitted to an assignment as visual MCP "
            . "content, limited to 3 MB. File id comes from get_assignment_feedback_context. "
            . "The grader must have access to the exact student's submission.";
    }

    public function get_required_capability(): string {
        return "mod/assign:grade";
    }

    public function get_input_schema(): array {
        return $this->object_schema([
            "cmid" => ["type" => "integer", "minimum" => 1],
            "userid" => ["type" => "integer", "minimum" => 1],
            "fileid" => ["type" => "integer", "minimum" => 1],
        ], ["cmid", "userid", "fileid"]);
    }

    public function resolve_context(array $arguments): context {
        return context_module::instance((int)$arguments["cmid"], MUST_EXIST);
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        return feedback_service::submission_image((int)$arguments["cmid"], (int)$arguments["userid"],
            (int)$arguments["fileid"], $identity);
    }
}
