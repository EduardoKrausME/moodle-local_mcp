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
 * Delete an unlinked cohort.
 *
 * @package   mcptool_cohort
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_cohort\write;

use context;
use local_mcp\security\authenticated_identity;
use mcptool_cohort\service;

/**
 * Delete an unlinked cohort.
 */
final class delete_cohort extends \local_mcp\write\tool\base_tool {
    public function get_name(): string {
        return "cohort_delete_cohort";
    }

    public function get_title(): string {
        return "Delete an unlinked cohort";
    }

    public function get_description(): string {
        return "Delete an unlinked cohort. WRITE uses preview and explicit confirmation. Cannot delete when linked to course enrolment. ";
    }

    public function get_required_capability(): string {
        return "moodle/cohort:manage";
    }

    public function get_input_schema(): array {
        return $this->object_schema(["cohortid" => ["type" => "integer", "minimum" => 1], "expected_revision" => ["type" => "string", "pattern" => "^[a-f0-9]{64}$"], "expected_members_sha256" => ["type" => "string", "pattern" => "^[a-f0-9]{64}$"]], ["cohortid", "expected_revision", "expected_members_sha256"]);
    }

    public function resolve_context(array $arguments): context {
        return service::cohort_context((int)$arguments["cohortid"]);
    }

    public function supports_dry_run(): bool {
        return true;
    }

    public function is_destructive(): bool {
        return true;
    }

    public function preview(array $arguments, authenticated_identity $identity): array {
        return service::delete_preview($arguments, $identity);
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        return service::delete($arguments, $identity);
    }
}
