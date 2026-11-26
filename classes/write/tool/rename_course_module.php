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
 * Generic course module metadata editing.
 *
 * @package local_mcp
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;

use context;
use context_module;
use local_mcp\course\activity_metadata_service;
use local_mcp\security\authenticated_identity;

/**
 * Edit only the shared metadata of an existing activity.
 */
final class rename_course_module extends base_tool {
    /** @return string Tool name. */
    public function get_name(): string {
        return "rename_course_module";
    }

    /** @return string Tool title. */
    public function get_title(): string {
        return "Rename or edit course module metadata";
    }

    /** @return string Tool description. */
    public function get_description(): string {
        return "Update an existing activity name, HTML introduction or show-description flag, "
            . "without recreating the activity. Supply only fields to change. "
            . "Unsupported module features are rejected. Existing activity content, attempts and completion settings "
            . "are not replaced; changing intro edits the introduction itself. Requires confirmation.";
    }

    /** @return string Required capability. */
    public function get_required_capability(): string {
        return "moodle/course:manageactivities";
    }

    /** @return array Input schema. */
    public function get_input_schema(): array {
        return $this->object_schema([
            "cmid" => ["type" => "integer", "minimum" => 1],
            "name" => ["type" => "string", "minLength" => 1, "maxLength" => 255],
            "intro" => ["type" => "string", "maxLength" => 30000,
                "description" => "Sanitized HTML introduction; empty string clears it. Existing files are preserved."],
            "showdescription" => ["type" => "boolean", "description" => "Show the introduction on the course page."],
            "expectedname" => ["type" => "string", "description" => "Optional exact current stored title."],
            "expected_metadata_sha256" => ["type" => "string", "pattern" => "^[a-f0-9]{64}$"],
        ], ["cmid"]);
    }

    /**
     * @param array $arguments Tool arguments.
     * @return context Module context.
     */
    public function resolve_context(array $arguments): context {
        return context_module::instance((int)$arguments["cmid"], MUST_EXIST);
    }

    /** @return bool Supports a non-mutating preview. */
    public function supports_dry_run(): bool {
        return true;
    }

    /**
     * @param array $arguments Tool arguments.
     * @param authenticated_identity $identity Connected user.
     * @return array Metadata preview.
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        return activity_metadata_service::preview($arguments, $identity);
    }

    /**
     * @param array $arguments Tool arguments.
     * @param authenticated_identity $identity Connected user.
     * @return array Verified metadata.
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        return activity_metadata_service::update($arguments, $identity);
    }
}
