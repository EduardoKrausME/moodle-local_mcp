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
 * Completion tracking and badge activity criteria integration tests.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\exception\api_exception;
use local_mcp\read\tool\get_course_completion_config;
use local_mcp\security\authenticated_identity;
use local_mcp\write\tool\set_course_completion_config;
use mcptool_badges\write\create_badge;
use mcptool_badges\write\enable_badge;
use mcptool_badges\write\set_criteria;

/**
 * End-to-end badge configuration through the native Moodle criteria and status APIs.
 *
 * @covers \mcptool_badges\write\set_criteria
 * @covers \mcptool_badges\write\enable_badge
 * @covers \local_mcp\read\tool\get_course_completion_config
 * @covers \local_mcp\write\tool\set_course_completion_config
 */
final class badge_criteria_completion_test extends advanced_testcase {
    /**
     * @return authenticated_identity
     */
    private function identity(): authenticated_identity {
        global $USER;
        return new authenticated_identity((int)$USER->id, "test", ["mcp:read", "mcp:write"]);
    }

    /**
     * @return string
     */
    private function png(): string {
        $image = imagecreatetruecolor(256, 256);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
        imagefill($image, 0, 0, $transparent);
        $color = imagecolorallocatealpha($image, 15, 65, 140, 0);
        imagefilledellipse($image, 128, 128, 140, 140, $color);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);
        return $bytes;
    }

    /**
     * An activity must be automatically completed before it can award a badge.
     *
     * @return void
     */
    public function test_configure_criteria_then_enable_badge(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config("enablebadges", 1);
        set_config("badges_allowcoursebadges", 1);
        set_config("enablecompletion", 1);
        $CFG->gdversion = 2;
        $course = $this->getDataGenerator()->create_course(["enablecompletion" => 1]);
        $page = $this->getDataGenerator()->create_module("page", [
            "course" => $course->id, "name" => "Read this page",
            "content" => "Content", "completion" => COMPLETION_TRACKING_AUTOMATIC, "completionview" => 1,
        ]);
        $identity = $this->identity();
        $newbadge = (new create_badge())->execute([
            "courseid" => (int)$course->id, "name" => "Course reader",
            "description" => "Completed required reading.",
            "image_base64" => base64_encode($this->png()),
        ], $identity);
        $id = (int)$newbadge["badgeid"];
        $before = (int)$DB->get_field("badge", "timemodified", ["id" => $id]);
        $tool = new set_criteria();
        $args = ["badgeid" => $id, "expected_timemodified" => $before,
            "cmids" => [(int)$page->cmid]];
        $this->assertSame("ALL", $tool->preview($args, $identity)["aggregation"]);
        $this->assertSame(0, $DB->count_records("badge_criteria", ["badgeid" => $id]));
        $result = $tool->execute($args, $identity);
        $this->assertFalse($result["enabled"]);
        $this->assertCount(2, $DB->get_records("badge_criteria", ["badgeid" => $id]));
        $this->expectException(api_exception::class);
        $tool->preview($args, $identity);
    }

    /**
     * Valid badge criteria can be explicitly activated and then locked from edits.
     *
     * @return void
     */
    public function test_enable_requires_criteria_and_preserves_issuance_guard(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config("enablebadges", 1);
        set_config("badges_allowcoursebadges", 1);
        set_config("enablecompletion", 1);
        $CFG->gdversion = 2;
        $course = $this->getDataGenerator()->create_course(["enablecompletion" => 1]);
        $page = $this->getDataGenerator()->create_module("page", [
            "course" => $course->id, "name" => "Chapter",
            "content" => "Content", "completion" => COMPLETION_TRACKING_AUTOMATIC, "completionview" => 1,
        ]);
        $identity = $this->identity();
        $id = (int)(new create_badge())->execute([
            "courseid" => (int)$course->id, "name" => "Completed Moodle step",
            "description" => "Completed selected activity.",
            "image_base64" => base64_encode($this->png()),
        ], $identity)["badgeid"];
        $before = (int)$DB->get_field("badge", "timemodified", ["id" => $id]);
        $enable = new enable_badge();
        try {
            $enable->preview(["badgeid" => $id, "expected_timemodified" => $before], $identity);
            $this->fail("Badge without criteria should be rejected.");
        } catch (api_exception $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        (new set_criteria())->execute(["badgeid" => $id,
            "expected_timemodified" => $before, "cmids" => [(int)$page->cmid]], $identity);
        $current = (int)$DB->get_field("badge", "timemodified", ["id" => $id]);
        $arguments = ["badgeid" => $id, "expected_timemodified" => $current];
        $this->assertTrue($enable->preview($arguments, $identity)["may_issue_to_previously_completed_students"]);
        $this->assertTrue($enable->execute($arguments, $identity)["enabled"]);
        $this->expectException(api_exception::class);
        $enable->preview($arguments, $identity);
    }

    /**
     * Course tracking can be switched on without altering user progress.
     *
     * @return void
     */
    public function test_enable_course_completion_tracking(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config("enablecompletion", 1);
        $course = $this->getDataGenerator()->create_course(["enablecompletion" => 0]);
        $identity = $this->identity();
        $reader = new get_course_completion_config();
        $before = $reader->execute(["courseid" => (int)$course->id], $identity);
        $this->assertFalse($before["course_enabled"]);
        $tool = new set_course_completion_config();
        $args = ["courseid" => (int)$course->id, "expected_enablecompletion" => 0];
        $this->assertSame(1, $tool->preview($args, $identity)["after"]);
        $this->assertTrue($tool->execute($args, $identity)["course_enabled"]);
        $after = $reader->execute(["courseid" => (int)$course->id], $identity);
        $this->assertTrue($after["course_enabled"]);
        $this->expectException(api_exception::class);
        $tool->preview($args, $identity);
    }
}
