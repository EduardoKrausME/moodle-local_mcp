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
 * Get cohort details and revision.
 *
 * @package   mcptool_cohort
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_cohort\read;

use context;
use local_mcp\security\authenticated_identity;
use mcptool_cohort\service;

/**
 * Get cohort details and revision.
 */
final class get_cohort extends \local_mcp\read\tool\base_tool {
    public function get_name(): string {
        return "cohort_get_cohort";
    }

    public function get_title(): string {
        return "Get cohort details and revision";
    }

    public function get_description(): string {
        return "Get cohort details and revision. READ.  ";
    }

    public function get_required_capability(): string {
        return "moodle/cohort:view";
    }

    public function get_input_schema(): array {
        return $this->object_schema(["cohortid" => ["type" => "integer", "minimum" => 1]], ["cohortid"]);
    }

    public function resolve_context(array $arguments): context {
        return service::cohort_context((int)$arguments["cohortid"]);
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        return service::get($arguments, $identity);
    }
}
