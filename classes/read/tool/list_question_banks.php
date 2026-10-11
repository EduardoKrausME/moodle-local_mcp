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

namespace local_mcp\read\tool;

use context;
use context_course;
use local_mcp\question\bank_service;
use local_mcp\security\authenticated_identity;

/**
 * MCP READ: List question banks.
 */
final class list_question_banks extends base_tool {
    /**
     * get_name
     *
     * @return string
     */
    public function get_name(): string {
        return 'list_question_banks';
    }

    /**
     * get_title
     *
     * @return string
     */
    public function get_title(): string {
        return 'List question banks';
    }

    /**
     * get_description
     *
     * @return string
     */
    public function get_description(): string {
        return 'Find the course question bank and quiz/shared bank module contexts.';
    }

    /**
     * get_required_capability
     *
     * @return string
     */
    public function get_required_capability(): string {
        return 'moodle/course:view';
    }

    /**
     * get_input_schema
     *
     * @return array
     */
    public function get_input_schema(): array {
        return $this->object_schema(['courseid' => ['type' => 'integer', 'minimum' => 1]], ['courseid']);
    }

    /**
     * resolve_context
     *
     * @param array $a
     * @return context
     */
    public function resolve_context(array $a): context {
        return context_course::instance((int)$a['courseid'], MUST_EXIST);
    }

    /**
     * execute
     *
     * @param array $a
     * @param authenticated_identity $identity
     * @return array
     */
    public function execute(array $a, authenticated_identity $identity): array {
        return bank_service::banks((int)$a['courseid'], $identity);
    }
}
