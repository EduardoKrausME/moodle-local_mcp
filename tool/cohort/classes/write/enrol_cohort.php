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
 * Link cohort to a course with cohort sync.
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
 * Link cohort to a course with cohort sync.
 */
final class enrol_cohort extends \local_mcp\write\tool\base_tool {
    public function get_name(): string {
        return "cohort_enrol_cohort";
    }

    public function get_title(): string {
        return "Link cohort to a course with cohort sync";
    }

    public function get_description(): string {
        return "Link cohort to a course with cohort sync. WRITE uses preview and explicit confirmation.  Synchronises users via native enrol_cohort.";
    }

    public function get_required_capability(): string {
        return "enrol/cohort:config";
    }

    public function get_input_schema(): array {
        return $this->object_schema(["courseid" => ["type" => "integer", "minimum" => 2], "cohortid" => ["type" => "integer", "minimum" => 1], "roleid" => ["type" => "integer", "minimum" => 1]], ["courseid", "cohortid", "roleid"]);
    }

    public function resolve_context(array $arguments): context {
        return \context_course::instance((int)$arguments["courseid"], MUST_EXIST);
    }

    public function supports_dry_run(): bool {
        return true;
    }

    public function preview(array $arguments, authenticated_identity $identity): array {
        return service::enrol_preview($arguments, $identity);
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        return service::enrol($arguments, $identity);
    }
}
