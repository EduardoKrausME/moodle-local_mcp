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
 * Expose a staged workflow controller to MCP clients.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;

use context;
use context_course;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\workflow\sequence;
use local_mcp\workflow\approved_plan;

/**
 * Create and advance persisted workflows without performing course writes.
 */
final class workflow_sequence extends base_tool {
    /** @return string */
    public function get_name(): string {
        return "workflow_sequence";
    }

    /** @return string */
    public function get_title(): string {
        return "Manage sequential operations";
    }

    /** @return string */
    public function get_description(): string {
        return "For requests with multiple Moodle changes, start an ordered workflow with item titles and "
            . "the exact WRITE tool name for each item. Prepare and confirm only its current step; attach "
            . "workflow_id and workflow_step to the ordinary WRITE tool call. After a successful confirmed "
            . "WRITE, the result contains workflow progress and, on supported hosts, a Next button. "
            . "Calling next only advances the plan; it never authorizes or executes a Moodle WRITE. "
            . "Retry reopens only failed idempotent move_activity stages; a fresh preview and confirmation are required. "
            . "Optional single approval: action=authorize previews exact steps for all remaining supported "
            . "operations (create_section, update_section and append move_activity). Only retry authorize "
            . "with its confirmation_token after the teacher approves the complete preview. Then call "
            . "action=run_next with the returned step and generation, one at a time, without arguments "
            . "or new confirmations. Stop on any failure or changed state. "
            . "Status and cancel require the original course and connection. Never invent progress.";
    }

    /** @return string */
    public function get_required_capability(): string {
        return "moodle/course:update";
    }

    /** @return array */
    public function get_input_schema(): array {
        return $this->object_schema([
            "action" => ["type" => "string", "enum" => ["start", "status", "next", "cancel", "retry", "authorize", "run_next"]],
            "courseid" => ["type" => "integer", "minimum" => 1],
            "expectedstep" => ["type" => "integer", "minimum" => 1],
            "expectedgeneration" => ["type" => "integer", "minimum" => 0],
            "workflow_id" => ["type" => "string", "pattern" => "^[a-f0-9]{24}$"],
            "steps" => approved_plan::steps_schema(),
            "items" => [
                "type" => "array", "minItems" => 2, "maxItems" => sequence::MAX_ITEMS,
                "items" => ["type" => "object", "additionalProperties" => false,
                    "properties" => [
                        "title" => ["type" => "string", "minLength" => 1, "maxLength" => 160],
                        "tool" => ["type" => "string", "minLength" => 1],
                    ],
                    "required" => ["title", "tool"],
                ],
            ],
        ], ["action", "courseid"]);
    }

    /** @return context */
    public function resolve_context(array $arguments): context {
        return context_course::instance((int)$arguments["courseid"], MUST_EXIST);
    }

    /** @return bool */
    public function requires_confirmation(): bool {
        return false;
    }

    /** @return array */
    public function execute(array $arguments, authenticated_identity $identity): array {
        $courseid = (int)$arguments["courseid"];
        $action = (string)$arguments["action"];
        // Plan approval and execution require the authenticated protocol dispatcher.
        if (in_array($action, ["authorize", "run_next"], true) || isset($arguments["steps"])) {
            throw new api_exception("invalid_workflow", 400);
        }
        if ($action === "start") {
            if (!isset($arguments["items"]) || !is_array($arguments["items"]) ||
                    isset($arguments["workflow_id"])) {
                throw new api_exception("invalid_workflow_items", 400);
            }
            $workflow = sequence::start($identity, $courseid, $arguments["items"]);
        } else {
            if (!isset($arguments["workflow_id"]) || isset($arguments["items"])) {
                throw new api_exception("invalid_workflow", 400);
            }
            $id = $arguments["workflow_id"];
            if ($action === "cancel") {
                sequence::cancel($id, $identity, $courseid);
                return ["cancelled" => true, "workflow_id" => $id];
            }
            $step = $arguments["expectedstep"] ?? null;
            $generation = $arguments["expectedgeneration"] ?? null;
            $workflow = $action === "retry" ? sequence::retry($id, $identity, $courseid, $step, $generation)
                : ($action === "next" ? sequence::next($id, $identity, $courseid, $step, $generation)
                    : sequence::status($id, $identity, $courseid));
        }
        return ["workflow" => $workflow, "message" =>
            "Process only the current step. For course changes, show a fresh preview and obtain "
            . "explicit approval before executing. Next means move to the next plan item only."];
    }
}
