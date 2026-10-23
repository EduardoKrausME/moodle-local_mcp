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
 * Root plugin regression coverage for the bundled quiz MCP extension.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use mcptool_quiz\provider;
use mcptool_quiz\quiz_service;

/**
 * Confirm that the quiz extension is registered, permission-safe and actually changes Moodle records.
 *
 * @covers \mcptool_quiz\provider
 * @covers \mcptool_quiz\quiz_service
 * @covers \mcptool_quiz\write\update_activity
 */
final class quiz_extension_tools_test extends advanced_testcase {
    public function test_quiz_tools_and_confirmable_mutations_are_registered(): void {
        $provider = new provider();
        $read = $provider->get_read_tools();
        $write = $provider->get_write_tools();
        $this->assertCount(6, $read);
        $this->assertCount(6, $write);
        $names = [];
        foreach (array_merge($read, $write) as $tool) {
            $names[] = $tool->get_name();
            $this->assertSame("object", $tool->get_input_schema()["type"]);
        }
        $this->assertCount(count(array_unique($names)), $names);
        foreach ($write as $tool) {
            $this->assertTrue($tool->requires_confirmation());
        }
    }

    public function test_quiz_update_preserves_grading_configuration(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module("quiz", ["course" => $course->id]);
        $original = $DB->get_record("quiz", ["id" => $module->id], "*", MUST_EXIST);
        $identity = new authenticated_identity($USER->id, "test", ["mcp:write", "mcp:read"]);
        $changed = quiz_service::update([
            "cmid" => $module->cmid, "name" => "Revised quiz",
            "timelimit" => 600,
        ], $identity);
        $this->assertTrue($changed["updated"]);
        $updated = $DB->get_record("quiz", ["id" => $module->id], "*", MUST_EXIST);
        $this->assertSame("Revised quiz", $updated->name);
        $this->assertEquals(600, $updated->timelimit);
        $this->assertSame($original->grademethod, $updated->grademethod);
        $this->assertSame($original->preferredbehaviour, $updated->preferredbehaviour);
        $slots = quiz_service::slots(["cmid" => $module->cmid], $identity);
        $this->assertSame($module->cmid, $slots["cmid"]);
        $this->assertTrue($slots["editable"]);
    }

    public function test_student_cannot_access_question_structure(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module("quiz", ["course" => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, "student");
        $this->setUser($student);
        $identity = new authenticated_identity($student->id, "test", ["mcp:read"]);
        $this->expectException(api_exception::class);
        quiz_service::slots(["cmid" => $module->cmid], $identity);
    }
}
