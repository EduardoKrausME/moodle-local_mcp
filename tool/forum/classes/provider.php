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
 * Register the forum activity MCP tools.
 *
 * @package   mcptool_forum
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_forum;

use local_mcp\extension\read_provider_interface;
use local_mcp\extension\write_provider_interface;
use mcptool_forum\read\get_posts;
use mcptool_forum\read\list_discussions;
use mcptool_forum\write\create_activity;
use mcptool_forum\write\create_discussion;
use mcptool_forum\write\reply_to_post;

/**
 * All Moodle forum tooling is self-contained in this subplugin.
 */
final class provider implements read_provider_interface, write_provider_interface {
    /** @return array */
    public function get_read_tools(): array {
        return [
            new list_discussions(),
            new get_posts(),
        ];
    }

    /** @return array */
    public function get_write_tools(): array {
        return [
            new create_activity(),
            new create_discussion(),
            new reply_to_post(),
        ];
    }
}
