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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Question bank MCP integration.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use context_course;
use local_mcp\question\bank_service;
use local_mcp\read\registry as read_registry;
use local_mcp\security\authenticated_identity;
use local_mcp\write\registry as write_registry;

defined('MOODLE_INTERNAL') || die;

/** Question bank READ/WRITE integration tests. */
final class question_bank_test extends advanced_testcase {
    /** @return void */
    public function test_question_bank_tools_are_registered(): void {
        $r = read_registry::get_tools();
        $w = write_registry::get_tools();
        foreach (['list_question_banks', 'list_question_categories', 'search_questions', 'get_question'] as $name) {
            $this->assertArrayHasKey($name, $r);
            $this->assertArrayNotHasKey($name, $w);
        }
        foreach (['create_question_category', 'create_question', 'update_question'] as $name) {
            $this->assertArrayHasKey($name, $w);
            $this->assertArrayNotHasKey($name, $r);
            $this->assertTrue($w[$name]->requires_confirmation());
        }
    }

    /** @return void */
    public function test_category_creation_and_single_choice_round_trip(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $ctx = context_course::instance($course->id);
        $identity = new authenticated_identity(get_admin()->id, 'test', ['mcp:read', 'mcp:write']);
        $category = bank_service::create_category([
            'contextid' => $ctx->id, 'parentid' => 0, 'name' => 'MCP tests',
        ], $identity);
        $list = bank_service::categories($ctx->id);
        $this->assertContains($category['id'], array_column($list['categories'], 'id'));
        $data = ['categoryid' => $category['id'], 'qtype' => 'multichoice',
            'name' => 'Example question', 'questiontext' => '<p>Question?</p>',
            'answers' => [
                ['text' => 'Correct', 'fraction' => 1],
                ['text' => 'Incorrect', 'fraction' => 0],
            ]];
        $created = bank_service::save($data, $identity);
        $details = bank_service::details($created['questionid'], $identity);
        $this->assertSame('Example question', $details['name']);
        $this->assertCount(2, $details['answers']);
        $next = bank_service::save(array_merge($data, [
            'expectedversion' => $created['version'],
            'name' => 'Revised question',
        ]), $identity, $created['questionid']);
        $this->assertSame($created['bankentryid'], $next['bankentryid']);
        $this->assertSame($created['version'] + 1, $next['version']);
        $this->assertSame('Example question',
            bank_service::details($created['questionid'], $identity)['name']);
    }

    /** @return void */
    public function test_invalid_multichoice_payload_is_rejected(): void {
        $this->expectException(\local_mcp\exception\api_exception::class);
        bank_service::form(['name' => 'Q', 'questiontext' => 'Text',
            'answers' => [
                ['text' => 'Wrong', 'fraction' => 0],
                ['text' => 'Also wrong', 'fraction' => 0],
            ]], 'multichoice');
    }
}
