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
 * Register the book activity MCP tools.
 *
 * @package   mcptool_book
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_book;

use local_mcp\extension\read_provider_interface;
use local_mcp\extension\write_provider_interface;
use mcptool_book\read\get_chapter;
use mcptool_book\read\list_chapters;
use mcptool_book\write\create_activity;
use mcptool_book\write\create_chapter;
use mcptool_book\write\update_chapter;

/**
 * All Moodle book tooling is self-contained in this subplugin.
 */
final class provider implements read_provider_interface, write_provider_interface {
    /** @return array */
    public function get_read_tools(): array {
        return [
            new list_chapters(),
            new get_chapter(),
        ];
    }

    /** @return array */
    public function get_write_tools(): array {
        return [
            new create_activity(),
            new create_chapter(),
            new update_chapter(),
        ];
    }
}
