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
 * Summarize submissions awaiting assignment feedback.
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
 * List small batches of eligible submissions without leaking learner content.
 */
final class get_assignment_feedback_queue extends base_tool {
    public function get_name(): string {
        return "get_assignment_feedback_queue";
    }

    public function get_title(): string {
        return "List submitted work for feedback";
    }

    public function get_description(): string {
        return "List submitted work and existing feedback state for a limited set of students. "
            . "Per-student group and anonymous-grading restrictions apply.";
    }

    public function get_required_capability(): string {
        return "mod/assign:grade";
    }

    public function get_input_schema(): array {
        return $this->object_schema([
            "cmid" => ["type" => "integer", "minimum" => 1],
            "limit" => ["type" => "integer", "minimum" => 1, "maximum" => 50],
            "offset" => ["type" => "integer", "minimum" => 0],
        ], ["cmid"]);
    }

    public function resolve_context(array $arguments): context {
        return context_module::instance((int)$arguments["cmid"], MUST_EXIST);
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/assign/locallib.php");
        [$course, $cm] = get_course_and_cm_from_cmid((int)$arguments["cmid"], "assign");
        $context = context_module::instance($cm->id);
        $instance = $DB->get_record("assign", ["id" => $cm->instance], "*", MUST_EXIST);
        if ((!empty($instance->blindmarking) && empty($instance->revealidentities))
                || !empty($instance->teamsubmission)) {
            return ["submissions" => [], "unsupported" => "blind_or_team_submissions"];
        }
        $limit = (int)($arguments["limit"] ?? 20);
        $offset = (int)($arguments["offset"] ?? 0);
        $submissions = $DB->get_records("assign_submission", [
            "assignment" => $instance->id,
            "status" => ASSIGN_SUBMISSION_STATUS_SUBMITTED,
            "latest" => 1,
            "groupid" => 0,
        ], "timemodified DESC, id DESC", "id,userid,timemodified", $offset, $limit);
        $result = [];
        foreach ($submissions as $submission) {
            try {
                [$assign, $checkedcm, $current] = feedback_service::target(
                    (int)$cm->id, (int)$submission->userid, $identity
                );
            } catch (\local_mcp\exception\api_exception $exception) {
                continue;
            }
            $feedback = feedback_service::current_feedback($assign, (int)$submission->userid);
            $result[] = [
                "userid" => (int)$submission->userid,
                "submissionid" => (int)$current->id,
                "timemodified" => (int)$current->timemodified,
                "has_feedback" => $feedback["has_feedback"],
            ];
        }
        return ["cmid" => (int)$cm->id, "submissions" => $result, "limit" => $limit,
            "offset" => $offset, "next_offset" => $offset + count($submissions)];
    }
}
