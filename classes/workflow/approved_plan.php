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
 * Explicitly approved sequential course organisation plans.
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\workflow;

use context_course;
use core\lock\lock_config;
use local_mcp\exception\api_exception;
use local_mcp\extension\manager as extension_manager;
use local_mcp\security\authenticated_identity;
use local_mcp\security\capability_guard;
use local_mcp\security\confirmation_service;
use local_mcp\security\secret;
use local_mcp\write\registry;
use local_mcp\write\tool\create_section;
use local_mcp\write\tool\move_activity;
use local_mcp\write\tool\update_section;

/**
 * Authorize immutable arguments for supported operations, then dispatch one at a time.
 * The ordinary MCP WRITE dispatcher remains responsible for every native operation.
 */
final class approved_plan {
    /** Scope is deliberately limited to non-destructive course organisation. */
    private const TOOLS = [
        'create_section' => create_section::class,
        'update_section' => update_section::class,
        'move_activity' => move_activity::class,
    ];

    /** Input schema shared by the workflow controller. */
    public static function steps_schema(): array {
        return [
            'type' => 'array', 'minItems' => 1, 'maxItems' => sequence::MAX_ITEMS,
            'items' => [
                'type' => 'object', 'additionalProperties' => false,
                'properties' => [
                    'tool' => ['type' => 'string', 'enum' => array_keys(self::TOOLS)],
                    'arguments' => [
                        'type' => 'object', 'additionalProperties' => false,
                        'properties' => [
                            'courseid' => ['type' => 'integer', 'minimum' => 1],
                            'sectionnum' => ['type' => 'integer', 'minimum' => 0],
                            'expectedsectionnum' => ['type' => 'integer', 'minimum' => 0],
                            'cmid' => ['type' => 'integer', 'minimum' => 1],
                            'name' => ['type' => 'string', 'minLength' => 1],
                            'summary' => ['type' => 'string'],
                            'summaryformat' => ['type' => 'integer', 'enum' => [1]],
                        ],
                    ],
                ],
                'required' => ['tool', 'arguments'],
            ],
        ];
    }

    /**
     * Read structure directly, including hidden sections and activities.
     * Do not read student records, activity contents, or grade data.
     */
    private static function snapshot(int $courseid): array {
        global $DB;
        $sections = [];
        $numbers = [];
        foreach ($DB->get_records('course_sections', ['course' => $courseid], 'section ASC') as $section) {
            $number = (int)$section->section;
            $numbers[(int)$section->id] = $number;
            $sections[$number] = [
                'id' => (int)$section->id,
                'name' => $section->name,
                'summary' => (string)$section->summary,
                'summaryformat' => (int)$section->summaryformat,
                'visible' => (bool)$section->visible,
                'availability' => (string)($section->availability ?? ''),
                'sequence' => array_map('intval', array_filter(explode(',', (string)$section->sequence), 'strlen')),
            ];
        }
        $modules = [];
        foreach ($DB->get_records('course_modules', ['course' => $courseid], 'id ASC') as $cm) {
            if (!isset($numbers[(int)$cm->section])) {
                self::changed();
            }
            $modules[(int)$cm->id] = [
                'sectionnum' => $numbers[(int)$cm->section],
                'module' => (int)$cm->module,
                'instance' => (int)$cm->instance,
                'visible' => (bool)$cm->visible,
                'visibleoncoursepage' => (bool)($cm->visibleoncoursepage ?? true),
                'availability' => (string)($cm->availability ?? ''),
                'completion' => (int)($cm->completion ?? 0),
                'completionview' => (int)($cm->completionview ?? 0),
                'completionexpected' => (int)($cm->completionexpected ?? 0),
                'groupmode' => (int)($cm->groupmode ?? 0),
                'groupingid' => (int)($cm->groupingid ?? 0),
                'deletioninprogress' => (bool)($cm->deletioninprogress ?? false),
            ];
        }
        return ['sections' => $sections, 'modules' => $modules];
    }

    /** Fail closed when the expected course structure is no longer present. */
    private static function changed(): never {
        throw new api_exception('workflow_reconciliation_required', 409, '', [],
            'The course structure differs from the approved sequence. Stop and reconcile before continuing.');
    }

    /** Reject unsupported or ambiguous instructions before authorization. */
    private static function invalid(): never {
        throw new api_exception('invalid_workflow_items', 400, '', [],
            'The approved plan must contain exact supported arguments for every remaining step.');
    }

    /** Normalize an exact supported tool call without accepting additional parameters. */
    private static function validate(array $entry, int $courseid, authenticated_identity $identity): array {
        $name = $entry['tool'] ?? '';
        $args = $entry['arguments'] ?? null;
        if (!isset(self::TOOLS[$name]) || !is_array($args)) {
            self::invalid();
        }
        if ($name === 'create_section') {
            $allowed = ['courseid', 'name'];
            $required = $allowed;
        } else if ($name === 'update_section') {
            $allowed = ['courseid', 'sectionnum', 'name', 'summary', 'summaryformat'];
            $required = ['courseid', 'sectionnum'];
            if (!array_key_exists('name', $args) && !array_key_exists('summary', $args)) {
                self::invalid();
            }
            if (isset($args['summaryformat']) &&
                    (!array_key_exists('summary', $args) || $args['summaryformat'] !== (int)FORMAT_HTML)) {
                self::invalid();
            }
        } else {
            // Appending to a named section number is deterministic, including a newly created section.
            // Anchor moves, visibility changes and destructive tools retain the ordinary approval flow.
            $allowed = ['cmid', 'sectionnum', 'expectedsectionnum'];
            $required = $allowed;
        }
        if (array_diff(array_keys($args), $allowed) || array_diff($required, array_keys($args))) {
            self::invalid();
        }
        if ($name !== 'move_activity' && ($args['courseid'] ?? null) !== $courseid) {
            self::invalid();
        }
        foreach (['courseid', 'cmid', 'sectionnum', 'expectedsectionnum'] as $key) {
            if (isset($args[$key]) && (!is_int($args[$key]) ||
                    $args[$key] < (in_array($key, ['cmid', 'courseid'], true) ? 1 : 0))) {
                self::invalid();
            }
        }
        if (isset($args['name']) && (!is_string($args['name']) || trim($args['name']) === '' ||
                clean_param($args['name'], PARAM_TEXT) !== $args['name'])) {
            self::invalid();
        }
        if (isset($args['summary']) && (!is_string($args['summary']) ||
                clean_param($args['summary'], PARAM_CLEANHTML) !== $args['summary'])) {
            self::invalid();
        }
        $tool = registry::get_tools()[$name] ?? null;
        if (!$tool || get_class($tool) !== self::TOOLS[$name] || $tool->is_destructive()) {
            self::invalid();
        }
        $context = $tool->resolve_context($args);
        $coursecontext = $context->get_course_context(false);
        if (!$coursecontext || (int)$coursecontext->instanceid !== $courseid) {
            throw new api_exception('workflow_context_mismatch', 409);
        }
        capability_guard::check($tool->get_required_capability(), $context, $identity->userid);
        if ($name === 'move_activity') {
            capability_guard::check('moodle/course:manageactivities',
                context_course::instance($courseid), $identity->userid);
        }
        foreach (extension_manager::access_policies() as $policy) {
            if (!$policy->tool_visible($identity->userid, $name, true)) {
                throw new api_exception('tool_not_found', 404);
            }
            $policy->authorize_tool($identity->userid, $name, true, $context, $args);
        }
        return ['tool' => $name, 'arguments' => $args];
    }

    /**
     * Simulate one operation without creating sections or touching module records.
     * Negative section IDs are placeholders until a creation is verified.
     */
    private static function simulate(array &$state, array $entry): array {
        $args = $entry['arguments'];
        $name = $entry['tool'];
        if ($name === 'create_section') {
            $number = max(array_keys($state['sections'])) + 1;
            $state['sections'][$number] = [
                'id' => -$number - 1,
                'name' => $args['name'],
                'summary' => '',
                'summaryformat' => (int)FORMAT_HTML,
                'visible' => true,
                'availability' => '',
                'sequence' => [],
            ];
            return ['sectionnum' => $number, 'name' => $args['name'], 'operation' => 'create'];
        }
        $number = $args['sectionnum'];
        if (!isset($state['sections'][$number])) {
            self::invalid();
        }
        if ($name === 'update_section') {
            $before = $state['sections'][$number];
            if (isset($args['name'])) {
                $state['sections'][$number]['name'] = $args['name'];
            }
            if (array_key_exists('summary', $args)) {
                $state['sections'][$number]['summary'] = $args['summary'];
                $state['sections'][$number]['summaryformat'] = (int)FORMAT_HTML;
            }
            $after = $state['sections'][$number];
            return [
                'sectionnum' => $number,
                'previous' => ['name' => $before['name'], 'summary' => $before['summary']],
                'changes' => ['name' => $after['name'], 'summary' => $after['summary']],
            ];
        }
        $cmid = $args['cmid'];
        $source = $args['expectedsectionnum'];
        $cm = $state['modules'][$cmid] ?? null;
        if (!$cm || $cm['deletioninprogress'] || $cm['sectionnum'] !== $source ||
                !isset($state['sections'][$source]) ||
                !in_array($cmid, $state['sections'][$source]['sequence'], true)) {
            self::invalid();
        }
        foreach ([$source, $number] as $sectionnum) {
            $section = $state['sections'][$sectionnum];
            // Do not implicitly change access by moving into or out of restricted/hidden sections.
            if (!$section['visible'] || $section['availability'] !== '') {
                throw new api_exception('workflow_reconciliation_required', 409, '', [],
                    'Moves involving hidden or restricted sections require an individual review.');
            }
        }
        if ($source !== $number && in_array($cmid, $state['sections'][$number]['sequence'], true)) {
            self::changed();
        }
        $state['sections'][$source]['sequence'] =
            array_values(array_diff($state['sections'][$source]['sequence'], [$cmid]));
        $state['sections'][$number]['sequence'][] = $cmid;
        $state['modules'][$cmid]['sectionnum'] = $number;
        return [
            'cmid' => $cmid,
            'from' => ['sectionnum' => $source, 'name' => $state['sections'][$source]['name']],
            'to' => ['sectionnum' => $number, 'name' => $state['sections'][$number]['name']],
            'destination_order' => $state['sections'][$number]['sequence'],
        ];
    }

    /** Build a complete non-mutating preview of the remaining ordered steps. */
    public static function preview(array $arguments, authenticated_identity $identity): array {
        $id = $arguments['workflow_id'] ?? '';
        $courseid = (int)$arguments['courseid'];
        $state = sequence::plan_state($id, $identity, $courseid);
        $step = (int)$state['index'] + 1;
        $generation = (int)($state['generation'] ?? 0);
        if ($state['state'] !== 'ready' || ($arguments['expectedstep'] ?? null) !== $step ||
                ($arguments['expectedgeneration'] ?? null) !== $generation) {
            throw new api_exception('workflow_step_mismatch', 409);
        }
        $entries = $arguments['steps'] ?? [];
        if (count($entries) !== count($state['items']) - $state['index']) {
            self::invalid();
        }
        $initial = self::snapshot($courseid);
        $virtual = $initial;
        $steps = [];
        foreach (array_values($entries) as $offset => $entry) {
            $item = $state['items'][$state['index'] + $offset];
            if (($entry['tool'] ?? null) !== $item['tool']) {
                self::invalid();
            }
            $entry = self::validate($entry, $courseid, $identity);
            $preview = self::simulate($virtual, $entry);
            $steps[] = ['step' => $step + $offset, 'title' => $item['title']] + $entry + ['preview' => $preview];
        }
        $baseline = confirmation_service::args_hash($initial);
        $plan = [
            'workflow_id' => $id,
            'courseid' => $courseid,
            'firststep' => $step,
            'generation' => $generation,
            'initial_sha256' => $baseline,
            'steps' => $steps,
        ];
        $plan['plan_sha256'] = confirmation_service::args_hash($plan);
        return $plan;
    }

    /** Persist approval only after the protocol has consumed its bound confirmation. */
    public static function authorize(array $arguments, authenticated_identity $identity, string $rawaccess,
            string $expectedhash): array {
        $plan = self::preview($arguments, $identity);
        if (!hash_equals($expectedhash, $plan['plan_sha256'])) {
            self::changed();
        }
        return sequence::authorize_plan($plan, $identity, secret::hash($rawaccess));
    }

    /**
     * Execute exactly one approved operation. Every call rechecks course state and authorization.
     * The callback must be the ordinary protocol dispatcher, not a raw database write.
     */
    public static function run_next(array $arguments, authenticated_identity $identity, string $rawaccess,
            callable $dispatch): array {
        $id = $arguments['workflow_id'] ?? '';
        $courseid = (int)$arguments['courseid'];
        $step = $arguments['expectedstep'] ?? 0;
        $generation = $arguments['expectedgeneration'] ?? -1;
        $accesshash = secret::hash($rawaccess);
        // Serialize a complete stage, including its post-write verification.
        $lock = lock_config::get_lock_factory('local_mcp')->get_lock('workflow_plan_run_' . $id, 5);
        if (!$lock) {
            throw new api_exception('workflow_busy', 409);
        }
        try {
            $approved = sequence::approved_current($id, $identity, $courseid, $step, $generation, $accesshash);
            try {
                $before = self::snapshot($courseid);
                if (!hash_equals($approved['baseline'], confirmation_service::args_hash($before))) {
                    self::changed();
                }
                $entry = self::validate($approved['entry'], $courseid, $identity);
                $expected = $before;
                $preview = self::simulate($expected, $entry);
                $result = $dispatch($entry['tool'], $entry['arguments'] + [
                    'workflow_id' => $id, 'workflow_step' => $step,
                ]);
                $actual = self::snapshot($courseid);
                if ($entry['tool'] === 'create_section') {
                    $number = $preview['sectionnum'];
                    $created = $actual['sections'][$number] ?? null;
                    if (!$created || (int)($result['section'] ?? -1) !== $number ||
                            (int)($result['id'] ?? 0) !== $created['id'] || $created['id'] <= 0) {
                        self::changed();
                    }
                    $expected['sections'][$number]['id'] = $created['id'];
                    // The native default format is harmless for an empty description.
                    $expected['sections'][$number]['summaryformat'] = $created['summaryformat'];
                }
                $afterhash = confirmation_service::args_hash($actual);
                if (!hash_equals(confirmation_service::args_hash($expected), $afterhash)) {
                    self::changed();
                }
                $workflow = sequence::advance_approved($id, $identity, $courseid, $step, $generation, $afterhash);
                unset($result['workflow']);
                return ['workflow' => $workflow, 'result' => $result, 'verified' => true];
            } catch (\Throwable $failure) {
                // A failure may follow a native commit. Do not retry or advance automatically.
                try {
                    sequence::block_approved($id, $identity, $courseid, $step, $generation);
                } catch (\Throwable $ignored) {
                    // Preserve the original diagnostic; an uncertain stage must be reconciled.
                }
                throw $failure;
            }
        } finally {
            $lock->release();
        }
    }
}
