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
 * Register assignment READ and WRITE tools.
 *
 * @package   mcptool_assign
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_assign;

use local_mcp\extension\read_provider_interface;
use local_mcp\extension\write_provider_interface;

/**
 * Expose all installed assignment subplugin tools through the MCP extension registry.
 */
final class provider implements read_provider_interface, write_provider_interface {
    public function get_read_tools(): array {
        return [
            new \mcptool_assign\read\get_submission_history(),
            new \mcptool_assign\read\get_grading_progress(),
            new \mcptool_assign\read\get_extensions(),
            new \mcptool_assign\read\get_grading_guide(),
            new \mcptool_assign\read\get_submission_document(),
            new \mcptool_assign\read\get_group_submissions(),
            new \mcptool_assign\read\get_anonymous_submissions(),
        ];
    }

    public function get_write_tools(): array {
        return [
            new \mcptool_assign\write\create_activity(),
            new \mcptool_assign\write\update_activity(),
            new \mcptool_assign\write\set_extension(),
            new \mcptool_assign\write\reopen_submission(),
            new \mcptool_assign\write\save_grade_and_feedback(),
            new \mcptool_assign\write\apply_rubric_draft(),
            new \mcptool_assign\write\set_marking_workflow(),
            new \mcptool_assign\write\release_grades(),
        ];
    }
}
