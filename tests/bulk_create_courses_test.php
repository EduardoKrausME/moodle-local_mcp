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
 * Tests bulk creation of Moodle courses using MCP.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use core_course_category;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\write\registry;
use local_mcp\write\tool\bulk_create_courses;

defined('MOODLE_INTERNAL') || die();

/**
 * Batch creation must be safe to retry and avoid modifying existing courses.
 */
final class bulk_create_courses_test extends advanced_testcase {
    /**
     * @return authenticated_identity
     */
    private function admin_identity(): authenticated_identity {
        $admin = get_admin();
        $this->setUser($admin);
        return new authenticated_identity((int)$admin->id, 'test', ['mcp:read', 'mcp:write']);
    }

    /**
     * @param int $categoryid Destination category.
     * @param string $code Shortname suffix.
     * @return array
     */
    private function course(int $categoryid, string $code): array {
        return [
            'fullname' => 'CITTA Course ' . $code,
            'shortname' => 'CITTA-' . $code,
            'idnumber' => 'CITTA-ID-' . $code,
            'categoryid' => $categoryid,
            'summary' => '<p><strong>Cidades Inteligentes</strong> — ' . $code . '</p>',
            'summaryformat' => 1,
            'visible' => false,
        ];
    }

    /**
     * @return void
     */
    public function test_registration_and_schema(): void {
        $tools = registry::get_tools();
        $this->assertArrayHasKey('bulk_create_courses', $tools);
        $this->assertInstanceOf(bulk_create_courses::class, $tools['bulk_create_courses']);
        $schema = $tools['bulk_create_courses']->get_input_schema();
        $this->assertSame(30, $schema['properties']['courses']['maxItems']);
        $this->assertContains('summary', $schema['properties']['courses']['items']['required']);
        $this->assertTrue($tools['bulk_create_courses']->requires_confirmation());
        $this->assertTrue($tools['bulk_create_courses']->supports_dry_run());
    }

    /**
     * @return void
     */
    public function test_preview_and_create_two_complete_courses(): void {
        global $DB;
        $this->resetAfterTest();
        $identity = $this->admin_identity();
        $category = core_course_category::create(['name' => 'CITTA regular']);
        $tool = new bulk_create_courses();
        $arguments = ['courses' => [$this->course($category->id, 'A'),
            $this->course($category->id, 'B')]];
        $preview = $tool->preview($arguments, $identity);
        $this->assertSame(2, $preview['to_create']);
        $this->assertSame(0, $preview['already_existing']);
        $this->assertSame('create', $preview['courses'][0]['action']);
        $this->assertArrayNotHasKey('summary', $preview['courses'][0]);
        $this->assertArrayNotHasKey('image_base64', $preview['courses'][0]);

        $result = $tool->execute($arguments, $identity);
        $this->assertSame(2, $result['created_count']);
        $this->assertSame(0, $result['skipped_count']);
        foreach ($result['created'] as $course) {
            $record = $DB->get_record('course', ['id' => $course['id']], '*', MUST_EXIST);
            $this->assertSame(FORMAT_HTML, (int)$record->summaryformat);
            $this->assertStringContainsString('<strong>Cidades Inteligentes</strong>', $record->summary);
            $this->assertSame(0, (int)$record->visible);
            $this->assertSame((int)$category->id, (int)$record->category);
        }
    }

    /**
     * @return void
     */
    public function test_existing_shortcode_or_idnumber_is_skipped_without_modification(): void {
        global $DB;
        $this->resetAfterTest();
        $identity = $this->admin_identity();
        $category = core_course_category::create(['name' => 'CITTA regular']);
        $original = $this->getDataGenerator()->create_course([
            'category' => $category->id,
            'shortname' => 'CITTA-A',
            'idnumber' => 'CITTA-ID-A',
            'summary' => '<p>Original preserved</p>',
            'summaryformat' => FORMAT_HTML,
        ]);

        $tool = new bulk_create_courses();
        $args = ['courses' => [$this->course($category->id, 'A'),
            $this->course($category->id, 'B')]];
        $preview = $tool->preview($args, $identity);
        $this->assertSame(1, $preview['to_create']);
        $this->assertSame(1, $preview['already_existing']);
        $this->assertSame((int)$original->id, $preview['courses'][0]['existing_course']['id']);
        $result = $tool->execute($args, $identity);
        $this->assertSame(1, $result['created_count']);
        $this->assertSame(1, $result['skipped_count']);
        $unchanged = $DB->get_record('course', ['id' => $original->id], '*', MUST_EXIST);
        $this->assertSame('<p>Original preserved</p>', $unchanged->summary);
    }

    /**
     * @return void
     */
    public function test_idnumber_only_match_skips_existing_course(): void {
        global $DB;
        $this->resetAfterTest();
        $identity = $this->admin_identity();
        $category = core_course_category::create(['name' => 'CITTA regular']);
        $existing = $this->getDataGenerator()->create_course([
            'category' => $category->id,
            'shortname' => 'EXISTING-OLD-CODE',
            'idnumber' => 'CITTA-ID-A',
        ]);
        $tool = new bulk_create_courses();
        $args = ['courses' => [$this->course($category->id, 'A')]];
        $preview = $tool->preview($args, $identity);
        $this->assertSame(0, $preview['to_create']);
        $this->assertSame((int)$existing->id, $preview['courses'][0]['existing_course']['id']);
        $result = $tool->execute($args, $identity);
        $this->assertSame(0, $result['created_count']);
        $this->assertSame(1, $result['skipped_count']);
        $this->assertFalse($DB->record_exists('course', ['shortname' => 'CITTA-A']));
    }

    /**
     * @return void
     */
    public function test_duplicate_shortnames_within_batch_are_rejected(): void {
        $this->resetAfterTest();
        $identity = $this->admin_identity();
        $category = core_course_category::create(['name' => 'CITTA regular']);
        $course = $this->course($category->id, 'A');
        $this->expectException(api_exception::class);
        (new bulk_create_courses())->preview(['courses' => [$course, $course]], $identity);
    }

    /**
     * @return void
     */
    public function test_excluded_category_including_descendants_is_rejected(): void {
        $this->resetAfterTest();
        $identity = $this->admin_identity();
        $test = core_course_category::create(['name' => 'Teste']);
        $child = core_course_category::create(['name' => 'Old training',
            'parent' => $test->id]);
        $this->expectException(api_exception::class);
        (new bulk_create_courses())->preview([
            'courses' => [$this->course($child->id, 'A')],
            'excluded_categoryids' => [$test->id],
        ], $identity);
    }

    /**
     * @return void
     */
    public function test_cannot_create_incomplete_courses_without_html_summary(): void {
        $this->resetAfterTest();
        $identity = $this->admin_identity();
        $category = core_course_category::create(['name' => 'CITTA regular']);
        $course = $this->course($category->id, 'A');
        unset($course['summary']);
        $this->expectException(api_exception::class);
        (new bulk_create_courses())->preview(['courses' => [$course]], $identity);
    }

    /**
     * @return void
     */
    public function test_rejects_batches_larger_than_thirty(): void {
        $this->resetAfterTest();
        $identity = $this->admin_identity();
        $category = core_course_category::create(['name' => 'CITTA regular']);
        $courses = [];
        for ($i = 0; $i < 31; $i++) {
            $courses[] = $this->course($category->id, (string)$i);
        }
        $this->expectException(api_exception::class);
        (new bulk_create_courses())->preview(['courses' => $courses], $identity);
    }
}
