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
 * Register Feedback MCP READ and WRITE operations.
 *
 * @package   mcptool_feedback
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_feedback;

use local_mcp\extension\read_provider_interface;
use local_mcp\extension\write_provider_interface;

/**
 * Feedback-specific MCP provider, auto-discovered by the core registry.
 */
final class provider implements read_provider_interface, write_provider_interface {
    /**
     * Method get_read_tools.
     *
     * @return array Return value.
     */
    public function get_read_tools(): array {
        return [
            new \mcptool_feedback\read\get_questions(),
            new \mcptool_feedback\read\get_summary(),
            new \mcptool_feedback\read\get_responses(),
        ];
    }

    /**
     * Method get_write_tools.
     *
     * @return array Return value.
     */
    public function get_write_tools(): array {
        return [
            new \mcptool_feedback\write\create_activity(),
            new \mcptool_feedback\write\add_question(),
            new \mcptool_feedback\write\update_question(),
        ];
    }
}
