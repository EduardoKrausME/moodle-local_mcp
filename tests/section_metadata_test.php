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
 * Section metadata regression tests.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\exception\api_exception;
use local_mcp\read\tool\get_course_sections;
use local_mcp\security\authenticated_identity;
use local_mcp\write\tool\update_section;

/**
 * @covers \local_mcp\write\tool\update_section
 * @covers \local_mcp\read\tool\get_course_contents
 */
final class section_metadata_test extends advanced_testcase {
    /** Verify preview, native update, readback, stale hash and module preservation. */
    public function test_update_section_summary_preserves_modules(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id], ['section' => 0]);
        $identity = new authenticated_identity($USER->id, 'test', ['mcp:read', 'mcp:write']);
        $tool = new update_section();
        $args = ['courseid' => (int)$course->id, 'sectionnum' => 0,
            'name' => 'Introduction', 'summary' => '<p><strong>Learning goals</strong></p>', 'summaryformat' => 1];
        $before = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 0], '*', MUST_EXIST);
        $preview = $tool->preview($args, $identity);
        $this->assertTrue($tool->requires_confirmation());
        $this->assertTrue($tool->supports_dry_run());
        $this->assertSame((int)FORMAT_HTML, $preview["changes"]["summaryformat"]);
        $this->assertSame($before->summary, $DB->get_field('course_sections', 'summary', ['id' => $before->id]));
        $args['expected_section_sha256'] = $preview['section_sha256'];
        $result = $tool->execute($args, $identity);
        $this->assertSame($args['summary'], $result['summary']);
        $this->assertSame((int)FORMAT_HTML, $result["summaryformat"]);
        $this->assertSame((int)$before->id, $result['sectionid']);
        $this->assertSame($before->sequence, $DB->get_field('course_sections', 'sequence', ['id' => $before->id]));
        $this->assertTrue($DB->record_exists('course_modules', ['id' => $page->cmid, 'section' => $before->id]));
        $sections = (new get_course_sections())->execute(['courseid' => (int)$course->id], $identity);
        $this->assertSame($result['section_sha256'], $sections['sections'][0]['section_sha256']);
        $this->assertSame($args['summary'], $sections['sections'][0]['summary']);
        $this->assertSame((int)FORMAT_HTML, $sections["sections"][0]["summaryformat"]);
        $this->expectException(api_exception::class);
        $tool->execute($args, $identity);
    }

    /** Omitted summary is preserved, an empty summary clears it. */
    public function test_partial_update_and_clear(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $identity = new authenticated_identity($USER->id, 'test', ['mcp:write']);
        $tool = new update_section();
        $base = ['courseid' => (int)$course->id, 'sectionnum' => 0];
        $tool->execute($base + ['summary' => '<p>Original</p>'], $identity);
        $result = $tool->execute($base + ['name' => 'New name'], $identity);
        $this->assertSame('<p>Original</p>', $result['summary']);
        $result = $tool->execute($base + ['summary' => ''], $identity);
        $this->assertSame('', $result['summary']);
        $this->assertSame('New name', $result['name']);
    }

    /** A non-HTML format or a format without a summary must not be accepted. */
    public function test_invalid_summaryformat_rejected(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $identity = new authenticated_identity($USER->id, "test", ["mcp:write"]);
        $tool = new update_section();
        $base = ["courseid" => (int)$course->id, "sectionnum" => 0];
        foreach ([["summary" => "<p>Invalid</p>", "summaryformat" => 0], ["summaryformat" => 1]] as $invalid) {
            try {
                $tool->preview($base + $invalid, $identity);
                $this->fail("Invalid summary format was accepted.");
            } catch (api_exception $e) {
                $this->assertSame("invalid_summaryformat", $e->machinecode);
            }
        }
    }

    /** Students cannot edit a section by calling the write tool directly. */
    public function test_student_cannot_update_section(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);
        $identity = new authenticated_identity($student->id, 'test', ['mcp:write']);
        $this->expectException(api_exception::class);
        (new update_section())->preview(['courseid' => (int)$course->id, 'sectionnum' => 0,
            'summary' => 'Not allowed'], $identity);
    }
}
