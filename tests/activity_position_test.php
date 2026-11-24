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
 * Native module position and visibility regression coverage.
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
 * Test real Moodle course modules.
 * @covers \local_mcp\course\activity_position_service
 * @covers \local_mcp\write\registry
 */
final class activity_position_test extends advanced_testcase {
    /** Check order, dry run, idempotence and individual visibility. */
    public function test_position_and_visibility(): void {
        global $USER, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $a = $this->getDataGenerator()->create_module("page", ["course" => $course->id]);
        $b = $this->getDataGenerator()->create_module("page", ["course" => $course->id]);
        $c = $this->getDataGenerator()->create_module("page", ["course" => $course->id]);
        $identity = new authenticated_identity($USER->id, "test", ["mcp:write"]);
        $tools = registry::get_tools();
        foreach (["move_activity", "hide_activity", "show_activity"] as $name) {
            $this->assertTrue($tools[$name]->requires_confirmation());
            $this->assertTrue($tools[$name]->supports_dry_run());
        }
        $args = ["cmid" => $c->cmid, "aftercmid" => $a->cmid];
        $plan = $tools["move_activity"]->preview($args, $identity);
        $this->assertSame((int)$b->cmid, $plan["beforecmid"]);
        $sectionid = $plan["sectionid"];
        $this->assertSame("$a->cmid,$b->cmid,$c->cmid",
            $DB->get_field("course_sections", "sequence", ["id" => $sectionid]));
        $this->assertTrue($tools["move_activity"]->execute($args, $identity)["changed"]);
        $this->assertSame("$a->cmid,$c->cmid,$b->cmid",
            $DB->get_field("course_sections", "sequence", ["id" => $sectionid]));
        $this->assertFalse($tools["move_activity"]->execute($args, $identity)["changed"]);
        $tools["move_activity"]->execute(["cmid" => $b->cmid, "beforecmid" => $a->cmid], $identity);
        $this->assertSame("$b->cmid,$a->cmid,$c->cmid",
            $DB->get_field("course_sections", "sequence", ["id" => $sectionid]));
        $tools["hide_activity"]->execute(["cmid" => $a->cmid], $identity);
        $this->assertFalse((bool)$DB->get_field("course_modules", "visible", ["id" => $a->cmid]));
        $this->assertTrue((bool)$DB->get_field("course_modules", "visible", ["id" => $b->cmid]));
        $tools["show_activity"]->execute(["cmid" => $a->cmid], $identity);
        $this->assertTrue((bool)$DB->get_field("course_modules", "visible", ["id" => $a->cmid]));
    }

    /** Cross-course anchors are never accepted. */
    public function test_reject_cross_course_anchor(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $a = $this->getDataGenerator()->create_course();
        $b = $this->getDataGenerator()->create_course();
        $x = $this->getDataGenerator()->create_module("page", ["course" => $a->id]);
        $y = $this->getDataGenerator()->create_module("page", ["course" => $b->id]);
        $identity = new authenticated_identity($USER->id, "test", ["mcp:write"]);
        $this->expectException(api_exception::class);
        registry::get_tools()["move_activity"]->preview(["cmid" => $x->cmid, "aftercmid" => $y->cmid], $identity);
    }

    /** A student must not acquire write rights through the service. */
    public function test_student_cannot_hide(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module("page", ["course" => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, "student");
        $this->setUser($student);
        $identity = new authenticated_identity($student->id, "test", ["mcp:write"]);
        $this->expectException(\required_capability_exception::class);
        registry::get_tools()["hide_activity"]->preview(["cmid" => $page->cmid], $identity);
    }
}
