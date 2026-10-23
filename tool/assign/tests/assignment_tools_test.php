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
 * Test registration, schemas and confirmation of assignment tools.
 *
 * @package   mcptool_assign
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_assign;

/**
 * Coverage of the public assignment tool providers and their contracts.
 *
 * @covers \mcptool_assign\provider
 * @covers \mcptool_assign\write\create_activity
 * @covers \mcptool_assign\write\update_activity
 */
final class assignment_tools_test extends \advanced_testcase {
    public function test_registers_unique_read_write_tools(): void {
        $provider = new provider();
        $read = $provider->get_read_tools();
        $write = $provider->get_write_tools();
        $this->assertCount(7, $read);
        $this->assertCount(8, $write);
        $names = array_merge(
            array_map(static function($tool) { return $tool->get_name(); }, $read),
            array_map(static function($tool) { return $tool->get_name(); }, $write)
        );
        $this->assertCount(count(array_unique($names)), $names);
        foreach ($write as $tool) {
            $this->assertTrue($tool->requires_confirmation());
            $this->assertTrue($tool->supports_dry_run());
            $this->assertSame("object", $tool->get_input_schema()["type"]);
        }
    }

    /**
     * Test the real module lifecycle rather than a schema-only placeholder.
     *
     * @covers \mcptool_assign\write\create_activity::execute
     * @covers \mcptool_assign\write\update_activity::execute
     */
    public function test_create_and_update_assignment_without_disabling_submission_plugins(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $identity = new \local_mcp\security\authenticated_identity($USER->id, "test", ["mcp:write"]);
        $create = new \mcptool_assign\write\create_activity();
        $created = $create->execute([
            "courseid" => $course->id, "sectionnum" => 0,
            "name" => "Assignment One", "intro" => "<p>Prepare a summary.</p>",
            "duedate" => time() + 86400, "file_submissions" => true, "onlinetext" => true,
        ], $identity);
        $this->assertGreaterThan(0, $created["cmid"]);
        $record = $DB->get_record("assign", ["id" => $created["instanceid"]], "*", MUST_EXIST);
        $this->assertStringContainsString("Prepare a summary", $record->intro);
        [$updatedcourse, $cm] = get_course_and_cm_from_cmid($created["cmid"], "assign");
        $assignment = new \assign(\context_module::instance($cm->id), $cm, $updatedcourse);
        $this->assertTrue($assignment->get_submission_plugin_by_type("file")->is_enabled());

        $update = new \mcptool_assign\write\update_activity();
        $response = $update->execute(["cmid" => $created["cmid"], "name" => "Assignment Two"], $identity);
        $this->assertTrue($response["updated"]);
        $this->assertSame("Assignment Two",
            $DB->get_field("assign", "name", ["id" => $created["instanceid"]]));
        $assignment = new \assign(\context_module::instance($cm->id), $cm, $updatedcourse);
        $this->assertTrue($assignment->get_submission_plugin_by_type("file")->is_enabled());
        $this->assertTrue($assignment->get_submission_plugin_by_type("onlinetext")->is_enabled());
    }

    public function test_assignment_creation_has_supported_moodle_capability(): void {
        $tool = new \mcptool_assign\write\create_activity();
        $this->assertSame("assign_create_activity", $tool->get_name());
        $this->assertSame("moodle/course:manageactivities", $tool->get_required_capability());
        $this->assertContains("courseid", $tool->get_input_schema()["required"]);
        $this->assertContains("sectionnum", $tool->get_input_schema()["required"]);
    }
}
