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
 * Create Lesson page MCP tool.
 *
 * @package   mcptool_lesson
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_lesson\write;

use context;
use context_module;
use local_mcp\security\authenticated_identity;
use mcptool_lesson\service;

/**
 * Create Lesson page through the native Moodle Lesson APIs.
 */
final class create_page extends \local_mcp\write\tool\base_tool {
    public function get_name(): string {
        return "lesson_create_page";
    }

    public function get_title(): string {
        return "Create Lesson page";
    }

    public function get_description(): string {
        return "Confirm Lesson page changes while preserving student attempts and navigation concurrency.";
    }

    public function get_required_capability(): string {
        return "mod/lesson:edit";
    }

    public function get_input_schema(): array {
        return $this->object_schema(["cmid" => ["type" => "integer", "minimum" => 1], "title" => ["type" => "string", "minLength" => 1, "maxLength" => 255], "content" => ["type" => "string", "maxLength" => 100000], "type" => ["type" => "string", "enum" => ["content", "multichoice"]], "choices" => ["type" => "array", "minItems" => 1, "maxItems" => 10, "items" => ["type" => "object", "properties" => ["text" => ["type" => "string", "minLength" => 1, "maxLength" => 255], "response" => ["type" => "string", "maxLength" => 10000], "jump" => ["anyOf" => [["type" => "string", "enum" => ["next", "previous", "end"]], ["type" => "integer", "minimum" => 1]]], "score" => ["type" => "integer", "minimum" => 0, "maximum" => 100]], "required" => ["text", "jump"], "additionalProperties" => false]], "expected_navigation_sha256" => ["type" => "string", "pattern" => "^[a-f0-9]{64}$"]], ["cmid", "title", "content", "type", "choices", "expected_navigation_sha256"]);
    }

    public function resolve_context(array $arguments): context {
        return context_module::instance((int)$arguments["cmid"], MUST_EXIST);
    }

    public function supports_dry_run(): bool {
        return true;
    }

    public function preview(array $arguments, authenticated_identity $identity): array {
        return service::create_preview($arguments, $identity);
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        return service::create($arguments, $identity);
    }
}
