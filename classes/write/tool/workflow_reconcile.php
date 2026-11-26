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
 * Confirm recognition of an already satisfied workflow postcondition.
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
use local_mcp\workflow\reconciliation_service;
use local_mcp\workflow\sequence;

/** Recognize existing configuration; do not run or authorize the original WRITE. */
final class workflow_reconcile extends base_tool {
    /** @return string */
    public function get_name(): string {
        return "workflow_reconcile";
    }

    /** @return string */
    public function get_title(): string {
        return "Reconcile an independently completed workflow step";
    }

    /** @return string */
    public function get_description(): string {
        return "Verify an existing server-bound activity completion configuration and, after explicit confirmation, "
            . "record the current workflow step as reconciled. Does not change activity settings, grades, "
            . "student completion records or course criteria. Never executes the original operation or authorizes "
            . "the next one. Only supported conditions recorded by an official preview in a new workflow are eligible.";
    }

    /** @return string */
    public function get_required_capability(): string {
        return "moodle/course:update";
    }

    /** @return array */
    public function get_input_schema(): array {
        return $this->object_schema([
            "workflow_id" => ["type" => "string", "pattern" => "^[a-f0-9]{24}$"],
            "courseid" => ["type" => "integer", "minimum" => 1],
            "expectedstep" => ["type" => "integer", "minimum" => 1],
            "expectedgeneration" => ["type" => "integer", "minimum" => 0],
        ], ["workflow_id", "courseid", "expectedstep", "expectedgeneration"]);
    }

    /**
     * @param array $arguments Tool arguments.
     * @return context
     */
    public function resolve_context(array $arguments): context {
        return context_course::instance((int)$arguments["courseid"], MUST_EXIST);
    }

    /**
     * @param array $arguments Tool arguments.
     * @param authenticated_identity $identity Connection.
     * @return array
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        return reconciliation_service::preview($arguments["workflow_id"], $identity, (int)$arguments["courseid"],
            (int)$arguments["expectedstep"], (int)$arguments["expectedgeneration"]);
    }

    /**
     * @param array $arguments Tool arguments plus internally bound snapshot hash.
     * @param authenticated_identity $identity Connection.
     * @return array
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        if (empty($arguments["_expected_reconciliation_sha256"])) {
            throw new api_exception("workflow_postcondition_unavailable", 409, "", [],
                        get_string("workflowpostconditionunavailable", "local_mcp"));
        }
        return sequence::reconcile($arguments["workflow_id"], $identity, (int)$arguments["courseid"],
            (int)$arguments["expectedstep"], (int)$arguments["expectedgeneration"],
            $arguments["_expected_reconciliation_sha256"]);
    }
}
