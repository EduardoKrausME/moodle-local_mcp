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
 * update_section.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;

use context;
use context_course;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\security\capability_guard;
use stdClass;

/**
 * Update existing course section metadata without replacing its activities.
 */
final class update_section extends base_tool {
    /** @return string Tool name. */
    public function get_name(): string {
        return 'update_section';
    }

    /** @return string Tool title. */
    public function get_title(): string {
        return 'Update section';
    }

    /** @return string Tool description. */
    public function get_description(): string {
        return 'Update course section name, HTML description or visibility. Use the current section_sha256 for concurrency.';
    }

    /** @return string Required capability. */
    public function get_required_capability(): string {
        return 'moodle/course:update';
    }

    /** @return array Input schema. */
    public function get_input_schema(): array {
        return $this->object_schema([
            'courseid' => ['type' => 'integer', 'minimum' => 1],
            'sectionnum' => ['type' => 'integer', 'minimum' => 0],
            'name' => ['type' => 'string'],
            'summary' => ['type' => 'string', 'description' => 'HTML description; empty string clears it.'],
            'summaryformat' => ['type' => 'integer', 'enum' => [1], 'description' => '1 = HTML.'],
            'visible' => ['type' => 'boolean'],
            'expected_section_sha256' => ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$'],
        ], ['courseid', 'sectionnum']);
    }

    /**
     * @param array $arguments Tool input.
     * @return context Course context.
     */
    public function resolve_context(array $arguments): context {
        return context_course::instance((int)$arguments['courseid'], MUST_EXIST);
    }

    /** @return bool Supports dry run. */
    public function supports_dry_run(): bool {
        return true;
    }

    /**
     * Return a fingerprint of the editable section and its identity.
     *
     * @param stdClass $section Section record.
     * @return string Current fingerprint.
     */
    public static function fingerprint(stdClass $section): string {
        return hash('sha256', serialize([
            (int)$section->id, (int)$section->course, (int)$section->section,
            $section->name, (string)$section->summary, (int)$section->summaryformat, (bool)$section->visible,
        ]));
    }

    /**
     * Validate a metadata update and prepare exactly the fields to change.
     *
     * @param array $arguments Tool input.
     * @param authenticated_identity $identity Connected identity.
     * @return array Section and proposed fields.
     */
    private function plan(array $arguments, authenticated_identity $identity): array {
        global $DB;
        $context = $this->resolve_context($arguments);
        capability_guard::check($this->get_required_capability(), $context, $identity->userid);
        $section = $DB->get_record('course_sections', [
            'course' => (int)$arguments['courseid'], 'section' => (int)$arguments['sectionnum'],
        ]);
        if (!$section) {
            throw new api_exception('section_not_found', 404);
        }
        $hash = self::fingerprint($section);
        if (isset($arguments['expected_section_sha256']) &&
                !hash_equals($hash, $arguments['expected_section_sha256'])) {
            throw new api_exception('course_section_changed', 409);
        }
        $data = new stdClass();
        if (isset($arguments['name'])) {
            $data->name = clean_param($arguments['name'], PARAM_TEXT);
        }
        if (isset($arguments['summaryformat']) &&
                ((int)$arguments['summaryformat'] !== FORMAT_HTML || !array_key_exists('summary', $arguments))) {
            throw new api_exception('invalid_summaryformat', 400, 'Supply summary with summaryformat=1 (HTML).');
        }
        if (array_key_exists('summary', $arguments)) {
            $data->summary = clean_param((string)$arguments['summary'], PARAM_CLEANHTML);
            $data->summaryformat = FORMAT_HTML;
        }
        if (array_key_exists('visible', $arguments)) {
            capability_guard::check('moodle/course:sectionvisibility', $context, $identity->userid);
            $data->visible = (int)(bool)$arguments['visible'];
        }
        return [$section, $data, $hash];
    }

    /**
     * @param array $arguments Tool input.
     * @param authenticated_identity $identity Connected identity.
     * @return array Non-mutating preview.
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        [$section, $data, $hash] = $this->plan($arguments, $identity);
        return [
            'courseid' => (int)$section->course, 'sectionnum' => (int)$section->section,
            'sectionid' => (int)$section->id, 'section_sha256' => $hash,
            'previous' => ['name' => $section->name, 'summary' => $section->summary,
                'summaryformat' => (int)$section->summaryformat, 'visible' => (bool)$section->visible],
            'changes' => (array)$data,
        ];
    }

    /**
     * Apply metadata through the native API using the existing section record.
     *
     * @param array $arguments Tool input.
     * @param authenticated_identity $identity Connected identity.
     * @return array Verified metadata.
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        $lock = \core\lock\lock_config::get_lock_factory('local_mcp')->get_lock(
            'section_metadata_' . (int)$arguments['courseid'], 5);
        if (!$lock) {
            throw new api_exception('course_section_busy', 409);
        }
        try {
            [$section, $data] = $this->plan($arguments, $identity);
            $transaction = $DB->start_delegated_transaction();
            try {
                if ((array)$data) {
                    course_update_section((int)$section->course, $section, $data);
                }
                $current = $DB->get_record('course_sections', ['id' => $section->id], '*', MUST_EXIST);
                $transaction->allow_commit();
            } catch (\Throwable $e) {
                $transaction->rollback($e);
            }
            return [
                'updated' => true, 'courseid' => (int)$current->course, 'sectionnum' => (int)$current->section,
                'sectionid' => (int)$current->id, 'name' => $current->name, 'summary' => $current->summary,
                'summaryformat' => (int)$current->summaryformat, 'visible' => (bool)$current->visible,
                'section_sha256' => self::fingerprint($current),
            ];
        } finally {
            $lock->release();
        }
    }
}
