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
 * Register Moodle badge READ and confirmed WRITE tools.
 *
 * @package   mcptool_badges
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_badges;

use local_mcp\extension\read_provider_interface;
use local_mcp\extension\write_provider_interface;
use mcptool_badges\read\get_badge;
use mcptool_badges\read\image_guide;
use mcptool_badges\read\list_badges;
use mcptool_badges\write\create_badge;
use mcptool_badges\write\replace_image;
use mcptool_badges\write\set_criteria;
use mcptool_badges\write\enable_badge;

/**
 * Moodle badge provider; no special cases in the parent MCP plugin.
 */
final class provider implements read_provider_interface, write_provider_interface {
    /** @return array */
    public function get_read_tools(): array {
        return [new image_guide(), new list_badges(), new get_badge()];
    }

    /** @return array */
    public function get_write_tools(): array {
        return [new create_badge(), new replace_image(), new set_criteria(), new enable_badge()];
    }
}
