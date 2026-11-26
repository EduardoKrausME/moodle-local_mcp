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
 * Coordinate a confirmed, read-only verification of workflow conditions.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\workflow;

use context;
use context_course;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\security\capability_guard;
use local_mcp\security\confirmation_service;

/** Reconciliation changes workflow metadata only. */
final class reconciliation_service {
    /**
     * Capture from the original tool's official preview.
     *
     * @param array $arguments Original normalized arguments.
     * @param array $preview Server-generated preview.
     * @param authenticated_identity $identity Connection.
     * @param context $context Original module context.
     * @return array|null
     */
    public static function capture(array $arguments, array $preview, authenticated_identity $identity,
            context $context): ?array {
        return reconciliation_registry::get("activity_completion")->capture($arguments, $preview, $identity, $context);
    }

    /**
     * Verify a stored postcondition with current permissions.
     *
     * @param array $condition Stored condition.
     * @param authenticated_identity $identity Connection.
     * @param int $courseid Expected course.
     * @return array
     */
    public static function verify(array $condition, authenticated_identity $identity, int $courseid): array {
        if (!$identity->has_scope("mcp:write")) {
            throw new api_exception("insufficient_scope", 403);
        }
        capability_guard::check("moodle/course:update", context_course::instance($courseid), $identity->userid);
        $verifier = reconciliation_registry::get($condition["type"] ?? "");
        if ($verifier === null) {
            throw new api_exception("workflow_postcondition_unavailable", 409);
        }
        return $verifier->verify($condition, $identity, $courseid);
    }

    /**
     * Return only a verified matching snapshot; mismatches never issue approval tokens.
     *
     * @param string $id Workflow ID.
     * @param authenticated_identity $identity Connection.
     * @param int $courseid Course ID.
     * @param int $step Expected stage.
     * @param int $generation Expected generation.
     * @return array
     */
    public static function preview(string $id, authenticated_identity $identity, int $courseid,
            int $step, int $generation): array {
        $candidate = sequence::reconciliation_candidate($id, $identity, $courseid, $step, $generation);
        $condition = $candidate["condition"];
        $actual = self::verify($condition, $identity, $courseid);
        return [
            "reconciliation_possible" => true,
            "cmid" => $actual["cmid"],
            "contextid" => $condition["contextid"],
            "workflow_step" => $step,
            "workflow_generation" => $generation,
            "expected" => $condition["expected"],
            "actual" => $actual,
            "activity_changes" => false,
            "academic_records_changed" => false,
            "reconciliation_sha256" => confirmation_service::args_hash([
                "workflow_id" => $id,
                "step" => $step,
                "generation" => $generation,
                "state" => $candidate["state"],
                "condition" => $condition,
                "actual" => $actual,
            ]),
        ];
    }
}
