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
 * Create a cohort.
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
 * Create a cohort.
 */
final class create_cohort extends \local_mcp\write\tool\base_tool {
    public function get_name(): string {
        return "cohort_create_cohort";
    }

    public function get_title(): string {
        return "Create a cohort";
    }

    public function get_description(): string {
        return "Create a cohort. WRITE uses preview and explicit confirmation.  ";
    }

    public function get_required_capability(): string {
        return "moodle/cohort:manage";
    }

    public function get_input_schema(): array {
        return $this->object_schema(["contextid" => ["type" => "integer", "minimum" => 1], "name" => ["type" => "string", "minLength" => 1, "maxLength" => 255], "idnumber" => ["type" => "string", "maxLength" => 100], "description" => ["type" => "string", "maxLength" => 10000], "visible" => ["type" => "boolean"]], ["name"]);
    }

    public function resolve_context(array $arguments): context {
        return service::scope((int)($arguments["contextid"] ?? 0));
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
