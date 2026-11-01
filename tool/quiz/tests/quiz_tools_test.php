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
 * Regression and permission tests for quiz MCP tools.
 *
 * @package   mcptool_quiz
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_quiz;

use advanced_testcase;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;

/**
 * @covers \mcptool_quiz\provider
 * @covers \mcptool_quiz\quiz_service
 * @covers \mcptool_quiz\attempt_service
 * @covers \mcptool_quiz\regrade_service
 * @covers \mcptool_quiz\question_usage_service
 */
final class quiz_tools_test extends advanced_testcase {
    /**
     * Method test_registration_and_write_confirmation.
     *
     * @return void Return value.
     */
    public function test_registration_and_write_confirmation(): void {
        $p = new provider();
        $read = $p->get_read_tools();
        $write = $p->get_write_tools();
        $this->assertCount(7, $read);
        $this->assertCount(6, $write);
        $names = [];
        foreach (array_merge($read, $write) as $tool) {
            $names[] = $tool->get_name();
            $this->assertSame("object", $tool->get_input_schema()["type"]);
        }
        $this->assertSameSize(array_unique($names), $names);
        $this->assertContains('quiz_get_proctoring_status', $names);
        foreach ($write as $tool) {
            $this->assertTrue($tool->requires_confirmation());
        }
        $regrade = new \mcptool_quiz\write\regrade_attempt();
        $this->assertContains("expected_timemodified", $regrade->get_input_schema()["required"]);
        $this->assertContains("expected_sumgrades", $regrade->get_input_schema()["required"]);
    }

    /**
     * The optional status check must work without the Proctoring plugin.
     */
    public function test_proctoring_status_without_optional_plugin(): void {
        if (get_config('local_kopere_proctoring', 'version') !== false) {
            $this->markTestSkipped('This test covers the installation without Kopere Proctoring.');
        }
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $identity = new authenticated_identity($USER->id, 'test', ['mcp:read']);
        $tool = new \mcptool_quiz\read\get_proctoring_status();

        $result = $tool->execute(['cmid' => $module->cmid], $identity);
        $this->assertFalse($result['installed']);
        $this->assertFalse($result['available']);
        $this->assertSame('not_installed', $result['status']);
        $this->assertSame($module->cmid, $result['cmid']);
        $this->assertSame(
            \mcptool_quiz\read\get_proctoring_status::MARKETPLACE_URL,
            $result['marketplace_url']
        );
        $this->assertArrayNotHasKey('policies', $result);
    }

    /**
     * Only users who may read quiz reports can obtain proctoring settings.
     */
    public function test_student_cannot_read_proctoring_settings(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);
        $tool = new \mcptool_quiz\read\get_proctoring_status();
        $identity = new authenticated_identity($student->id, 'test', ['mcp:read']);

        $this->assertSame('mod/quiz:viewreports', $tool->get_required_capability());
        $this->expectException(api_exception::class);
        $tool->execute(['cmid' => $module->cmid], $identity);
    }

    /**
     * Method test_student_cannot_manage_quiz_slots.
     *
     * @return void Return value.
     */
    public function test_student_cannot_manage_quiz_slots(): void {
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

    /**
     * Method test_updating_quiz_does_not_change_unrelated_settings.
     *
     * @return void Return value.
     */
    public function test_updating_quiz_does_not_change_unrelated_settings(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module("quiz", ["course" => $course->id]);
        $old = $DB->get_record("quiz", ["id" => $module->id], "*", MUST_EXIST);
        $identity = new authenticated_identity($USER->id, "test", ["mcp:write"]);
        quiz_service::update(["cmid" => $module->cmid,
            "name" => "Updated quiz title", "timelimit" => 900], $identity);
        $new = $DB->get_record("quiz", ["id" => $module->id], "*", MUST_EXIST);
        $this->assertSame("Updated quiz title", $new->name);
        $this->assertEquals(900, $new->timelimit);
        $this->assertSame($old->grademethod, $new->grademethod);
        $this->assertSame($old->preferredbehaviour, $new->preferredbehaviour);
    }
}
