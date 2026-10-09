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

namespace local_mcp\write\tool;

use context;
use local_mcp\question\bank_service;
use local_mcp\security\authenticated_identity;

/** MCP WRITE: Update question version. */
final class update_question extends base_tool {
    public function get_name(): string { return 'update_question'; }
    public function get_title(): string { return 'Update question version'; }
    public function get_description(): string { return 'Create a new version from the current question ID; expectedversion prevents overwriting newer edits. Requires complete new question content.'; }
    public function get_required_capability(): string { return 'moodle/question:editmine'; }
    public function get_input_schema(): array {
        return $this->object_schema([
 'questionid' => ['type' => 'integer', 'minimum' => 1],
 'expectedversion' => ['type' => 'integer', 'minimum' => 1],
 'name' => ['type' => 'string', 'minLength' => 1],
            'questiontext' => ['type' => 'string', 'minLength' => 1, 'description' => 'HTML question text.'],
            'generalfeedback' => ['type' => 'string'],
            'defaultmark' => ['type' => 'number', 'exclusiveMinimum' => 0],
            'status' => ['type' => 'string', 'enum' => ['ready', 'draft']],
            'idnumber' => ['type' => 'string'],
            'single' => ['type' => 'boolean', 'description' => 'Multiple choice: exactly one correct answer.'],
            'shuffleanswers' => ['type' => 'boolean'],
            'usecase' => ['type' => 'boolean'],
            'correctanswer' => ['type' => 'boolean', 'description' => 'True/false correct response.'],
            'answers' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 50,
                'items' => ['type' => 'object', 'properties' => [
                    'text' => ['type' => 'string'], 'fraction' => ['type' => 'number'],
                    'feedback' => ['type' => 'string']],
                    'required' => ['text', 'fraction'], 'additionalProperties' => false]],

 ], ['questionid', 'expectedversion', 'name', 'questiontext']);
    }
    public function resolve_context(array $a): context { return bank_service::question_context((int)$a['questionid']); }
    public function supports_dry_run(): bool { return true; }
    public function preview(array $a, authenticated_identity $identity): array { $old = bank_service::row((int)$a['questionid']); bank_service::authorize($old, $identity, true); bank_service::form($a, $old->qtype); return ['operation' => 'update_question', 'questionid' => (int)$old->id, 'bankentryid' => (int)$old->questionbankentryid, 'expectedversion' => (int)$a['expectedversion'], 'qtype' => $old->qtype]; }
    public function execute(array $a, authenticated_identity $identity): array { return bank_service::save($a, $identity, (int)$a['questionid']); }
}
