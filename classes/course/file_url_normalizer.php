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
 * Normalize historical course file URLs received by MCP write tools.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\course;

/**
 * Remove the URL-only itemid segment from Moodle's two course file areas.
 *
 * The stored files still use itemid=0; only their pluginfile URL omits it.
 */
final class file_url_normalizer {
    /**
     * Normalize file URLs inside submitted HTML without touching other file areas.
     *
     * Match /0/ as a complete path segment: /0123/ and /0-name/ are not item IDs.
     *
     * @param string $html Submitted HTML content.
     * @return string Corrected HTML.
     */
    public static function normalize(string $html): string {
        return str_replace(
            ['/course/overviewfiles/0/', '/course/summary/0/'],
            ['/course/overviewfiles/', '/course/summary/'],
            $html
        );
    }
}
