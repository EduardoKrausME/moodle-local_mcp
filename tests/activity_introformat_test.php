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
 * Regression tests for HTML descriptions in MCP activity tools.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\security\authenticated_identity;

/**
 * Verify that editor drafts and stored description formats remain consistent.
 *
 * @covers \local_mcp\write\tool\activity_create_base
 * @covers \mcptool_book\write\create_activity
 * @covers \mcptool_page\write\create_activity
 * @covers \mcptool_forum\write\create_activity
 * @covers \mcptool_label\write\create_activity
 * @covers \mcptool_url\write\create_activity
 * @covers \mcptool_assign\write\update_activity
 * @covers \mcptool_quiz\quiz_service
 */
final class activity_introformat_test extends advanced_testcase {
    /**
     * Direct activity creators must keep the HTML description and page body.
     */
    public function test_direct_creators_preserve_html_description_and_page_body(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $identity = new authenticated_identity((int)get_admin()->id, "test", ["mcp:write"]);
        $tools = [
            "book" => new \mcptool_book\write\create_activity(),
            "page" => new \mcptool_page\write\create_activity(),
            "forum" => new \mcptool_forum\write\create_activity(),
            "url" => new \mcptool_url\write\create_activity(),
        ];

        foreach ($tools as $modulename => $tool) {
            $intro = "<p><strong>HTML description for " . $modulename . "</strong></p>";
            $args = [
                "courseid" => (int)$course->id,
                "sectionnum" => 0,
                "name" => "MCP HTML " . $modulename,
                "intro" => $intro,
            ];
            if ($modulename === "page") {
                $args["content"] = "<p><em>Page body content</em></p>";
            }
            if ($modulename === "url") {
                $args["externalurl"] = "https://example.com/";
            }
            $created = $tool->execute($args, $identity);
            $cm = get_coursemodule_from_id($modulename, (int)$created["cmid"], 0, false, MUST_EXIST);
            $this->assertSame(1, (int)$cm->showdescription);
            $args["showdescription"] = false;
            $args["name"] .= " hidden description";
            $hidden = $tool->execute($args, $identity);
            $hiddencm = get_coursemodule_from_id($modulename, (int)$hidden["cmid"], 0, false, MUST_EXIST);
            $this->assertSame(0, (int)$hiddencm->showdescription);
            $record = $DB->get_record($modulename, ["id" => $created["instanceid"]], "*", MUST_EXIST);
            $this->assertSame($intro, $record->intro);
            $this->assertSame((int)FORMAT_HTML, (int)$record->introformat);

            if ($modulename === "page") {
                $this->assertSame($args["content"], $record->content);
                $this->assertSame((int)FORMAT_HTML, (int)$record->contentformat);
                require_once($CFG->dirroot . "/course/modlib.php");
                $cm = get_coursemodule_from_id("page", (int)$created["cmid"], 0, false, MUST_EXIST);
                [, , , $formdata] = get_moduleinfo_data($cm, $course);
                $this->assertTrue(property_exists($formdata, "content"));
                $this->assertSame($args["content"], $formdata->content);
                $this->assertSame((int)FORMAT_HTML, (int)$formdata->contentformat);
            }
        }
    }

    /**
     * Module-specific configuration must not be overwritten by an empty editor draft.
     */
    public function test_label_creator_preserves_configured_html_content(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $identity = new authenticated_identity((int)get_admin()->id, "test", ["mcp:write"]);
        $content = "<p><strong>Label HTML body</strong></p>";
        $created = (new \mcptool_label\write\create_activity())->execute([
            "courseid" => (int)$course->id,
            "sectionnum" => 0,
            "content" => $content,
        ], $identity);

        $record = $DB->get_record("label", ["id" => $created["instanceid"]], "*", MUST_EXIST);
        $this->assertSame($content, $record->intro);
        $this->assertSame((int)FORMAT_HTML, (int)$record->introformat);
        $created = (new \mcptool_label\write\create_activity())->execute([
            "courseid" => (int)$course->id,
            "sectionnum" => 0,
            "intro" => $content,
        ], $identity);
        $record = $DB->get_record("label", ["id" => $created["instanceid"]], "*", MUST_EXIST);
        $this->assertSame($content, $record->intro);
        $this->assertSame((int)FORMAT_HTML, (int)$record->introformat);
    }

    /**
     * All bundled activity creation schemas share the public description contract.
     */
    public function test_activity_creation_description_schema(): void {
        foreach (["assign", "book", "feedback", "forum", "glossary", "h5p", "label", "lesson",
            "page", "quiz", "resource", "url"] as $module) {
            $classname = "\\mcptool_" . $module . "\\write\\create_activity";
            $schema = (new $classname())->get_input_schema();
            $this->assertSame("string", $schema["properties"]["intro"]["type"], $module);
            $this->assertArrayNotHasKey("introformat", $schema["properties"], $module);
            $this->assertSame("boolean", $schema["properties"]["showdescription"]["type"], $module);
            $this->assertTrue($schema["properties"]["showdescription"]["default"], $module);
            $this->assertFalse($schema["additionalProperties"], $module);
        }
    }

    /**
     * HTML description updates must also correct a pre-existing plain-text format.
     */
    public function test_assignment_and_quiz_updates_set_html_description_format(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $identity = new authenticated_identity((int)get_admin()->id, "test", ["mcp:write"]);
        $tools = [
            "assign" => new \mcptool_assign\write\update_activity(),
            "quiz" => new \mcptool_quiz\write\update_activity(),
        ];

        foreach ($tools as $modulename => $tool) {
            $instance = $this->getDataGenerator()->create_module($modulename, [
                "course" => $course->id,
                "name" => "Update intro " . $modulename,
            ]);
            $DB->set_field($modulename, "introformat", FORMAT_PLAIN, ["id" => $instance->id]);
            $cm = get_coursemodule_from_instance($modulename, $instance->id, $course->id, false, MUST_EXIST);
            $intro = "<p><strong>Updated HTML description</strong></p>";
            $tool->execute(["cmid" => (int)$cm->id, "intro" => $intro], $identity);
            $updated = $DB->get_record($modulename, ["id" => $instance->id], "*", MUST_EXIST);
            $this->assertSame($intro, $updated->intro);
            $this->assertSame((int)FORMAT_HTML, (int)$updated->introformat);
        }
    }
}
