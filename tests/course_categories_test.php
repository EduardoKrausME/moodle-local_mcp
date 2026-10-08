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
 * Tests course category and course creation MCP tools.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use core_course_category;
use local_mcp\exception\api_exception;
use local_mcp\read\tool\list_categories;
use local_mcp\security\authenticated_identity;
use local_mcp\write\tool\create_category;
use local_mcp\write\tool\update_category;
use local_mcp\write\tool\create_course;

defined('MOODLE_INTERNAL') || die;

/**
 * Exercising course/category tool operations on test Moodle data.
 */
final class course_categories_test extends advanced_testcase {
    /**
     * Ensure the new operations are discoverable.
     *
     * @return void
     */
    public function test_category_tools_are_registered(): void {
        $read = \local_mcp\read\registry::get_tools();
        $write = \local_mcp\write\registry::get_tools();
        $this->assertInstanceOf(list_categories::class, $read['list_categories']);
        $this->assertInstanceOf(create_category::class, $write['create_category']);
        $this->assertInstanceOf(update_category::class, $write['update_category']);
    }

    /**
     * @return authenticated_identity
     */
    private function admin_identity(): authenticated_identity {
        $admin = get_admin();
        $this->setUser($admin);
        return new authenticated_identity((int)$admin->id, 'test', ['mcp:read', 'mcp:write']);
    }

    /**
     * @return void
     */
    public function test_create_and_list_categories(): void {
        $this->resetAfterTest();
        $identity = $this->admin_identity();
        $create = new create_category();
        $before = $create->preview(['name' => 'Teste', 'parentid' => 0], $identity);
        $this->assertSame('Teste', $before['name']);
        $result = $create->execute(['name' => 'Teste', 'parentid' => 0], $identity);
        $this->assertSame(0, $result['parentid']);
        $listed = (new list_categories())->execute([
            'includehidden' => true, 'includecoursecount' => true,
        ], $identity);
        $matching = array_values(array_filter($listed['categories'],
            static fn(array $item): bool => $item['id'] === $result['id']));
        $this->assertCount(1, $matching);
        $this->assertSame('Teste', $matching[0]['name']);
        $this->assertSame(0, $matching[0]['parent']);
        $this->assertSame(0, $matching[0]['coursecount']);
        $this->assertArrayHasKey('visible', $matching[0]);
    }

    /**
     * @return void
     */
    public function test_move_category_preserves_subcategories_and_courses(): void {
        global $DB;
        $this->resetAfterTest();
        $identity = $this->admin_identity();

        $oldtop = core_course_category::create(['name' => 'Original']);
        $newtop = core_course_category::create(['name' => 'Teste']);
        $child = core_course_category::create(['name' => 'Child', 'parent' => $oldtop->id]);
        $grandchild = core_course_category::create(['name' => 'Nested', 'parent' => $child->id]);
        $course = $this->getDataGenerator()->create_course(['category' => $grandchild->id]);

        $update = new update_category();
        $preview = $update->preview(['categoryid' => $oldtop->id, 'parentid' => $newtop->id], $identity);
        $this->assertTrue($preview['courses_remain_in_their_categories']);
        $changed = $update->execute(['categoryid' => $oldtop->id, 'parentid' => $newtop->id], $identity);
        $this->assertSame((int)$newtop->id, $changed['parentid']);

        $this->assertSame((int)$oldtop->id, (int)$DB->get_field('course_categories', 'parent',
            ['id' => $child->id]));
        $this->assertSame((int)$child->id, (int)$DB->get_field('course_categories', 'parent',
            ['id' => $grandchild->id]));
        $this->assertSame((int)$grandchild->id, (int)$DB->get_field('course', 'category',
            ['id' => $course->id]));
    }

    /**
     * @return void
     */
    public function test_cannot_move_category_into_its_own_descendant(): void {
        $this->resetAfterTest();
        $identity = $this->admin_identity();
        $parent = core_course_category::create(['name' => 'Parent']);
        $child = core_course_category::create(['name' => 'Child', 'parent' => $parent->id]);
        $this->expectException(api_exception::class);
        (new update_category())->preview(['categoryid' => $parent->id, 'parentid' => $child->id], $identity);
    }

    /**
     * @return void
     */
    public function test_create_course_accepts_html_summary_idnumber_and_base64_image(): void {
        $this->resetAfterTest();
        $identity = $this->admin_identity();
        $category = core_course_category::create(['name' => 'Courses']);
        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg==';
        $input = [
            'fullname' => 'Smart Cities',
            'shortname' => 'CITY-101',
            'idnumber' => 'CITTA-101',
            'categoryid' => $category->id,
            'summary' => '<p><strong>Course description</strong> in HTML.</p>',
            'summaryformat' => 1,
            'visible' => false,
            'image_base64' => $png,
        ];
        $tool = new create_course();
        $preview = $tool->preview($input, $identity);
        $this->assertSame('base64', $preview['image_source']);
        $this->assertArrayNotHasKey('image_base64', $preview);
        $created = $tool->execute($input, $identity);
        $this->assertSame('CITTA-101', $created['idnumber']);
        $this->assertSame(FORMAT_HTML, $created['summaryformat']);
        $this->assertStringContainsString('<strong>Course description</strong>', $created['summary']);
        $this->assertFalse($created['visible']);
        $this->assertNotEmpty($created['image']);
    }
}
