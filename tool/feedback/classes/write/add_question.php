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
 * Add Feedback question tool.
 *
 * @package   mcptool_feedback
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_feedback\write;

use context;
use context_module;
use local_mcp\security\authenticated_identity;
use mcptool_feedback\service;

/**
 * Add Feedback question, with Moodle capability and privacy checks.
 */
final class add_question extends \local_mcp\write\tool\base_tool {
    public function get_name(): string {
        return "feedback_add_question";
    }

    public function get_title(): string {
        return "Add Feedback question";
    }

    public function get_description(): string {
        return "Modify the survey through the Moodle Feedback item handler with confirmation.";
    }

    public function get_required_capability(): string {
        return "mod/feedback:edititems";
    }

    public function get_input_schema(): array {
        return $this->object_schema(["cmid" => ["type" => "integer", "minimum" => 1], "name" => ["type" => "string", "minLength" => 1, "maxLength" => 255], "type" => ["type" => "string", "enum" => ["multichoice", "textarea"]], "expected_items_sha256" => ["type" => "string", "pattern" => "^[a-f0-9]{64}$"], "required" => ["type" => "boolean"], "options" => ["type" => "array", "items" => ["type" => "string", "minLength" => 1, "maxLength" => 255], "maxItems" => 20]], ["cmid", "name", "type", "expected_items_sha256"]);
    }

    public function resolve_context(array $arguments): context {
        return context_module::instance((int)$arguments["cmid"], MUST_EXIST);
    }

    public function supports_dry_run(): bool {
        return true;
    }

    public function preview(array $arguments, authenticated_identity $identity): array {
        return service::question_preview($arguments, $identity, false);
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        return service::save_question($arguments, $identity, false);
    }
}
