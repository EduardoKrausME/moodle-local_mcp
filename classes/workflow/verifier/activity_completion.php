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
 * Verify native activity completion configuration without academic writes.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\workflow\verifier;

use context;
use context_module;
use local_mcp\exception\api_exception;
use local_mcp\extension\manager;
use local_mcp\security\authenticated_identity;
use local_mcp\security\capability_guard;
use local_mcp\security\confirmation_service;
use local_mcp\write\tool\set_activity_completion;

/** Initial verifier: automatic view-only completion, no student history. */
final class activity_completion implements verifier_interface {
    /**
     * Read supported configuration, including module identity and custom rules.
     *
     * @param int $cmid Activity ID.
     * @param authenticated_identity $identity Connection.
     * @return array|null
     */
    private function read_state(int $cmid, authenticated_identity $identity): ?array {
        global $CFG, $DB;
        $context = context_module::instance($cmid, MUST_EXIST);
        capability_guard::check("moodle/course:manageactivities", $context, $identity->userid);
        // Read flags and instance identity from the same database row.
        $cm = $DB->get_record("course_modules", ["id" => $cmid], "*", MUST_EXIST);
        $course = $DB->get_record("course", ["id" => $cm->course], "id, enablecompletion", MUST_EXIST);
        $modname = (string)$DB->get_field("modules", "name", ["id" => $cm->module], MUST_EXIST);
        if (empty($CFG->enablecompletion) || empty($course->enablecompletion) ||
                !empty($cm->deletioninprogress) ||
                $DB->record_exists("course_modules_completion", ["coursemoduleid" => $cmid]) ||
                !plugin_supports("mod", $modname, FEATURE_COMPLETION_TRACKS_VIEWS, false)) {
            return null;
        }
        // Unknown module-specific rules cannot be verified by comparing core flags.
        if (plugin_supports("mod", $modname, FEATURE_COMPLETION_HAS_RULES, false)) {
            if ($modname !== "mcp") {
                return null;
            }
            // Fail closed if a newer mod_mcp declares additional custom rules.
            if (class_exists('\core_completion\activity_custom_completion')) {
                $customclass = '\mod_mcp\completion\custom_completion';
                if (!is_callable([$customclass, 'get_defined_custom_rules']) ||
                        $customclass::get_defined_custom_rules() !== ["completioninteractive"]) {
                    return null;
                }
            }
            $instance = $DB->get_record("mcp", ["id" => $cm->instance], "*", MUST_EXIST);
            if (!property_exists($instance, "completioninteractive") || (int)$instance->completioninteractive !== 0) {
                return null;
            }
        }
        return [
            "cmid" => $cmid,
            "courseid" => (int)$course->id,
            "instanceid" => (int)$cm->instance,
            "modname" => $modname,
            "completion" => (int)$cm->completion,
            "completionview" => (int)$cm->completionview,
            "completiongradeitemnumber" => $cm->completiongradeitemnumber === null
                ? null : (int)$cm->completiongradeitemnumber,
            "completionpassgrade" => (int)($cm->completionpassgrade ?? 0),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function capture(array $arguments, array $preview, authenticated_identity $identity, context $context): ?array {
        $allowed = ["cmid", "completion", "expected_completion", "requireview", "requiregrade"];
        if (array_diff(array_keys($arguments), $allowed)) {
            return null;
        }
        $after = $preview["after"];
        if ((int)$after["completion"] !== COMPLETION_TRACKING_AUTOMATIC || (int)$after["completionview"] !== 1 ||
                $after["completiongradeitemnumber"] !== null || (int)$after["completionpassgrade"] !== 0) {
            return null;
        }
        $actual = $this->read_state((int)$preview["cmid"], $identity);
        $before = $preview["before"];
        $beforegrade = $before["completiongradeitemnumber"] === null
            ? null : (int)$before["completiongradeitemnumber"];
        if ($actual === null || $actual["completion"] !== (int)$arguments["expected_completion"] ||
                $actual["completion"] !== (int)$before["completion"] ||
                $actual["completionview"] !== (int)$before["completionview"] ||
                $actual["completiongradeitemnumber"] !== $beforegrade ||
                $actual["completionpassgrade"] !== (int)$before["completionpassgrade"]) {
            return null;
        }
        $expected = array_replace($actual, $after);
        // A pre-existing match is not evidence of an externally completed step.
        if ($expected === $actual) {
            return null;
        }
        return [
            "type" => "activity_completion",
            "contextid" => (int)$context->id,
            "arguments" => $arguments,
            "argshash" => confirmation_service::args_hash($arguments),
            "before" => $actual,
            "expected" => $expected,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function verify(array $condition, authenticated_identity $identity, int $courseid): array {
        $arguments = $condition["arguments"];
        $original = new set_activity_completion();
        $context = $original->resolve_context($arguments);
        if (!($context instanceof context_module) || (int)$context->id !== (int)$condition["contextid"] ||
                (int)$context->get_course_context(false)->instanceid !== $courseid ||
                !hash_equals($condition["argshash"], confirmation_service::args_hash($arguments))) {
            throw new api_exception("workflow_postcondition_mismatch", 409, "", [],
                        get_string("workflowpostconditionmismatch", "local_mcp"));
        }
        capability_guard::check($original->get_required_capability(), $context, $identity->userid);
        foreach (manager::access_policies() as $policy) {
            if (!$policy->tool_visible($identity->userid, $original->get_name(), true)) {
                throw new api_exception("permission_denied", 403);
            }
            $policy->authorize_tool($identity->userid, $original->get_name(), true, $context, $arguments);
        }
        $actual = $this->read_state((int)$arguments["cmid"], $identity);
        if ($actual === null || $actual !== $condition["expected"]) {
            throw new api_exception("workflow_postcondition_mismatch", 409, "", [],
                        get_string("workflowpostconditionmismatch", "local_mcp"));
        }
        return $actual;
    }
}
