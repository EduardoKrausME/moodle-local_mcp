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
 * Integration tests for gradebook and completion reporting MCP tools.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\completion\completion_report_service;
use local_mcp\exception\api_exception;
use local_mcp\grades\gradebook_service;
use local_mcp\security\authenticated_identity;

/**
 * Exercise grade visibility, sensitive audit data and real completion APIs.
 *
 * @covers \local_mcp\grades\gradebook_service
 * @covers \local_mcp\completion\completion_report_service
 * @covers \local_mcp\read\registry
 * @covers \local_mcp\write\registry
 */
final class gradebook_completion_tools_test extends advanced_testcase {
    /**
     * All six new tools are reachable through their proper registries.
     */
    public function test_tool_registration_and_write_confirmation(): void {
        $read = \local_mcp\read\registry::get_tools();
        $write = \local_mcp\write\registry::get_tools();
        foreach (["get_gradebook_structure", "get_grade_history",
                "get_course_completion_criteria", "get_course_completion_status",
                "get_course_progress_report"] as $name) {
            $this->assertArrayHasKey($name, $read);
            $this->assertArrayNotHasKey($name, $write);
            $this->assertSame("object", $read[$name]->get_input_schema()["type"]);
        }
        $tool = $write["set_grade_item_visibility"];
        $this->assertTrue($tool->requires_confirmation());
        $this->assertTrue($tool->supports_dry_run());
        $this->assertSame("moodle/grade:hide", $tool->get_required_capability());
    }

    /**
     * Moodle grade items are used for visibility changes with stale-write protection.
     */
    public function test_gradebook_structure_and_visibility(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module("assign", ["course" => $course->id]);
        $identity = new authenticated_identity((int)get_admin()->id, "test", ["mcp:read", "mcp:write"]);
        $before = gradebook_service::structure(["courseid" => $course->id], $identity);
        $this->assertGreaterThan(0, $before["total_items"]);
        $item = null;
        foreach ($before["items"] as $row) {
            if ($row["itemtype"] === "mod") {
                $item = $row;
                break;
            }
        }
        $this->assertNotNull($item);
        $args = ["courseid" => $course->id, "itemid" => $item["itemid"],
            "expected_item_sha256" => $item["item_sha256"], "hidden" => true];
        $preview = gradebook_service::visibility_preview($args, $identity);
        $this->assertTrue($preview["requested_hidden"]);
        $saved = gradebook_service::set_visibility($args, $identity);
        $this->assertTrue($saved["changed"]);
        $this->assertSame(1, (int)$DB->get_field("grade_items", "hidden",
            ["id" => $item["itemid"]]));
        $this->expectException(api_exception::class);
        gradebook_service::set_visibility($args, $identity);
    }

    /**
     * Non-graders cannot inspect other people's grade history.
     */
    public function test_grade_history_is_denied_to_students(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, "student");
        $this->getDataGenerator()->enrol_user($other->id, $course->id, "student");
        $this->setUser($student);
        $identity = new authenticated_identity((int)$student->id, "test", ["mcp:read"]);
        $this->expectException(api_exception::class);
        gradebook_service::history(["courseid" => $course->id,
            "userid" => $other->id], $identity);
    }

    /**
     * Enrolled students can see only their own course completion detail.
     */
    public function test_course_criteria_and_own_activity_completion(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->enablecompletion = 1;
        $course = $this->getDataGenerator()->create_course(["enablecompletion" => 1]);
        $this->getDataGenerator()->create_module("page", [
            "course" => $course->id, "completion" => COMPLETION_TRACKING_MANUAL,
        ]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, "student");
        $this->setUser($student);
        $identity = new authenticated_identity((int)$student->id, "test", ["mcp:read"]);
        $criteria = completion_report_service::criteria(["courseid" => $course->id], $identity);
        $this->assertTrue($criteria["enabled"]);
        $this->assertFalse($criteria["has_criteria"]);
        $status = completion_report_service::user_status([
            "courseid" => $course->id, "userid" => $student->id,
        ], $identity);
        $this->assertSame(1, $status["activities_total"]);
        $this->assertSame(0, $status["activities_completed"]);
        $this->assertFalse($status["course_complete"]);
    }

    /**
     * Only graders can access paginated enrollee progress.
     */
    public function test_class_report_requires_gradebook_permission(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, "student");
        $this->setUser($student);
        $identity = new authenticated_identity((int)$student->id, "test", ["mcp:read"]);
        $this->expectException(api_exception::class);
        completion_report_service::report(["courseid" => $course->id], $identity);
    }

    /**
     * Admins can see active participants without discovering identity details.
     */
    public function test_course_progress_report_is_paginated(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, "student");
        $identity = new authenticated_identity((int)get_admin()->id, "test", ["mcp:read"]);
        $report = completion_report_service::report(["courseid" => $course->id,
            "limit" => 10], $identity);
        $this->assertNotEmpty($report["participants"]);
        $ids = array_column($report["participants"], "userid");
        $this->assertContains((int)$student->id, $ids);
        $this->assertArrayNotHasKey("email", $report["participants"][0]);
        $this->assertArrayNotHasKey("name", $report["participants"][0]);
    }
}
