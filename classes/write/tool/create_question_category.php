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
 * Question bank MCP integration.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;

use coding_exception;
use context;
use local_mcp\exception\api_exception;
use local_mcp\question\bank_service;
use local_mcp\security\authenticated_identity;

/**
 * MCP WRITE: Create question category.
 */
final class create_question_category extends base_tool {
    /**
     * get_name
     *
     * @return string
     */
    public function get_name(): string {
        return 'create_question_category';
    }

    /**
     * get_title
     *
     * @return string
     */
    public function get_title(): string {
        return 'Create question category';
    }

    /**
     * get_description
     *
     * @return string
     */
    public function get_description(): string {
        return 'Create a category in a selected bank context using the Moodle core category manager.';
    }

    /**
     * get_required_capability
     *
     * @return string
     */
    public function get_required_capability(): string {
        return 'moodle/question:managecategory';
    }

    /**
     * get_input_schema
     *
     * @return array
     */
    public function get_input_schema(): array {
        return $this->object_schema([
            'contextid' => ['type' => 'integer', 'minimum' => 1],
            'name' => ['type' => 'string', 'minLength' => 1],
            'parentid' => ['type' => 'integer', 'minimum' => 0],
            'info' => ['type' => 'string'], 'idnumber' => ['type' => 'string'],
        ], ['contextid', 'name']);
    }

    /**
     * resolve_context
     *
     * @param array $a
     * @return context
     * @throws coding_exception
     */
    public function resolve_context(array $a): context {
        return context::instance_by_id((int)$a['contextid'], MUST_EXIST);
    }

    /**
     * supports_dry_run
     *
     * @return bool
     */
    public function supports_dry_run(): bool {
        return true;
    }

    /**
     * preview
     *
     * @param array $a
     * @param authenticated_identity $identity
     * @return array
     * @throws api_exception
     */
    public function preview(array $a, authenticated_identity $identity): array {
        [$contextid, $name, $parentid] = bank_service::validate_category($a, $identity);
        return ['operation' => 'create_question_category', 'contextid' => $contextid, 'name' => $name, 'parentid' => $parentid];
    }

    /**
     * execute
     *
     * @param array $a
     * @param authenticated_identity $identity
     * @return array
     */
    public function execute(array $a, authenticated_identity $identity): array {
        return bank_service::create_category($a, $identity);
    }
}
