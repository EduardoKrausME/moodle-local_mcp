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
 * Regression coverage for assignment creation and safe module deletion.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\write\registry;

/**
 * course module maintenance tests.
 *
 * @covers \local_mcp\write\registry
 * @covers \local_mcp\write\tool\assign_create_activity
 * @covers \local_mcp\write\tool\delete_course_module
 */
final class course_module_maintenance_test extends advanced_testcase {
    public function test_tools_are_registered_with_safe_schemas(): void {
        $tools = registry::get_tools();
        $this->assertArrayHasKey('assign_create_activity', $tools);
        $this->assertArrayHasKey('delete_course_module', $tools);
        $this->assertTrue($tools['assign_create_activity']->requires_confirmation());
        $this->assertTrue($tools['delete_course_module']->requires_confirmation());
        $this->assertTrue($tools['delete_course_module']->is_destructive());
        $schema = $tools['delete_course_module']->get_input_schema();
        foreach (['courseid', 'cmid', 'expectedsectionnum', 'expectedmodname', 'expectedname'] as $field) {
            $this->assertContains($field, $schema['required']);
        }
        $assignschema = $tools['assign_create_activity']->get_input_schema();
        $this->assertArrayHasKey('file_enabled', $assignschema['properties']);
        $this->assertArrayHasKey('onlinetext_enabled', $assignschema['properties']);
    }

    public function test_deletion_refuses_mismatched_name_or_course(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page',
            ['course' => $course->id, 'name' => 'Keep this original']);
        $identity = new authenticated_identity($USER->id, 'test', ['mcp:write']);
        $tool = registry::get_tools()['delete_course_module'];
        $args = [
            'courseid' => $course->id,
            'cmid' => $page->cmid,
            'expectedsectionnum' => 0,
            'expectedmodname' => 'page',
            'expectedname' => 'Wrong name',
        ];
        $this->expectException(api_exception::class);
        $tool->preview($args, $identity);
    }
}
