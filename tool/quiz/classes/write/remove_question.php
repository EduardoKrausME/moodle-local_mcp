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
 * Remove quiz question slot MCP tool.
 *
 * @package   mcptool_quiz
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_quiz\write;

use context;
use context_module;
use local_mcp\security\authenticated_identity;
use mcptool_quiz\quiz_service;

/**
 * Remove a quiz question slot before any real attempts exist.
 */
final class remove_question extends \local_mcp\write\tool\base_tool {
    public function get_name(): string {
        return "quiz_remove_question";
    }

    public function get_title(): string {
        return "Remove quiz question slot";
    }

    public function get_description(): string {
        return "Remove a quiz question slot before any real attempts exist.";
    }

    public function get_required_capability(): string {
        return "mod/quiz:manage";
    }

    public function get_input_schema(): array {
        return $this->object_schema(["cmid" => ["type" => "integer", "minimum" => 1], "slotid" => ["type" => "integer", "minimum" => 1]], ["cmid", "slotid"]);
    }

    public function resolve_context(array $arguments): context {
        return context_module::instance((int)$arguments["cmid"], MUST_EXIST);
    }

    public function supports_dry_run(): bool {
        return true;
    }

    public function preview(array $arguments, authenticated_identity $identity): array {
        \mcptool_quiz\quiz_service::target((int)$arguments["cmid"], $identity, "mod/quiz:manage");
        return ["operation" => $this->get_name(), "arguments" => $arguments,
            "requires_confirmation" => true];
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        return quiz_service::remove($arguments, $identity);
    }
}
