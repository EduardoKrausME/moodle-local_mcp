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
 * Register Lesson MCP READ and WRITE tools.
 *
 * @package   mcptool_lesson
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_lesson;

use local_mcp\extension\read_provider_interface;
use local_mcp\extension\write_provider_interface;

/**
 * The Lesson extension is discovered by the MCP tool manager.
 */
final class provider implements read_provider_interface, write_provider_interface {
    public function get_read_tools(): array {
        return [
            new \mcptool_lesson\read\get_navigation(),
            new \mcptool_lesson\read\get_page(),
        ];
    }

    public function get_write_tools(): array {
        return [
            new \mcptool_lesson\write\create_activity(),
            new \mcptool_lesson\write\create_page(),
            new \mcptool_lesson\write\update_page(),
            new \mcptool_lesson\write\set_jumps(),
        ];
    }
}
