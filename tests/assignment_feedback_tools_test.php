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
 * Regression tests for assignment feedback MCP tools.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\assignment\feedback_service;
use local_mcp\exception\api_exception;
use local_mcp\read\registry as read_registry;
use local_mcp\security\authenticated_identity;
use local_mcp\write\registry as write_registry;

/**
 * Verify the assignment feedback tool contract and student-level permission checks.
 *
 * @covers \local_mcp\assignment\feedback_service
 * @covers \local_mcp\read\tool\get_assignment_feedback_context
 * @covers \local_mcp\read\tool\get_assignment_feedback_queue
 * @covers \local_mcp\write\tool\save_assignment_feedback
 * @covers \local_mcp\read\registry
 * @covers \local_mcp\write\registry
 */
final class assignment_feedback_tools_test extends advanced_testcase {
    public function test_tools_are_discoverable_and_write_requires_confirmation(): void {
        $read = read_registry::get_tools();
        $write = write_registry::get_tools();
        $this->assertArrayHasKey("get_assignment_feedback_context", $read);
        $this->assertArrayHasKey("get_assignment_feedback_queue", $read);
        $this->assertArrayHasKey("save_assignment_feedback", $write);
        $tool = $write["save_assignment_feedback"];
        $this->assertTrue($tool->requires_confirmation());
        $this->assertTrue($tool->supports_dry_run());
        $schema = $tool->get_input_schema();
        $this->assertContains("expected_submission_timemodified", $schema["required"]);
        $this->assertContains("expected_feedback_sha256", $schema["required"]);
    }

    public function test_student_cannot_read_other_students_submission(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module("assign", ["course" => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, "student");
        $this->setUser($student);
        $identity = new authenticated_identity($student->id, "test", ["mcp:read"]);
        $this->expectException(api_exception::class);
        feedback_service::target($module->cmid, $student->id, $identity);
    }
}
