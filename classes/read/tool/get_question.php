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

namespace local_mcp\read\tool;

use context;
use local_mcp\question\bank_service;
use local_mcp\security\authenticated_identity;

/** MCP READ: Get question. */
final class get_question extends base_tool {
    public function get_name(): string { return 'get_question'; }
    public function get_title(): string { return 'Get question'; }
    public function get_description(): string { return 'Return full question authoring details, options and answers for a version.'; }
    public function get_required_capability(): string { return 'moodle/question:viewmine'; }
    public function get_input_schema(): array {
        return $this->object_schema(['questionid' => ['type' => 'integer', 'minimum' => 1]], ['questionid']);
    }
    public function resolve_context(array $a): context { return bank_service::question_context((int)$a['questionid']); }
    public function execute(array $a, authenticated_identity $identity): array { return bank_service::details((int)$a['questionid'], $identity); }
}
