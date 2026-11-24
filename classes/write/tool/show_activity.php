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
 * Show one Moodle activity using native visibility. This does not publish authored content or override access restrictions.
 *
 * @package local_mcp
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;

use context;
use context_module;
use local_mcp\course\activity_position_service;
use local_mcp\security\authenticated_identity;

/**
 * Confirmed native course activity operation.
 */
final class show_activity extends base_tool {
    /** @return string Tool name. */
    public function get_name(): string {
        return "show_activity";
    }

    /** @return string Tool title. */
    public function get_title(): string {
        return "show activity";
    }

    /** @return string Tool description. */
    public function get_description(): string {
        return "Show one Moodle activity using native visibility. This does not publish authored content or override access restrictions.";
    }

    /** @return string Required capability. */
    public function get_required_capability(): string {
        return "moodle/course:activityvisibility";
    }

    /** @return array Input schema. */
    public function get_input_schema(): array {
        return $this->object_schema([
            "cmid" => ["type" => "integer", "minimum" => 1],
            "expectedvisible" => ["type" => "boolean"],
        ], ["cmid"]);
    }

    /**
     * @param array $arguments Input arguments.
     * @return context Target module context.
     */
    public function resolve_context(array $arguments): context {
        return context_module::instance((int)$arguments["cmid"], MUST_EXIST);
    }

    /** @return bool Supports preview. */
    public function supports_dry_run(): bool {
        return true;
    }

    /**
     * @param array $arguments Input arguments.
     * @param authenticated_identity $identity Connected identity.
     * @return array Preview.
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        return activity_position_service::visibility_preview($arguments, $identity, true);
    }

    /**
     * @param array $arguments Input arguments.
     * @param authenticated_identity $identity Connected identity.
     * @return array Verified result.
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        return activity_position_service::visibility($arguments, $identity, true);
    }
}
