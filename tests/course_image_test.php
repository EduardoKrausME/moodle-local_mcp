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
 * Tests for course image READ/WRITE MCP tools.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use context_course;
use local_mcp\course\image_service;
use local_mcp\exception\api_exception;
use local_mcp\read\tool\get_course_image;
use local_mcp\write\tool\set_course_image;

defined('MOODLE_INTERNAL') || die;

/** Course image integration tests. */
final class course_image_test extends advanced_testcase {
    /** @return string A small valid PNG. */
    private static function png(): string {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg==');
    }

    /** @return void */
    public function test_tools_are_registered_on_the_correct_sides(): void {
        $read = \local_mcp\read\registry::get_tools();
        $write = \local_mcp\write\registry::get_tools();
        $this->assertInstanceOf(get_course_image::class, $read['get_course_image']);
        $this->assertInstanceOf(set_course_image::class, $write['set_course_image']);
        $this->assertArrayNotHasKey('set_course_image', $read);
        $this->assertArrayNotHasKey('get_course_image', $write);
    }

    /** @return void */
    public function test_read_replace_and_preserve_non_image_files(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        $userid = get_admin()->id;
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'course',
            'filearea' => 'overviewfiles',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'guide.txt',
            'userid' => $userid,
        ], 'Other material should not be lost');
        $this->assertFalse(image_service::read($course->id)['hasimage']);
        $result = image_service::replace($course->id, ['image_base64' => base64_encode(self::png())], $userid);
        $this->assertSame($course->id, $result['courseid']);
        $this->assertSame('image/png', $result['image']['mimetype']);
        $this->assertSame(1, $result['image']['width']);
        $this->assertNotEmpty($result['image']['url']);
        $this->assertNotFalse($fs->get_file($context->id, 'course', 'overviewfiles', 0, '/', 'guide.txt'));
        $first = image_service::read($course->id);
        $this->assertTrue($first['hasimage']);
        $this->assertCount(1, $first['images']);
        $this->assertCount(1, $first['_mcp_content']);
        $this->assertSame(self::png(), base64_decode($first['_mcp_content'][0]['data']));

        // The next upload replaces the previous image, not unrelated course files.
        $second = image_service::replace($course->id, [
            'image_base64' => base64_encode(self::png()),
            'expected_contenthash' => $first['image']['contenthash'],
        ], $userid);
        $this->assertSame(1, $second['replaced']);
        $this->assertCount(1, image_service::images($course->id));
        $this->assertNotFalse($fs->get_file($context->id, 'course', 'overviewfiles', 0, '/', 'guide.txt'));
    }

    /** @return void */
    public function test_invalid_or_outdated_upload_is_rejected(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->expectException(api_exception::class);
        image_service::replace($course->id, [
            'image_base64' => base64_encode(self::png()),
            'expected_contenthash' => 'different-image-hash',
        ], get_admin()->id);
    }

    /** @return void */
    public function test_rejects_svg_and_private_url(): void {
        $this->expectException(api_exception::class);
        image_service::decode(['image_base64' => base64_encode('<svg><script>alert(1)</script></svg>')]);
    }
}
