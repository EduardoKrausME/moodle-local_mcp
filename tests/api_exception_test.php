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
}
