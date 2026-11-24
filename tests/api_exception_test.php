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
 * Tests for the MCP API exception.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\exception\api_exception;

/**
 * Test the localized API exception without altering machine-readable errors.
 *
 * @covers \local_mcp\exception\api_exception
 */
final class api_exception_test extends advanced_testcase {
    /**
     * The exception has a translated message and retains its API metadata.
     */
    public function test_localized_exception_preserves_api_error_data(): void {
        $exception = new api_exception("invalid_request", 422, "Invalid parameter", ["field" => "name"]);

        $this->assertStringStartsWith(get_string("error", "local_mcp"), $exception->getMessage());
        $this->assertSame("invalid_request", $exception->machinecode);
        $this->assertSame(422, $exception->httpstatus);
        $this->assertSame("Invalid parameter", $exception->debuginfo);
        $this->assertSame(["field" => "name"], $exception->details);
    }

    /** Explicit public messages do not expose legacy debug details. */
    public function test_public_message_is_opt_in(): void {
        $legacy = new api_exception('invalid_request', 400, 'private debug detail');
        $this->assertStringNotContainsString('private debug detail', $legacy->get_public_message());
        $safe = new api_exception('configuration_required', 409, 'private debug detail', [], 'Configure first.');
        $this->assertSame('Configure first.', $safe->get_public_message());
        $this->assertSame($safe, api_exception::from_throwable($safe));
    }

    /** Only allowlisted native errors are converted, without leaking their arguments. */
    public function test_native_error_mapping(): void {
        $course = api_exception::from_throwable(new \moodle_exception('invalidcourseid', 'error', '', 'private'));
        $this->assertSame('course_not_found', $course->machinecode);
        $this->assertSame(404, $course->httpstatus);
        $this->assertStringNotContainsString('private', $course->get_public_message());
        $activity = api_exception::from_throwable(new \moodle_exception('invalidcoursemodule'));
        $this->assertSame('activity_not_found', $activity->machinecode);
        $this->assertNull(api_exception::from_throwable(new \moodle_exception('invalidcourseid', 'custom')));
        $this->assertNull(api_exception::from_throwable(new \RuntimeException('SQL or secret')));
        $this->assertNull(api_exception::from_throwable(new \coding_exception('private details')));
    }
}
