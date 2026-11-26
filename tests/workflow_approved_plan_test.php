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
 * Tests for an explicitly approved sequence of native course operations.
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\exception\api_exception;
use local_mcp\protocol\mcp_server;
use local_mcp\security\authenticated_identity;
use local_mcp\workflow\sequence;
use ReflectionMethod;

/**
 * @covers \local_mcp\workflow\approved_plan
 * @covers \local_mcp\workflow\sequence
 * @covers \local_mcp\protocol\mcp_server
 */
final class workflow_approved_plan_test extends advanced_testcase {
    /** Invoke the protocol with the same JSON object types as a remote caller. */
    private function call(mcp_server $server, authenticated_identity $identity, string $tool, array $args,
            string $access = 'approved-plan-test'): array {
        $method = new ReflectionMethod(mcp_server::class, 'call_tool');
        $method->setAccessible(true);
        return $method->invoke($server, $tool, $args, [], $identity, $access, json_decode(json_encode($args)));
    }

    /** Assert a rejected operation without hiding an unexpected exception. */
    private function rejected(callable $call, string $code): void {
        try {
            $call();
            $this->fail('An unauthorized or stale operation was accepted.');
        } catch (api_exception $e) {
            $this->assertSame($code, $e->machinecode);
        }
    }

    /** One approval covers creation, description and movement into the future section. */
    public function test_approved_sequence_runs_exactly_one_native_write_per_call(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['format' => 'topics', 'numsections' => 1]);
        $courseid = (int)$course->id;
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $courseid, 'section' => 0, 'name' => 'Keep content', 'content' => '<p>Original content</p>',
        ]);
        $cmid = (int)$page->cmid;
        $sourceid = (int)$DB->get_field('course_modules', 'section', ['id' => $cmid], MUST_EXIST);
        $source = (int)$DB->get_field('course_sections', 'section', ['id' => $sourceid], MUST_EXIST);
        $target = 1 + (int)$DB->get_field_sql('SELECT MAX(section) FROM {course_sections} WHERE course = ?', [$courseid]);
        $count = $DB->count_records('course_sections', ['course' => $courseid]);
        $original = $DB->get_field('page', 'content', ['id' => $page->id], MUST_EXIST);
        $identity = new authenticated_identity((int)get_admin()->id, 'test', ['mcp:write']);
        $server = new mcp_server('write');
        $workflow = sequence::start($identity, $courseid, [
            ['title' => 'Create', 'tool' => 'create_section'],
            ['title' => 'Describe', 'tool' => 'update_section'],
            ['title' => 'Move', 'tool' => 'move_activity'],
        ]);
        $args = [
            'action' => 'authorize', 'courseid' => $courseid, 'workflow_id' => $workflow['id'],
            'expectedstep' => 1, 'expectedgeneration' => 0,
            'steps' => [
                ['tool' => 'create_section', 'arguments' => ['courseid' => $courseid, 'name' => 'Approved section']],
                ['tool' => 'update_section', 'arguments' => [
                    'courseid' => $courseid, 'sectionnum' => $target,
                    'summary' => '<p>Pedagogical description</p>', 'summaryformat' => 1,
                ]],
                ['tool' => 'move_activity', 'arguments' => [
                    'cmid' => $cmid, 'sectionnum' => $target, 'expectedsectionnum' => $source,
                ]],
            ],
        ];
        $pending = $this->call($server, $identity, 'workflow_sequence', $args);
        $this->assertTrue($pending['confirmation_required']);
        $this->assertSame($target, $pending['preview']['steps'][0]['preview']['sectionnum']);
        $this->assertSame($count, $DB->count_records('course_sections', ['course' => $courseid]));
        $run = [
            'action' => 'run_next', 'courseid' => $courseid, 'workflow_id' => $workflow['id'],
            'expectedstep' => 1, 'expectedgeneration' => 1,
        ];
        $this->rejected(fn() => $this->call($server, $identity, 'workflow_sequence', $run), 'workflow_step_mismatch');
        $tampered = $args + ['confirmation_token' => $pending['confirmation_token']];
        $tampered['steps'][0]['arguments']['name'] = 'Different section';
        $this->rejected(fn() => $this->call($server, $identity, 'workflow_sequence', $tampered), 'confirmation_expired');
        $approved = $this->call($server, $identity, 'workflow_sequence',
            $args + ['confirmation_token' => $pending['confirmation_token']]);
        $this->assertSame('authorized', $approved['workflow']['approval']['state']);
        $this->assertSame(1, $approved['workflow']['generation']);
        $this->assertSame($count, $DB->count_records('course_sections', ['course' => $courseid]));
        $this->rejected(fn() => $this->call($server, $identity, 'workflow_sequence', $run, 'different-access'),
            'workflow_step_mismatch');
        $this->rejected(fn() => $this->call($server, $identity, 'workflow_sequence',
            array_replace($run, ['expectedstep' => 2])), 'workflow_step_mismatch');
        $this->rejected(fn() => $this->call($server, $identity, 'create_section', [
            'courseid' => $courseid, 'name' => 'Unapproved', 'workflow_id' => $workflow['id'], 'workflow_step' => 1,
        ]), 'workflow_step_mismatch');

        $first = $this->call($server, $identity, 'workflow_sequence', $run);
        $this->assertTrue($first['verified']);
        $this->assertSame(2, $first['workflow']['step']);
        $this->assertCount(1, $first['workflow']['completed']);
        $this->assertSame($count + 1, $DB->count_records('course_sections', ['course' => $courseid]));
        $created = $DB->get_record('course_sections', ['course' => $courseid, 'section' => $target], '*', MUST_EXIST);
        $this->assertSame('Approved section', $created->name);
        $this->assertSame('', (string)$created->summary);
        $this->assertSame($sourceid, (int)$DB->get_field('course_modules', 'section', ['id' => $cmid]));
        $this->rejected(fn() => $this->call($server, $identity, 'workflow_sequence', $run), 'workflow_step_mismatch');

        $run['expectedstep'] = 2;
        $second = $this->call($server, $identity, 'workflow_sequence', $run);
        $this->assertTrue($second['verified']);
        $this->assertSame(3, $second['workflow']['step']);
        $this->assertSame('<p>Pedagogical description</p>',
            $DB->get_field('course_sections', 'summary', ['id' => $created->id]));
        $this->assertSame($sourceid, (int)$DB->get_field('course_modules', 'section', ['id' => $cmid]));

        $run['expectedstep'] = 3;
        $last = $this->call($server, $identity, 'workflow_sequence', $run);
        $this->assertTrue($last['verified']);
        $this->assertSame('done', $last['workflow']['state']);
        $this->assertSame('completed', $last['workflow']['approval']['state']);
        $this->assertCount(3, $last['workflow']['completed']);
        $this->assertSame((int)$created->id, (int)$DB->get_field('course_modules', 'section', ['id' => $cmid]));
        $this->assertSame((int)$page->id, (int)$DB->get_field('course_modules', 'instance', ['id' => $cmid]));
        $this->assertSame($original, $DB->get_field('page', 'content', ['id' => $page->id], MUST_EXIST));
        $this->rejected(fn() => $this->call($server, $identity, 'workflow_sequence', $run), 'workflow_step_mismatch');
    }

    /** An outside edit invalidates the approval and does not execute a later step. */
    public function test_course_drift_stops_before_next_write(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $courseid = (int)$course->id;
        $identity = new authenticated_identity((int)get_admin()->id, 'test', ['mcp:write']);
        $server = new mcp_server('write');
        $workflow = sequence::start($identity, $courseid, [
            ['title' => 'First', 'tool' => 'create_section'],
            ['title' => 'Second', 'tool' => 'create_section'],
        ]);
        $args = [
            'action' => 'authorize', 'courseid' => $courseid, 'workflow_id' => $workflow['id'],
            'expectedstep' => 1, 'expectedgeneration' => 0,
            'steps' => [
                ['tool' => 'create_section', 'arguments' => ['courseid' => $courseid, 'name' => 'First approved']],
                ['tool' => 'create_section', 'arguments' => ['courseid' => $courseid, 'name' => 'Second approved']],
            ],
        ];
        $preview = $this->call($server, $identity, 'workflow_sequence', $args);
        $this->call($server, $identity, 'workflow_sequence', $args + ['confirmation_token' => $preview['confirmation_token']]);
        $run = [
            'action' => 'run_next', 'courseid' => $courseid, 'workflow_id' => $workflow['id'],
            'expectedstep' => 1, 'expectedgeneration' => 1,
        ];
        $first = $this->call($server, $identity, 'workflow_sequence', $run);
        $DB->set_field('course_sections', 'summary', '<p>Concurrent edit</p>', ['id' => $first['result']['id']]);
        $run['expectedstep'] = 2;
        $this->rejected(fn() => $this->call($server, $identity, 'workflow_sequence', $run),
            'workflow_reconciliation_required');
        $this->assertFalse($DB->record_exists('course_sections', ['course' => $courseid, 'name' => 'Second approved']));
        $state = sequence::status($workflow['id'], $identity, $courseid);
        $this->assertSame('blocked', $state['approval']['state']);
        $this->assertCount(1, $state['completed']);
        $this->rejected(fn() => $this->call($server, $identity, 'workflow_sequence', $run), 'workflow_step_mismatch');
    }
}
