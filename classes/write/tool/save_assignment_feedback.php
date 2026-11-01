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
 * Persist reviewed assignment feedback with confirmation.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;

use context;
use context_module;
use local_mcp\assignment\feedback_service;
use local_mcp\security\authenticated_identity;

/**
 * Save teacher-approved feedback without automatically setting or publishing grades.
 */
final class save_assignment_feedback extends base_tool {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string {
        return "save_assignment_feedback";
    }

    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string {
        return "Save assignment feedback";
    }

    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string {
        return "Write reviewed plain-text feedback for one submitted assignment. "
            . "Requires confirmation, matching submission timestamp and current feedback hash; "
            . "does not change the numeric grade.";
    }

    /**
     * Method get_required_capability.
     *
     * @return string Return value.
     */
    public function get_required_capability(): string {
        return "mod/assign:grade";
    }

    /**
     * Method get_input_schema.
     *
     * @return array Return value.
     */
    public function get_input_schema(): array {
        return $this->object_schema([
            "cmid" => ["type" => "integer", "minimum" => 1],
            "userid" => ["type" => "integer", "minimum" => 1],
            "feedback" => ["type" => "string", "minLength" => 1, "maxLength" => 30000],
            "expected_submission_timemodified" => ["type" => "integer", "minimum" => 0],
            "expected_feedback_sha256" => ["type" => "string", "pattern" => "^[a-f0-9]{64}$"],
        ], ["cmid", "userid", "feedback", "expected_submission_timemodified", "expected_feedback_sha256"]);
    }

    /**
     * Method resolve_context.
     *
     * @param array $arguments Parameter arguments.
     * @return context Return value.
     */
    public function resolve_context(array $arguments): context {
        return context_module::instance((int)$arguments["cmid"], MUST_EXIST);
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
        [$assign, $submission, $current] = feedback_service::verified_feedback_target($arguments, $identity);
        return [
            "operation" => $this->get_name(),
            "cmid" => (int)$arguments["cmid"],
            "userid" => (int)$arguments["userid"],
            "submissionid" => (int)$submission->id,
            "existing_feedback" => $current["text"],
            "proposed_feedback" => $arguments["feedback"],
            "grade_unchanged" => true,
            "requires_teacher_approval" => true,
        ];
    }

    /**
     * Method execute.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        return feedback_service::save_feedback($arguments, $identity);
    }
}
