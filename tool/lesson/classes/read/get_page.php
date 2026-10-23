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
 * Read Lesson page MCP tool.
 *
 * @package   mcptool_lesson
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_lesson\read;

use context;
use context_module;
use local_mcp\security\authenticated_identity;
use mcptool_lesson\service;

/**
 * Read Lesson page through the native Moodle Lesson APIs.
 */
final class get_page extends \local_mcp\read\tool\base_tool {
    public function get_name(): string {
        return "lesson_get_page";
    }

    public function get_title(): string {
        return "Read Lesson page";
    }

    public function get_description(): string {
        return "Read the teacher-visible Lesson page structure and navigation.";
    }

    public function get_required_capability(): string {
        return "mod/lesson:edit";
    }

    public function get_input_schema(): array {
        return $this->object_schema(["cmid" => ["type" => "integer", "minimum" => 1], "pageid" => ["type" => "integer", "minimum" => 1]], ["cmid", "pageid"]);
    }

    public function resolve_context(array $arguments): context {
        return context_module::instance((int)$arguments["cmid"], MUST_EXIST);
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        return service::page($arguments, $identity);
    }
}
