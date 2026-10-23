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
 * Tests for core group, access and completion MCP operations.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\course\group_service;
use local_mcp\course\activity_settings_service;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;

/**
 * Exercise real Moodle group membership and course-module settings.
 *
 * @covers \local_mcp\course\group_service
 * @covers \local_mcp\course\activity_settings_service
 * @covers \local_mcp\read\registry
 * @covers \local_mcp\write\registry
 */
final class course_group_settings_tools_test extends advanced_testcase {
    public function test_all_tools_registered_and_writes_confirmable(): void {
        $reads = \local_mcp\read\registry::get_tools();
        $writes = \local_mcp\write\registry::get_tools();
        foreach (["get_course_groups", "get_group_members",
                "get_activity_restrictions", "get_activity_completion"] as $name) {
            $this->assertArrayHasKey($name, $reads);
            $this->assertArrayNotHasKey($name, $writes);
            $this->assertSame("object", $reads[$name]->get_input_schema()["type"]);
        }
        foreach (["create_group", "add_group_members", "remove_group_members",
                "set_activity_groupmode", "set_activity_restrictions",
                "set_activity_completion"] as $name) {
            $this->assertArrayHasKey($name, $writes);
            $this->assertTrue($writes[$name]->requires_confirmation());
            $this->assertTrue($writes[$name]->supports_dry_run());
        }
    }

    public function test_groups_create_add_and_remove_with_snapshot_check(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, "student");
        $identity = new authenticated_identity((int)get_admin()->id, "test", ["mcp:read", "mcp:write"]);

        $created = group_service::create(["courseid" => $course->id, "name" => "Seminar A"], $identity);
        $this->assertTrue($created["created"]);
        $groups = group_service::list_groups(["courseid" => $course->id], $identity);
        $this->assertSame(1, $groups["total"]);
        $before = group_service::members(["courseid" => $course->id,
            "groupid" => $created["groupid"]], $identity);
        $this->assertSame(0, $before["total"]);
        $arguments = ["courseid" => $course->id, "groupid" => $created["groupid"],
            "userids" => [$student->id], "expected_members_sha256" => $before["members_sha256"]];
        $added = group_service::change($arguments, $identity, false);
        $this->assertSame(1, $added["changed"]);
        $after = group_service::members(["courseid" => $course->id,
            "groupid" => $created["groupid"]], $identity);
        $this->assertSame(1, $after["total"]);
        $this->assertSame($student->id, $after["members"][0]["userid"]);

        try {
            group_service::change($arguments, $identity, false);
            $this->fail("Stale membership snapshot was accepted.");
        } catch (api_exception $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }

        $arguments["expected_members_sha256"] = $after["members_sha256"];
        $removed = group_service::change($arguments, $identity, true);
        $this->assertSame(1, $removed["changed"]);
        $this->assertSame(0, group_service::members(["courseid" => $course->id,
            "groupid" => $created["groupid"]], $identity)["total"]);
    }

    public function test_module_groupmode_availability_and_completion(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $CFG->enableavailability = 1;
        $CFG->enablecompletion = 1;
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(["enablecompletion" => 1]);
        $module = $this->getDataGenerator()->create_module("page", [
            "course" => $course->id, "completion" => COMPLETION_TRACKING_NONE,
        ]);
        $identity = new authenticated_identity((int)get_admin()->id, "test", ["mcp:read", "mcp:write"]);
        $created = group_service::create(["courseid" => $course->id, "name" => "Limited group"], $identity);

        $mode = activity_settings_service::set_groupmode([
            "cmid" => $module->cmid, "groupmode" => SEPARATEGROUPS, "expected_groupmode" => NOGROUPS,
        ], $identity);
        $this->assertTrue($mode["changed"]);
        $this->assertSame(SEPARATEGROUPS, (int)$DB->get_field("course_modules", "groupmode",
            ["id" => $module->cmid]));

        $before = activity_settings_service::restrictions(["cmid" => $module->cmid], $identity);
        $conditions = [["type" => "group", "groupid" => $created["groupid"]],
            ["type" => "date", "direction" => "from", "timestamp" => time() - 86400]];
        $arguments = ["cmid" => $module->cmid, "mode" => "replace",
            "expected_availability_sha256" => $before["availability_sha256"],
            "conditions" => $conditions];
        $updated = activity_settings_service::set_restrictions($arguments, $identity);
        $this->assertTrue($updated["updated"]);
        $stored = activity_settings_service::restrictions(["cmid" => $module->cmid], $identity);
        $this->assertCount(2, $stored["availability"]["c"]);

        try {
            activity_settings_service::set_restrictions($arguments, $identity);
            $this->fail("Stale availability snapshot was accepted.");
        } catch (api_exception $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }

        $settings = activity_settings_service::completion(["cmid" => $module->cmid], $identity);
        $this->assertTrue($settings["enabled_for_course"]);
        $changed = activity_settings_service::set_completion([
            "cmid" => $module->cmid, "completion" => COMPLETION_TRACKING_AUTOMATIC,
            "expected_completion" => $settings["completion"], "requireview" => true,
        ], $identity);
        $this->assertTrue($changed["updated"]);
        $this->assertSame(COMPLETION_TRACKING_AUTOMATIC, (int)$DB->get_field(
            "course_modules", "completion", ["id" => $module->cmid]));
        $this->assertSame(1, (int)$DB->get_field("course_modules", "completionview",
            ["id" => $module->cmid]));
    }

    public function test_student_cannot_manage_groups_or_module_settings(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, "student");
        $this->setUser($student);
        $identity = new authenticated_identity($student->id, "test", ["mcp:read", "mcp:write"]);
        $this->expectException(api_exception::class);
        group_service::list_groups(["courseid" => $course->id], $identity);
    }
}
