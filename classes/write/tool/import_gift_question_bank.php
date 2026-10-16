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
 * Import a complete GIFT question bank through Moodle's native question APIs.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;

use context;
use local_mcp\question\gift_import_service;
use local_mcp\security\authenticated_identity;

/**
 * MCP WRITE: create a question category and import a complete GIFT bank.
 */
final class import_gift_question_bank extends base_tool {
    /**
     * @return string MCP tool identifier.
     */
    public function get_name(): string {
        return "import_gift_question_bank";
    }

    /**
     * @return string Localized tool name.
     */
    public function get_title(): string {
        return get_string("importgiftquestionbank", "local_mcp");
    }

    /**
     * @return string Localized tool description.
     */
    public function get_description(): string {
        return get_string("importgiftquestionbank_desc", "local_mcp");
    }

    /**
     * @return string Minimum capability for the selected question bank.
     */
    public function get_required_capability(): string {
        return "moodle/question:add";
    }

    /**
     * @return array Strict MCP JSON Schema.
     */
    public function get_input_schema(): array {
        return $this->object_schema([
            "contextid" => ["type" => "integer", "minimum" => 1,
                "description" => "Existing question bank context from list_question_banks."],
            "name" => ["type" => "string", "minLength" => 1,
                "description" => "New question category name."],
            "parentid" => ["type" => "integer", "minimum" => 0,
                "description" => "Parent category in the same context; 0 for root."],
            "info" => ["type" => "string", "description" => "Category description as HTML."],
            "idnumber" => ["type" => "string", "description" => "Unique category identifier in this context."],
            "gift" => ["type" => "string", "minLength" => 1, "maxLength" => 2097152,
                "description" => "Entire UTF-8 GIFT file as plain text, up to 2 MiB and 500 questions. "
                    . "Use blank lines between questions. Embedded category directives are not supported."],
        ], ["contextid", "name", "gift"]);
    }

    /**
     * @param array $arguments MCP arguments.
     * @return context Selected Moodle question-bank context.
     */
    public function resolve_context(array $arguments): context {
        return context::instance_by_id((int)$arguments["contextid"], MUST_EXIST);
    }

    /**
     * @return bool Allow an uncommitted validation-only preview.
     */
    public function supports_dry_run(): bool {
        return true;
    }

    /**
     * @param array $arguments MCP arguments.
     * @param authenticated_identity $identity Connected user.
     * @return array Content-free import preview.
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        return ["operation" => $this->get_name()] +
            gift_import_service::inspect($arguments, $identity);
    }

    /**
     * @param array $arguments MCP arguments.
     * @param authenticated_identity $identity Connected user.
     * @return array IDs created by Moodle's native importer.
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        return gift_import_service::execute($arguments, $identity);
    }
}
