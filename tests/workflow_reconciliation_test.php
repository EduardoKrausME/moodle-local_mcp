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
 * Test independently completed workflow configuration reconciliation.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use context_module;
use context_course;
use local_mcp\exception\api_exception;
use local_mcp\protocol\mcp_server;
use local_mcp\security\authenticated_identity;
use local_mcp\workflow\sequence;
use ReflectionMethod;

/**
 * Tests use real course modules and the normal two-phase MCP protocol.
 *
 * @covers \local_mcp\workflow\sequence
 * @covers \local_mcp\workflow\reconciliation_service
 * @covers \local_mcp\workflow\verifier\activity_completion
 * @covers \local_mcp\write\tool\workflow_reconcile
 */
final class workflow_reconciliation_test extends advanced_testcase {
    /**
     * Build an isolated plan and fixture without any real learner data.
     *
     * @return array
     */
    private function fixture(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config("enablecompletion", 1);
        $course = $this->getDataGenerator()->create_course(["enablecompletion" => 1]);
        $page = $this->getDataGenerator()->create_module("page", ["course" => $course->id, "completion" => 0]);
        $identity = new authenticated_identity((int)get_admin()->id, "test", ["mcp:write"], 10, 20);
        $workflow = sequence::start($identity, (int)$course->id, [
            ["title" => "Configure view", "tool" => "set_activity_completion"],
            ["title" => "Next step", "tool" => "create_section"],
        ]);
        $arguments = [
            "cmid" => (int)$page->cmid,
            "completion" => 2,
            "expected_completion" => 0,
            "requireview" => true,
            "requiregrade" => false,
            "workflow_id" => $workflow["id"],
            "workflow_step" => 1,
        ];
        $reconcile = [
            "workflow_id" => $workflow["id"],
            "courseid" => (int)$course->id,
            "expectedstep" => 1,
            "expectedgeneration" => 0,
        ];
        return [$identity, $arguments, $reconcile];
    }

    /**
     * Invoke the production protocol path, including confirmation validation.
     *
     * @param string $tool Tool name.
     * @param array $args Arguments.
     * @param authenticated_identity $identity Connection.
     * @return array
     */
    private function call(string $tool, array $args, authenticated_identity $identity): array {
        $method = new ReflectionMethod(mcp_server::class, "call_tool");
        $method->setAccessible(true);
        return $method->invoke(new mcp_server("write"), $tool, $args, [], $identity, "test-reconciliation-access");
    }

    /**
     * Simulate an independent administrative configuration in the test fixture.
     *
     * @param int $cmid Target.
     * @return void
     */
    private function configure(int $cmid): void {
        global $DB;
        $DB->update_record("course_modules", (object)["id" => $cmid, "completion" => 2, "completionview" => 1]);
    }

    /** Verify matching existing state without repeating an academic write. */
    public function test_confirmed_reconciliation_preserves_academic_state(): void {
        global $DB;
        [$identity, $args, $reconcile] = $this->fixture();
        $pending = $this->call("set_activity_completion", $args, $identity);
        $this->assertTrue($pending["confirmation_required"]);
        $this->assertTrue($pending["workflow"]["has_reconciliation_postcondition"]);
        $this->configure($args["cmid"]);
        $before = $DB->get_record("course_modules", ["id" => $args["cmid"]], "*", MUST_EXIST);
        $criteria = $DB->get_records("course_completion_criteria", ["course" => $reconcile["courseid"]]);
        $history = $DB->get_records("course_modules_completion", ["coursemoduleid" => $args["cmid"]]);
        $grades = $DB->get_records("grade_items", ["courseid" => $reconcile["courseid"]]);
        $preview = $this->call("workflow_reconcile", $reconcile, $identity);
        $this->assertTrue($preview["confirmation_required"]);
        $this->assertFalse($preview["preview"]["activity_changes"]);
        $this->assertSame("ready", sequence::status($reconcile["workflow_id"], $identity, $reconcile["courseid"])["state"]);
        $saved = $this->call("workflow_reconcile",
            $reconcile + ["confirmation_token" => $preview["confirmation_token"]], $identity);
        $this->assertSame("done", $saved["workflow"]["state"]);
        $this->assertSame("reconciled", $saved["workflow"]["completed"][0]["completion_method"]);
        $this->assertSame(1, $saved["workflow"]["generation"]);
        $audit = $DB->get_record("local_mcp_audit", [
            "tool" => "workflow_reconcile", "status" => "ok", "confirmation" => 1,
        ], "*", MUST_EXIST);
        $metadata = json_decode($audit->metadata, true);
        $this->assertSame($reconcile["workflow_id"], $metadata["workflow_id"]);
        $this->assertSame("reconciled", $metadata["completion_method"]);
        $this->assertSame(1, $metadata["workflow_generation"]);
        $this->assertEquals($before, $DB->get_record("course_modules", ["id" => $args["cmid"]], "*", MUST_EXIST));
        $this->assertEquals($criteria, $DB->get_records("course_completion_criteria", ["course" => $reconcile["courseid"]]));
        $this->assertEquals($history, $DB->get_records("course_modules_completion", ["coursemoduleid" => $args["cmid"]]));
        $this->assertEquals($grades, $DB->get_records("grade_items", ["courseid" => $reconcile["courseid"]]));
        $next = sequence::next($reconcile["workflow_id"], $identity, $reconcile["courseid"], 1, 1);
        $this->assertSame("ready", $next["state"]);
        $this->assertSame(2, $next["step"]);
    }

    /** Missing or legacy postconditions cannot be supplied by the client. */
    public function test_unbound_workflow_is_rejected(): void {
        [$identity, $args, $reconcile] = $this->fixture();
        $this->configure($args["cmid"]);
        $this->expectException(api_exception::class);
        $this->call("workflow_reconcile", $reconcile, $identity);
    }

    /** Unchanged settings cannot advance a workflow. */
    public function test_mismatched_state_is_rejected(): void {
        [$identity, $args, $reconcile] = $this->fixture();
        $this->call("set_activity_completion", $args, $identity);
        $this->expectException(api_exception::class);
        $this->call("workflow_reconcile", $reconcile, $identity);
    }

    /** A state change between preview and confirmation invalidates reconciliation. */
    public function test_state_drift_is_rejected(): void {
        global $DB;
        [$identity, $args, $reconcile] = $this->fixture();
        $this->call("set_activity_completion", $args, $identity);
        $this->configure($args["cmid"]);
        $preview = $this->call("workflow_reconcile", $reconcile, $identity);
        $DB->set_field("course_modules", "completionview", 0, ["id" => $args["cmid"]]);
        try {
            $this->call("workflow_reconcile", $reconcile + ["confirmation_token" => $preview["confirmation_token"]], $identity);
            $this->fail("Changed state must not reconcile.");
        } catch (api_exception $e) {
            $this->assertSame("workflow_postcondition_mismatch", $e->machinecode);
        }
        $this->assertSame("ready", sequence::status($reconcile["workflow_id"], $identity, $reconcile["courseid"])["state"]);
    }

    /** An active execution cannot be reconciled concurrently. */
    public function test_executing_step_is_rejected(): void {
        [$identity, $args, $reconcile] = $this->fixture();
        $this->call("set_activity_completion", $args, $identity);
        $original = $args;
        unset($original["workflow_id"], $original["workflow_step"]);
        sequence::claim($reconcile["workflow_id"], $identity, 1, "set_activity_completion",
            context_module::instance($args["cmid"]), 0, \local_mcp\security\confirmation_service::args_hash($original));
        $this->configure($args["cmid"]);
        $this->expectException(api_exception::class);
        $this->call("workflow_reconcile", $reconcile, $identity);
    }

    /** A distinct OAuth connection cannot access a matching condition. */
    public function test_other_connection_is_rejected(): void {
        [$identity, $args, $reconcile] = $this->fixture();
        $this->call("set_activity_completion", $args, $identity);
        $this->configure($args["cmid"]);
        $other = new authenticated_identity($identity->userid, "test", ["mcp:write"], 10, 21);
        $this->expectException(api_exception::class);
        $this->call("workflow_reconcile", $reconcile, $other);
    }

    /** A confirmation cannot be replayed or complete the same step twice. */
    public function test_replay_is_rejected(): void {
        [$identity, $args, $reconcile] = $this->fixture();
        $this->call("set_activity_completion", $args, $identity);
        $this->configure($args["cmid"]);
        $preview = $this->call("workflow_reconcile", $reconcile, $identity);
        $confirmed = $reconcile + ["confirmation_token" => $preview["confirmation_token"]];
        $this->call("workflow_reconcile", $confirmed, $identity);
        try {
            $this->call("workflow_reconcile", $confirmed, $identity);
            $this->fail("A completed stage must reject replay.");
        } catch (api_exception $e) {
            $this->assertSame("workflow_step_mismatch", $e->machinecode);
        }
        $this->assertCount(1, sequence::status($reconcile["workflow_id"], $identity, $reconcile["courseid"])["completed"]);
    }

    /** Old original approvals cannot be reused once reconciliation has completed. */
    public function test_original_confirmation_cannot_be_replayed(): void {
        [$identity, $args, $reconcile] = $this->fixture();
        $original = $this->call("set_activity_completion", $args, $identity);
        $this->configure($args["cmid"]);
        $preview = $this->call("workflow_reconcile", $reconcile, $identity);
        $this->call("workflow_reconcile", $reconcile + ["confirmation_token" => $preview["confirmation_token"]], $identity);
        $this->expectException(api_exception::class);
        $this->call("set_activity_completion", $args + ["confirmation_token" => $original["confirmation_token"]], $identity);
    }

    /** The current activity cannot be replaced by another activity in the same course. */
    public function test_bound_target_cannot_be_changed(): void {
        [$identity, $args, $reconcile] = $this->fixture();
        $this->call("set_activity_completion", $args, $identity);
        $other = $this->getDataGenerator()->create_module("page", [
            "course" => $reconcile["courseid"], "completion" => 0,
        ]);
        $args["cmid"] = (int)$other->cmid;
        try {
            $this->call("set_activity_completion", $args, $identity);
            $this->fail("A bound step cannot be retargeted.");
        } catch (api_exception $e) {
            $this->assertSame("workflow_postcondition_mismatch", $e->machinecode);
        }
    }

    /** Legacy metadata is never upgraded into an externally asserted condition. */
    public function test_legacy_plan_cannot_acquire_postcondition(): void {
        [$identity, $args, $reconcile] = $this->fixture();
        $key = "workflow_" . $reconcile["workflow_id"];
        $stored = json_decode(get_config("local_mcp", $key), true);
        unset($stored["reconciliation_version"]);
        set_config($key, json_encode($stored), "local_mcp");
        $original = $this->call("set_activity_completion", $args, $identity);
        $this->assertFalse($original["workflow"]["has_reconciliation_postcondition"]);
        $this->configure($args["cmid"]);
        try {
            $this->call("workflow_reconcile", $reconcile, $identity);
            $this->fail("A legacy plan has no trustworthy postcondition.");
        } catch (api_exception $e) {
            $this->assertSame("workflow_postcondition_unavailable", $e->machinecode);
        }
    }

    /** Ordinary confirmed completion remains available with a server-bound condition. */
    public function test_original_confirmed_operation_still_works(): void {
        [$identity, $args, $reconcile] = $this->fixture();
        $preview = $this->call("set_activity_completion", $args, $identity);
        $result = $this->call("set_activity_completion",
            $args + ["confirmation_token" => $preview["confirmation_token"]], $identity);
        $this->assertSame("done", $result["workflow"]["state"]);
        $this->assertSame(2, $result["completion"]);
        $this->assertArrayNotHasKey("completion_method", $result["workflow"]["completed"][0]);
    }

    /** A confirmation token must actually be valid even when the state matches. */
    public function test_invalid_reconciliation_token_is_rejected(): void {
        [$identity, $args, $reconcile] = $this->fixture();
        $this->call("set_activity_completion", $args, $identity);
        $this->configure($args["cmid"]);
        $this->call("workflow_reconcile", $reconcile, $identity);
        try {
            $this->call("workflow_reconcile", $reconcile + ["confirmation_token" => "invalid-token"], $identity);
            $this->fail("An invalid token cannot reconcile.");
        } catch (api_exception $e) {
            $this->assertSame("confirmation_expired", $e->machinecode);
        }
        $this->assertSame("ready", sequence::status($reconcile["workflow_id"], $identity, $reconcile["courseid"])["state"]);
    }

    /** A write scope is independently required for reconciliation. */
    public function test_read_scope_cannot_reconcile(): void {
        [$identity, $args, $reconcile] = $this->fixture();
        $this->call("set_activity_completion", $args, $identity);
        $this->configure($args["cmid"]);
        $read = new authenticated_identity($identity->userid, "test", ["mcp:read"], 10, 20);
        try {
            $this->call("workflow_reconcile", $reconcile, $read);
            $this->fail("Read-only identity cannot reconcile.");
        } catch (api_exception $e) {
            $this->assertSame("insufficient_scope", $e->machinecode);
        }
    }

    /** A capability removed after preview must be rechecked against the original module. */
    public function test_revoked_module_permission_is_rejected(): void {
        global $DB;
        [$admin, $args, $reconcile] = $this->fixture();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $reconcile["courseid"], "editingteacher");
        $identity = new authenticated_identity((int)$teacher->id, "test", ["mcp:write"], 10, 20);
        $coursecontext = context_course::instance($reconcile["courseid"]);
        $this->assertTrue(has_capability("moodle/course:update", $coursecontext, $teacher->id));
        $workflow = sequence::start($identity, $reconcile["courseid"], [
            ["title" => "Configure view", "tool" => "set_activity_completion"],
            ["title" => "Next step", "tool" => "create_section"],
        ]);
        $args["workflow_id"] = $workflow["id"];
        $reconcile["workflow_id"] = $workflow["id"];
        $this->setUser($teacher);
        $this->call("set_activity_completion", $args, $identity);
        $this->configure($args["cmid"]);
        $preview = $this->call("workflow_reconcile", $reconcile, $identity);
        $role = $DB->get_record("role", ["shortname" => "editingteacher"], "*", MUST_EXIST);
        assign_capability("moodle/course:manageactivities", CAP_PROHIBIT, $role->id,
            context_module::instance($args["cmid"])->id, true);
        $this->assertFalse(has_capability("moodle/course:manageactivities",
            context_module::instance($args["cmid"]), $teacher->id));
        try {
            $this->call("workflow_reconcile",
                $reconcile + ["confirmation_token" => $preview["confirmation_token"]], $identity);
            $this->fail("Revoked module permission cannot reconcile.");
        } catch (api_exception $e) {
            $this->assertSame("permission_denied", $e->machinecode);
        }
    }

    /** A student record appearing after preview prevents conservative reconciliation. */
    public function test_new_student_completion_record_is_preserved(): void {
        global $DB;
        [$identity, $args, $reconcile] = $this->fixture();
        $this->call("set_activity_completion", $args, $identity);
        $this->configure($args["cmid"]);
        $preview = $this->call("workflow_reconcile", $reconcile, $identity);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $reconcile["courseid"], "student");
        $id = $DB->insert_record("course_modules_completion", (object)[
            "coursemoduleid" => $args["cmid"], "userid" => $student->id,
            "completionstate" => 1, "viewed" => 1, "timemodified" => time(),
        ]);
        $record = $DB->get_record("course_modules_completion", ["id" => $id], "*", MUST_EXIST);
        try {
            $this->call("workflow_reconcile",
                $reconcile + ["confirmation_token" => $preview["confirmation_token"]], $identity);
            $this->fail("Student history must prevent reconciliation.");
        } catch (api_exception $e) {
            $this->assertSame("workflow_postcondition_mismatch", $e->machinecode);
        }
        $this->assertEquals($record, $DB->get_record("course_modules_completion", ["id" => $id], "*", MUST_EXIST));
    }

    /** A failed operation with a known matching result can be recognized, but never retried. */
    public function test_failed_step_can_be_verified(): void {
        [$identity, $args, $reconcile] = $this->fixture();
        $this->call("set_activity_completion", $args, $identity);
        $original = $args;
        unset($original["workflow_id"], $original["workflow_step"]);
        sequence::claim($reconcile["workflow_id"], $identity, 1, "set_activity_completion",
            context_module::instance($args["cmid"]), 0, \local_mcp\security\confirmation_service::args_hash($original));
        sequence::fail($reconcile["workflow_id"], $identity, $reconcile["courseid"], "test-failure");
        $this->configure($args["cmid"]);
        $preview = $this->call("workflow_reconcile", $reconcile, $identity);
        $saved = $this->call("workflow_reconcile",
            $reconcile + ["confirmation_token" => $preview["confirmation_token"]], $identity);
        $this->assertSame("done", $saved["workflow"]["state"]);
        $this->assertNull($saved["workflow"]["failure"]);
    }

    /** Revalidate again at Next, without authorizing the next operation. */
    public function test_next_refuses_configuration_drift(): void {
        global $DB;
        [$identity, $args, $reconcile] = $this->fixture();
        $this->call("set_activity_completion", $args, $identity);
        $this->configure($args["cmid"]);
        $preview = $this->call("workflow_reconcile", $reconcile, $identity);
        $this->call("workflow_reconcile", $reconcile + ["confirmation_token" => $preview["confirmation_token"]], $identity);
        $DB->set_field("course_modules", "completionview", 0, ["id" => $args["cmid"]]);
        try {
            sequence::next($reconcile["workflow_id"], $identity, $reconcile["courseid"], 1, 1);
            $this->fail("Changed configuration must prevent navigation.");
        } catch (api_exception $e) {
            $this->assertSame("workflow_postcondition_mismatch", $e->machinecode);
        }
        $this->assertSame(1, sequence::status($reconcile["workflow_id"], $identity, $reconcile["courseid"])["step"]);
    }

    /** An equivalent JSON object may be serialized with a different key order. */
    public function test_preview_key_order_is_irrelevant(): void {
        [$identity, $args, $reconcile] = $this->fixture();
        $this->call("set_activity_completion", $args, $identity);
        $second = $this->call("set_activity_completion", array_reverse($args, true), $identity);
        $this->assertTrue($second["workflow"]["has_reconciliation_postcondition"]);
    }

    /** A first unsupported preview cannot subsequently create a trusted condition. */
    public function test_unsupported_first_preview_cannot_bind_later(): void {
        [$identity, $args, $reconcile] = $this->fixture();
        $unsupported = $args;
        $unsupported["completion"] = 1;
        $first = $this->call("set_activity_completion", $unsupported, $identity);
        $this->assertFalse($first["workflow"]["has_reconciliation_postcondition"]);
        $second = $this->call("set_activity_completion", $args, $identity);
        $this->assertFalse($second["workflow"]["has_reconciliation_postcondition"]);
        $this->configure($args["cmid"]);
        $this->expectException(api_exception::class);
        $this->call("workflow_reconcile", $reconcile, $identity);
    }

    /** State reads must not return the stale configuration cache. */
    public function test_status_reads_persisted_workflow_without_config_cache(): void {
        global $DB;
        [$identity, $args, $reconcile] = $this->fixture();
        $key = "workflow_" . $reconcile["workflow_id"];
        $stored = json_decode(get_config("local_mcp", $key), true);
        $stored["state"] = "executing";
        $DB->set_field("config_plugins", "value", json_encode($stored), [
            "plugin" => "local_mcp", "name" => $key,
        ]);
        $this->assertSame("executing", sequence::status($reconcile["workflow_id"], $identity,
            $reconcile["courseid"])["state"]);
    }

    /** Drift after Next must prevent the following tool from claiming its stage. */
    public function test_following_stage_refuses_configuration_drift(): void {
        global $DB;
        [$identity, $args, $reconcile] = $this->fixture();
        $this->call("set_activity_completion", $args, $identity);
        $this->configure($args["cmid"]);
        $preview = $this->call("workflow_reconcile", $reconcile, $identity);
        $this->call("workflow_reconcile", $reconcile + ["confirmation_token" => $preview["confirmation_token"]], $identity);
        sequence::next($reconcile["workflow_id"], $identity, $reconcile["courseid"], 1, 1);
        $DB->set_field("course_modules", "completionview", 0, ["id" => $args["cmid"]]);
        try {
            sequence::claim($reconcile["workflow_id"], $identity, 2, "create_section",
                context_course::instance($reconcile["courseid"]), 1);
            $this->fail("Drift must prevent claiming the next stage.");
        } catch (api_exception $e) {
            $this->assertSame("workflow_postcondition_mismatch", $e->machinecode);
        }
        $this->assertSame("ready", sequence::status($reconcile["workflow_id"], $identity,
            $reconcile["courseid"])["state"]);
    }
}
