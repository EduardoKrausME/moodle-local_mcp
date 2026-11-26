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
 * Regression tests for generic course module metadata changes.
 *
 * @package local_mcp
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\write\registry;

/**
 * Verify partial updates and preservation of academic records.
 *
 * @covers \local_mcp\write\tool\rename_course_module
 * @covers \local_mcp\course\activity_metadata_service
 */
final class activity_metadata_test extends advanced_testcase {
    /**
     * Rename different module types without replacing their records.
     */
    public function test_rename_preserves_module_data(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $identity = new authenticated_identity($USER->id, "test", ["mcp:write"]);
        $tool = registry::get_tools()["rename_course_module"];
        $this->assertTrue($tool->requires_confirmation());
        $this->assertTrue($tool->supports_dry_run());
        foreach (["page", "quiz", "assign", "forum"] as $modname) {
            $activity = $this->getDataGenerator()->create_module($modname,
                ["course" => $course->id, "name" => "01 - Original", "intro" => "<p>Keep me</p>"]);
            $cmid = (int)$activity->cmid;
            $args = ["cmid" => $cmid, "name" => "Original", "expectedname" => "01 - Original"];
            $before = $DB->get_record($modname, ["id" => $activity->id], "*", MUST_EXIST);
            $cm = $DB->get_record("course_modules", ["id" => $cmid], "*", MUST_EXIST);
            $preview = $tool->preview($args, $identity);
            $this->assertSame("01 - Original", $preview["name"]);
            $this->assertEquals($before, $DB->get_record($modname, ["id" => $activity->id]));
            $args["expected_metadata_sha256"] = $preview["metadata_sha256"];
            $result = $tool->execute($args, $identity);
            $this->assertSame("Original", $result["name"]);
            $this->assertSame($cmid, $result["cmid"]);
            $after = $DB->get_record($modname, ["id" => $activity->id], "*", MUST_EXIST);
            $before->name = "Original";
            $before->timemodified = $after->timemodified;
            $this->assertEquals($before, $after);
            $this->assertEquals($cm, $DB->get_record("course_modules", ["id" => $cmid]));
            $this->assertSame("Original", get_fast_modinfo($course->id)->get_cm($cmid)->name);
        }
    }

    /**
     * Edit description and visibility flag independently, including false and empty values.
     */
    public function test_partial_intro_and_showdescription(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module("page",
            ["course" => $course->id, "name" => "Keep name", "intro" => "<p>Old</p>",
                "content" => "<p>Keep content</p>", "showdescription" => 1]);
        $identity = new authenticated_identity($USER->id, "test", ["mcp:write"]);
        $tool = registry::get_tools()["rename_course_module"];
        $args = ["cmid" => (int)$page->cmid, "intro" => "", "showdescription" => false];
        $preview = $tool->preview($args, $identity);
        $this->assertSame("<p>Old</p>", $preview["intro"]);
        $result = $tool->execute($args + ["expected_metadata_sha256" => $preview["metadata_sha256"]], $identity);
        $this->assertSame("", $result["intro"]);
        $this->assertFalse($result["showdescription"]);
        $this->assertSame("Keep name", $result["name"]);
        $this->assertSame("<p>Keep content</p>", $DB->get_field("page", "content", ["id" => $page->id]));
        $this->assertFalse((bool)$DB->get_field("course_modules", "showdescription", ["id" => $page->cmid]));
    }

    /**
     * A stale preview cannot overwrite an intervening change.
     */
    public function test_stale_metadata_rejected(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module("page", ["course" => $course->id]);
        $identity = new authenticated_identity($USER->id, "test", ["mcp:write"]);
        $tool = registry::get_tools()["rename_course_module"];
        $args = ["cmid" => (int)$page->cmid, "name" => "New title"];
        $preview = $tool->preview($args, $identity);
        $DB->set_field("page", "intro", "Concurrent edit", ["id" => $page->id]);
        $this->expectException(api_exception::class);
        $tool->execute($args + ["expected_metadata_sha256" => $preview["metadata_sha256"]], $identity);
    }

    /**
     * The connected user needs the module management capability even for a preview.
     */
    public function test_student_cannot_edit_metadata(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module("page", ["course" => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, "student");
        $identity = new authenticated_identity($student->id, "test", ["mcp:write"]);
        $this->expectException(api_exception::class);
        registry::get_tools()["rename_course_module"]->preview(
            ["cmid" => (int)$page->cmid, "name" => "Unauthorized"], $identity);
    }
}
