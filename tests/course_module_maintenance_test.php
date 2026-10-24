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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Regression coverage for assignment creation and safe module deletion.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\write\registry;

/**
 * Exercise the Moodle activity creation and deletion safety contracts.
 */
final class course_module_maintenance_test extends advanced_testcase {
    /**
     * @covers \local_mcp\write\registry
     * @covers \local_mcp\write\tool\assign_create_activity
     * @covers \local_mcp\write\tool\delete_course_module
     */
    public function test_tools_are_registered_with_safe_schemas(): void {
        $tools = registry::get_tools();
        $this->assertArrayHasKey("assign_create_activity", $tools);
        $this->assertArrayHasKey("delete_course_module", $tools);
        $this->assertTrue($tools["assign_create_activity"]->requires_confirmation());
        $this->assertTrue($tools["delete_course_module"]->requires_confirmation());
        $this->assertTrue($tools["delete_course_module"]->is_destructive());
        $schema = $tools["delete_course_module"]->get_input_schema();
        foreach (["courseid", "cmid", "expectedsectionnum", "expectedmodname", "expectedname"] as $field) {
            $this->assertContains($field, $schema["required"]);
        }
        $assignschema = $tools["assign_create_activity"]->get_input_schema();
        $this->assertArrayHasKey("file_enabled", $assignschema["properties"]);
        $this->assertArrayHasKey("onlinetext_enabled", $assignschema["properties"]);
    }

    /**
     * @covers \local_mcp\write\tool\delete_course_module
     */
    public function test_deletion_refuses_mismatched_name_or_course(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module("page",
            ["course" => $course->id, "name" => "Keep this original"]);
        $identity = new authenticated_identity($USER->id, "test", ["mcp:write"]);
        $tool = registry::get_tools()["delete_course_module"];
        $args = [
            "courseid" => $course->id,
            "cmid" => $page->cmid,
            "expectedsectionnum" => 0,
            "expectedmodname" => "page",
            "expectedname" => "Wrong name",
        ];
        $this->expectException(api_exception::class);
        $tool->preview($args, $identity);
    }
    /**
     * @covers \local_mcp\write\tool\delete_course_module
     */
    public function test_deletion_removes_only_the_verified_module(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $selected = $this->getDataGenerator()->create_module("page",
            ["course" => $course->id, "name" => "Delete selected page"]);
        $preserved = $this->getDataGenerator()->create_module("page",
            ["course" => $course->id, "name" => "Preserve neighbouring page"]);
        $identity = new authenticated_identity($USER->id, "test", ["mcp:write"]);
        $tool = registry::get_tools()["delete_course_module"];
        $args = [
            "courseid" => $course->id,
            "cmid" => $selected->cmid,
            "expectedsectionnum" => 0,
            "expectedmodname" => "page",
            "expectedname" => "Delete selected page",
        ];
        $preview = $tool->preview($args, $identity);
        $this->assertSame((int)$selected->cmid, $preview["cmid"]);
        $this->assertTrue($DB->record_exists("course_modules", ["id" => $selected->cmid]));
        $result = $tool->execute($args, $identity);
        $this->assertTrue($result["deleted"]);
        $this->assertFalse($DB->record_exists("course_modules", ["id" => $selected->cmid]));
        $this->assertTrue($DB->record_exists("course_modules", ["id" => $preserved->cmid]));
    }

    /**
     * @covers \local_mcp\write\tool\assign_create_activity
     */
    public function test_assignment_requires_at_least_one_submission_type(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $identity = new authenticated_identity($USER->id, "test", ["mcp:write"]);
        $tool = registry::get_tools()["assign_create_activity"];
        $args = [
            "courseid" => $course->id,
            "sectionnum" => 0,
            "name" => "Invalid assignment",
            "file_enabled" => false,
            "onlinetext_enabled" => false,
        ];
        $this->expectException(api_exception::class);
        $tool->preview($args, $identity);
    }
}
