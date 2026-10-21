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
 * Tests for MCP untrusted content boundaries.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\protocol\mcp_server;
use local_mcp\security\authenticated_identity;
use local_mcp\security\untrusted_content;
use ReflectionMethod;

/**
 * Test that markup is flattened and warnings are supplied with read results.
 *
 * @covers \local_mcp\security\untrusted_content
 */
final class untrusted_content_test extends advanced_testcase {
    /**
     * User-authored markup must not be returned as embedded HTML to the model.
     *
     * @return void
     */
    public function test_html_becomes_plain_text(): void {
        $text = untrusted_content::text("<p>Hello <strong>students</strong>.</p>");
        $this->assertStringContainsString("Hello", $text);
        $this->assertStringContainsString("students", $text);
        $this->assertStringNotContainsString("<strong>", $text);
    }

    /**
     * All core READ results carry the trust warning in structured content.
     *
     * @return void
     */
    public function test_read_tool_returns_content_warning(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $identity = new authenticated_identity((int)get_admin()->id, "test", ["mcp:read"]);
        $method = new ReflectionMethod(mcp_server::class, "call_tool");
        $result = $method->invoke(new mcp_server("read"), "get_course",
            ["courseid" => (int)$course->id], [], $identity, "test-access-token");
        $this->assertSame(untrusted_content::NOTICE, $result["content_warning"]);
    }
}
