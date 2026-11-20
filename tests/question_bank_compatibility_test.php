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
 * Verify bank and PHP compatibility across tested Moodle releases.
 *
 * @package local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_mcp;

use advanced_testcase;
use local_mcp\question\question_bank_factory;

final class question_bank_compatibility_test extends advanced_testcase {
    public function test_driver_matches_question_storage_version(): void {
        global $CFG;
        $this->resetAfterTest();
        $expected = (int)$CFG->version < 2022041900
            ? \local_mcp\question\legacy_question_bank::class
            : \local_mcp\question\versioned_question_bank::class;
        $this->assertSame($expected, question_bank_factory::driver());
    }

    public function test_php_compatibility_helpers(): void {
        $this->assertTrue(compat::starts_with('moodle', 'moo'));
        $this->assertTrue(compat::ends_with('moodle', 'dle'));
        $this->assertTrue(compat::contains('moodle', 'ood'));
        $this->assertTrue(compat::is_list([]));
        $this->assertTrue(compat::is_list([1, 2]));
        $this->assertFalse(compat::is_list(['a' => 1]));
        $this->assertFalse(compat::starts_with('moodle', 'odle'));
    }

    public function test_quiz_structure_uses_installed_quiz_api(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id, 'name' => 'MCP compatibility quiz',
        ]);
        $structure = \mcptool_quiz\quiz_compat::structure((int)$module->id);
        $this->assertInstanceOf(\mod_quiz\structure::class, $structure);
    }
}
