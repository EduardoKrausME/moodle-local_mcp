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
 * Add users to a cohort.
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
 * Add users to a cohort.
 */
final class add_cohort_members extends \local_mcp\write\tool\base_tool {
    public function get_name(): string {
        return "cohort_add_cohort_members";
    }

    public function get_title(): string {
        return "Add users to a cohort";
    }

    public function get_description(): string {
        return "Add users to a cohort. WRITE uses preview and explicit confirmation.  ";
    }

    public function get_required_capability(): string {
        return "moodle/cohort:assign";
    }

    public function get_input_schema(): array {
        return $this->object_schema(["cohortid" => ["type" => "integer", "minimum" => 1], "userids" => ["type" => "array", "minItems" => 1, "maxItems" => 50, "uniqueItems" => true, "items" => ["type" => "integer", "minimum" => 1]], "expected_members_sha256" => ["type" => "string", "pattern" => "^[a-f0-9]{64}$"]], ["cohortid", "userids", "expected_members_sha256"]);
    }

    public function resolve_context(array $arguments): context {
        return service::cohort_context((int)$arguments["cohortid"]);
    }

    public function supports_dry_run(): bool {
        return true;
    }

    public function preview(array $arguments, authenticated_identity $identity): array {
        return service::members_preview($arguments, $identity, false);
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        return service::change_members($arguments, $identity, false);
    }
}
