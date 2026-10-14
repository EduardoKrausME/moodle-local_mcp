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
 * Course read and update tools.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use context_system;
use core\exception\moodle_exception;
use core_course_category;
use dml_exception;
use local_mcp\read\tool\get_course;
use local_mcp\read\tool\search_courses;
use local_mcp\security\authenticated_identity;
use local_mcp\write\tool\update_course;

/**
 * Validate safe administration queries and course metadata changes.
 *
 * @covers \local_mcp\read\tool\get_course
 * @covers \local_mcp\read\tool\search_courses
 * @covers \local_mcp\write\tool\update_course
 */
final class course_read_test extends advanced_testcase {
    /**
     * admin_identity
     *
     * @return authenticated_identity
     */
    private function admin_identity(): authenticated_identity {
        $admin = get_admin();
        $this->setUser($admin);
        return new authenticated_identity((int)$admin->id, 'test', ['mcp:read', 'mcp:write']);
    }

    /**
     * The front page is a Moodle course, but must never be modified.
     *
     * @return void
     */
    public function test_get_site_course_returns_complete_metadata(): void {
        $this->resetAfterTest();
        $identity = $this->admin_identity();
        $tool = new get_course();
        $this->assertEquals(context_system::instance()->id,
            $tool->resolve_context(['courseid' => SITEID])->id);
        $result = $tool->execute(['courseid' => SITEID], $identity);
        $this->assertSame((int)SITEID, $result['id']);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('summaryformat', $result);
        $this->assertArrayHasKey('categoryid', $result);
        $this->assertArrayHasKey('visible', $result);
    }

    /**
     * test_search_hidden_courses_and_exact_shortname
     *
     * @return void
     * @throws moodle_exception
     */
    public function test_search_hidden_courses_and_exact_shortname(): void {
        $this->resetAfterTest();
        $identity = $this->admin_identity();
        $category = core_course_category::create(['name' => 'CITTA']);
        $hidden = $this->getDataGenerator()->create_course([
            'category' => $category->id, 'shortname' => 'CITTA-EXACT',
            'fullname' => 'Hidden course', 'summary' => '<p>HTML description</p>',
            'summaryformat' => FORMAT_HTML, 'visible' => 0,
        ]);
        $this->getDataGenerator()->create_course([
            'category' => $category->id, 'shortname' => 'CITTA-EXACT-OTHER',
            'fullname' => 'Visible course', 'visible' => 1,
        ]);

        $tool = new search_courses();
        $result = $tool->execute(['shortname' => 'CITTA-EXACT',
            'includehidden' => true], $identity);
        $this->assertCount(1, $result['courses']);
        $found = $result['courses'][0];
        $this->assertSame((int)$hidden->id, $found['id']);
        $this->assertSame((int)$category->id, $found['categoryid']);
        $this->assertSame('<p>HTML description</p>', $found['summary']);
        $this->assertSame((int)FORMAT_HTML, $found['summaryformat']);
        $this->assertFalse($found['visible']);

        $withouthidden = $tool->execute(['shortname' => 'CITTA-EXACT',
            'includehidden' => false], $identity);
        $this->assertCount(0, $withouthidden['courses']);

        $siteexcluded = $tool->execute(['query' => '',
            'includehidden' => true], $identity);
        foreach ($siteexcluded['courses'] as $course) {
            $this->assertNotEquals(SITEID, $course['id']);
        }
    }

    /**
     * test_update_html_summary_and_move_course_to_category
     *
     * @return void
     * @throws moodle_exception
     * @throws dml_exception
     * @throws exception\api_exception
     */
    public function test_update_html_summary_and_move_course_to_category(): void {
        global $DB;

        $this->resetAfterTest();
        $identity = $this->admin_identity();
        $source = core_course_category::create(['name' => 'Source']);
        $destination = core_course_category::create(['name' => 'Destination']);
        $course = $this->getDataGenerator()->create_course([
            'category' => $source->id,
            'shortname' => 'MCP-MOVE',
        ]);
        $result = (new update_course())->execute([
            'courseid' => (int)$course->id,
            'summary' => '<p><strong>Updated HTML</strong></p>',
            'summaryformat' => 1,
            'categoryid' => (int)$destination->id,
        ], $identity);
        $this->assertSame((int)$destination->id, $result['categoryid']);
        $this->assertSame((int)FORMAT_HTML, $result['summaryformat']);
        $this->assertStringContainsString('<strong>Updated HTML</strong>', $result['summary']);
        $this->assertSame((int)$destination->id,
            (int)$DB->get_field('course', 'category', ['id' => $course->id]));
    }

    /**
     * Verify the site course cannot be edited through the course update tool.
     *
     * @return void
     */
    public function test_update_course_rejects_site_course(): void {
        $this->resetAfterTest();
        $identity = $this->admin_identity();
        $this->expectException(exception\api_exception::class);
        (new update_course())->execute([
            'courseid' => (int)SITEID,
            'fullname' => 'Must not change the site course',
        ], $identity);
    }
}
