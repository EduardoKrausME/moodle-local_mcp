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
 * Cohort MCP tool provider.
 *
 * @package   mcptool_cohort
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_cohort;

use local_mcp\extension\read_provider_interface;
use local_mcp\extension\write_provider_interface;

/**
 * Registers native Moodle cohort tools without modifying the parent MCP registry.
 */
final class provider implements read_provider_interface, write_provider_interface {
    public function get_read_tools(): array {
        return [
            new read\search_cohorts(),
            new read\get_cohort(),
            new read\get_cohort_members(),
            new read\get_cohort_courses(),
        ];
    }

    public function get_write_tools(): array {
        return [
            new write\create_cohort(),
            new write\update_cohort(),
            new write\delete_cohort(),
            new write\add_cohort_members(),
            new write\remove_cohort_members(),
            new write\enrol_cohort(),
        ];
    }
}
