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
 * Regression tests for legacy course file URLs in MCP HTML.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use basic_testcase;
use local_mcp\course\file_url_normalizer;

/**
 * @covers \local_mcp\course\file_url_normalizer
 */
final class course_file_url_normalizer_test extends basic_testcase {
    /**
     * Both course file areas omit itemid=0 from the public URL.
     */
    public function test_normalizes_both_file_areas_without_touching_other_paths(): void {
        $html = '<img src="https://example.com/pluginfile.php/23/course/overviewfiles/0/cover.png">'
            . '<a href="/pluginfile.php/23/course/summary/0/manual.pdf">Read</a>'
            . '<img src="/pluginfile.php/23/course/overviewfiles/0/nested/image.png">'
            . '<img src="/pluginfile.php/23/course/section/0/keep.png">'
            . '<img src="/pluginfile.php/23/course/summary/0123/keep.png">'
            . '<img src="/pluginfile.php/23/mod_page/content/0/keep.png">';

        $expected = '<img src="https://example.com/pluginfile.php/23/course/overviewfiles/cover.png">'
            . '<a href="/pluginfile.php/23/course/summary/manual.pdf">Read</a>'
            . '<img src="/pluginfile.php/23/course/overviewfiles/nested/image.png">'
            . '<img src="/pluginfile.php/23/course/section/0/keep.png">'
            . '<img src="/pluginfile.php/23/course/summary/0123/keep.png">'
            . '<img src="/pluginfile.php/23/mod_page/content/0/keep.png">';

        $this->assertSame($expected, file_url_normalizer::normalize($html));
        $this->assertSame($expected, file_url_normalizer::normalize($expected));
    }
}
