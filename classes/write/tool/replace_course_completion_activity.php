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
 * Safe activity criterion replacement.
 * @package local_mcp
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;

use context;
use context_course;
use local_mcp\completion\activity_replacement;
use local_mcp\security\authenticated_identity;

/** Confirmed, conservative replacement of a native activity completion criterion. */
final class replace_course_completion_activity extends base_tool {
    /** @return string Tool name. */
    public function get_name(): string {
        return "replace_course_completion_activity";
    }

    /** @return string Tool title. */
    public function get_title(): string {
        return "Replace course activity completion criterion";
    }

    /** @return string Tool description. */
    public function get_description(): string {
        return "Replace an equivalent published view-only page or mod_mcp criterion without deleting academic history. "
            . "Review access warnings, certificate and custom dependencies and coordinate a quiescent migration window first. "
            . "The preview reports native checks and external dependency warnings; explicit confirmation is required.";
    }

    /** @return string Capability. */
    public function get_required_capability(): string {
        return "moodle/course:update";
    }

    /** @return array Input schema. */
    public function get_input_schema(): array {
        return $this->object_schema([
            "courseid" => ["type" => "integer", "minimum" => 1],
            "sourcecmid" => ["type" => "integer", "minimum" => 1],
            "targetcmid" => ["type" => "integer", "minimum" => 1],
            "preservehistory" => ["type" => "boolean"],
            "expected_criteria_sha256" => ["type" => "string", "pattern" => "^[a-f0-9]{64}$"],
        ], ["courseid", "sourcecmid", "targetcmid", "preservehistory", "expected_criteria_sha256"]);
    }

    /** @return context Course context. */
    public function resolve_context(array $arguments): context {
        return context_course::instance((int)$arguments["courseid"], MUST_EXIST);
    }

    /** @return bool Preview support. */
    public function supports_dry_run(): bool {
        return true;
    }

    /** Preview without writes. */
    public function preview(array $arguments, authenticated_identity $identity): array {
        return activity_replacement::preview($arguments, $identity);
    }

    /** Apply confirmed replacement. */
    public function execute(array $arguments, authenticated_identity $identity): array {
        return activity_replacement::execute($arguments, $identity);
    }
}
