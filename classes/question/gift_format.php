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
 * Import a complete GIFT question bank through Moodle's native question APIs.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\question;

defined("MOODLE_INTERNAL") || die();

global $CFG;
require_once($CFG->dirroot . "/question/format.php");
require_once($CFG->dirroot . "/question/format/gift/format.php");

/**
 * Expose a read-only parsing pass for previewing the native GIFT importer.
 */
final class gift_format extends \qformat_gift {
    /**
     * Parse the original GIFT payload without creating questions or categories.
     *
     * @param string $gift UTF-8 GIFT text.
     * @return array Parsed Moodle question objects.
     */
    public function inspect_content(string $gift): array {
        $lines = preg_split("/\r\n|\n|\r/", \core_text::trim_utf8_bom($gift));
        return $this->readquestions($lines);
    }
}
