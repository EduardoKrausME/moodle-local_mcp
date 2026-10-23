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
 * Integration tests for Feedback and Lesson MCP providers.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;

/**
 * Test real module creation, question storage, lesson navigation and access controls.
 *
 * @covers \mcptool_feedback\provider
 * @covers \mcptool_feedback\service
 * @covers \mcptool_feedback\write\create_activity
 * @covers \mcptool_lesson\provider
 * @covers \mcptool_lesson\service
 * @covers \mcptool_lesson\write\create_activity
 */
final class feedback_lesson_tools_test extends advanced_testcase {
    public function test_both_providers_expose_confirmed_reads_and_writes(): void {
        foreach ([
            new \mcptool_feedback\provider(),
            new \mcptool_lesson\provider(),
        ] as $provider) {
            $read = $provider->get_read_tools();
            $write = $provider->get_write_tools();
            $this->assertNotEmpty($read);
            $this->assertNotEmpty($write);
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
    }

    public function test_feedback_creation_questions_and_anonymous_report_suppression(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $identity = new authenticated_identity((int)$USER->id, "test", ["mcp:read", "mcp:write"]);

        $create = new \mcptool_feedback\write\create_activity();
        $result = $create->execute([
            "courseid" => $course->id, "sectionnum" => 0, "name" => "Survey",
            "anonymous" => true,
        ], $identity);
        $this->assertGreaterThan(0, $result["cmid"]);
        $state = \mcptool_feedback\service::questions(["cmid" => $result["cmid"]], $identity);
        $added = \mcptool_feedback\service::save_question([
            "cmid" => $result["cmid"], "type" => "multichoice",
            "name" => "How was this lesson?", "options" => ["Good", "Excellent"],
            "expected_items_sha256" => $state["items_sha256"],
        ], $identity, false);
        $this->assertGreaterThan(0, $added["itemid"]);
        $questions = \mcptool_feedback\service::questions(["cmid" => $result["cmid"]], $identity);
        $this->assertCount(1, $questions["items"]);
        $this->assertSame(["Good", "Excellent"], $questions["items"][0]["options"]);
        $summary = \mcptool_feedback\service::summary(["cmid" => $result["cmid"]], $identity);
        $this->assertTrue($summary["anonymous"]);
        $this->assertTrue($summary["suppressed_small_anonymous_cohort"]);
        $this->assertNull($summary["responses_total"]);
        $responses = \mcptool_feedback\service::responses([
            "cmid" => $result["cmid"], "itemid" => $added["itemid"],
        ], $identity);
        $this->assertTrue($responses["suppressed_small_cohort"]);
    }

    public function test_lesson_creation_and_conditional_page_navigation(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $identity = new authenticated_identity((int)$USER->id, "test", ["mcp:read", "mcp:write"]);

        $create = new \mcptool_lesson\write\create_activity();
        $result = $create->execute(["courseid" => $course->id, "sectionnum" => 0,
            "name" => "Decision lesson", "maxanswers" => 4], $identity);
        $first = \mcptool_lesson\service::navigation(["cmid" => $result["cmid"]], $identity);
        $this->assertSame(0, $first["total_pages"]);
        $page = \mcptool_lesson\service::create([
            "cmid" => $result["cmid"], "title" => "Make your choice",
            "type" => "content", "content" => "<p>Select a route.</p>",
            "expected_navigation_sha256" => $first["navigation_sha256"],
            "choices" => [["text" => "Finish lesson", "jump" => "end"]],
        ], $identity);
        $this->assertGreaterThan(0, $page["pageid"]);
        $navigation = \mcptool_lesson\service::navigation(["cmid" => $result["cmid"]], $identity);
        $this->assertSame(1, $navigation["total_pages"]);
        $this->assertCount(1, $navigation["pages"][0]["choices"]);
        $detail = \mcptool_lesson\service::page([
            "cmid" => $result["cmid"], "pageid" => $page["pageid"],
        ], $identity);
        $this->assertStringContainsString("Select a route", $detail["content"]);
    }

    public function test_student_cannot_read_feedback_responses(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module("feedback", ["course" => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, "student");
        $this->setUser($student);
        $identity = new authenticated_identity((int)$student->id, "test", ["mcp:read"]);
        $this->expectException(api_exception::class);
        \mcptool_feedback\service::summary(["cmid" => $module->cmid], $identity);
    }
}
