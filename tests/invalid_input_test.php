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
 * Regression coverage for MCP negative-input handling and confirmation previews.
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
 * Verify that invalid client arguments never become unexpected internal exceptions.
 *
 * @covers \local_mcp\security\input_guard
 * @covers \local_mcp\question\bank_service
 * @covers \local_mcp\read\tool\get_question
 * @covers \local_mcp\read\tool\get_assignment
 * @covers \local_mcp\read\tool\get_submissions
 * @covers \local_mcp\write\tool\update_question
 * @covers \local_mcp\write\tool\suspend_user
 * @covers \local_mcp\write\tool\grade_submission
 * @covers \mcptool_book\read\get_chapter
 * @covers \mcptool_book\write\update_chapter
 * @covers \mcptool_forum\read\get_posts
 * @covers \mcptool_forum\write\reply_to_post
 * @covers \mcptool_quiz\write\add_question
 */
final class invalid_input_test extends advanced_testcase {
    /**
     * Missing questions, users, chapters and discussion records return API 404 responses.
     */
    public function test_missing_resource_ids_have_stable_error_codes(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $missingid = 999999999;
        $cases = [
            [new \local_mcp\read\tool\get_question(), ["questionid" => $missingid], "question_not_found"],
            [new \local_mcp\write\tool\update_question(), ["questionid" => $missingid], "question_not_found"],
            [new \local_mcp\read\tool\get_assignment(), ["cmid" => $missingid], "activity_not_found"],
            [new \local_mcp\read\tool\get_submissions(), ["cmid" => $missingid], "activity_not_found"],
            [new \local_mcp\write\tool\suspend_user(), ["userid" => $missingid], "user_not_found"],
            [new \mcptool_book\read\get_chapter(), ["chapterid" => $missingid], "chapter_not_found"],
            [new \mcptool_book\write\update_chapter(), ["chapterid" => $missingid], "chapter_not_found"],
            [new \mcptool_forum\read\get_posts(), ["discussionid" => $missingid], "discussion_not_found"],
            [new \mcptool_forum\write\reply_to_post(), ["postid" => $missingid], "post_not_found"],
        ];
        foreach ($cases as [$tool, $arguments, $code]) {
            try {
                $tool->resolve_context($arguments);
                $this->fail($tool->get_name() . " must reject an unknown identifier.");
            } catch (api_exception $e) {
                $this->assertSame($code, $e->machinecode, $tool->get_name());
                $this->assertSame(404, $e->httpstatus, $tool->get_name());
            }
        }
    }

    /**
     * A quiz CMID cannot be used to preview or read assignment grades.
     */
    public function test_assignment_tools_reject_wrong_module_type(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module("quiz", ["course" => $course->id]);
        foreach ([new \local_mcp\read\tool\get_assignment(),
                  new \local_mcp\read\tool\get_submissions(),
                  new \local_mcp\write\tool\grade_submission()] as $tool) {
            try {
                $tool->resolve_context(["cmid" => (int)$quiz->cmid]);
                $this->fail($tool->get_name() . " must reject a quiz CMID.");
            } catch (api_exception $e) {
                $this->assertSame("activity_not_found", $e->machinecode);
                $this->assertSame(404, $e->httpstatus);
            }
        }
        $identity = new authenticated_identity((int)$USER->id, "test", ["mcp:write"]);
        try {
            (new \local_mcp\write\tool\grade_submission())->preview(
                ["cmid" => (int)$quiz->cmid, "userid" => (int)$USER->id, "grade" => 1], $identity
            );
            $this->fail("A quiz must never produce an assignment grading preview.");
        } catch (api_exception $e) {
            $this->assertSame("activity_not_found", $e->machinecode);
        }
    }

    /**
     * The question must exist before a quiz addition can be approved.
     */
    public function test_quiz_preview_rejects_unknown_question(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module("quiz", ["course" => $course->id]);
        $identity = new authenticated_identity((int)$USER->id, "test", ["mcp:write"]);
        $tool = new \mcptool_quiz\write\add_question();
        try {
            $tool->preview(["cmid" => (int)$quiz->cmid, "questionid" => 999999999], $identity);
            $this->fail("A nonexistent question must not receive an approval preview.");
        } catch (api_exception $e) {
            $this->assertSame("question_not_found", $e->machinecode);
            $this->assertSame(404, $e->httpstatus);
        }
    }
}
