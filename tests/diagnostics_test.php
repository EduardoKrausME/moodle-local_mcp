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
 * diagnostics_test.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\diagnostics;
use local_mcp\exception\api_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Test safe and useful PHP error_log diagnostic formats.
 */
final class diagnostics_test extends advanced_testcase {
    /**
     * @return void
     */
    public function test_structured_milestone_includes_request_id_and_approved_metadata(): void {
        $log = diagnostics::format('INFO', 'tools_listed', [
            'method' => 'tools/list',
            'userid' => 2,
            'count' => 25,
            'args_keys' => ['shortname', 'summary', 'image_base64'],
            'secret_key' => 'do not log me',
        ]);
        $this->assertStringStartsWith('[local_mcp] ', $log);
        $decoded = json_decode(substr($log, strlen('[local_mcp] ')), true);
        $this->assertIsArray($decoded);
        $this->assertSame('tools_listed', $decoded['event']);
        $this->assertSame('INFO', $decoded['level']);
        $this->assertSame('tools/list', $decoded['method']);
        $this->assertSame(2, $decoded['userid']);
        $this->assertSame(25, $decoded['count']);
        $this->assertSame(['shortname', 'summary', 'image_base64'], $decoded['args_keys']);
        $this->assertSame(12, strlen($decoded['request']));
        $this->assertArrayNotHasKey('secret_key', $decoded);
    }

    /**
     * @return void
     */
    public function test_scrubs_tokens_urls_html_base64_and_newlines(): void {
        $token = 'mcp_' . str_repeat('A', 42);
        $binary = str_repeat('Q', 160);
        $log = diagnostics::format('ERROR', 'exception', [
            'message' => "Bearer {$token} https://example.com/server.php?toke={$token}"
                . " <h1>secret</h1> {$binary}\nInjected",
        ]);
        $this->assertStringNotContainsString($token, $log);
        $this->assertStringNotContainsString('https://example.com', $log);
        $this->assertStringNotContainsString($binary, $log);
        $this->assertStringNotContainsString('<h1>', $log);
        $this->assertStringNotContainsString("\n", $log);
        $this->assertStringContainsString('[redacted]', $log);
    }

    /**
     * @return void
     */
    public function test_handles_exception_without_exposing_confirmation_details(): void {
        $error = new api_exception('confirmation_required', 409, '',
            ['confirmation_token' => 'mcp_confirm_' . str_repeat('B', 32)]);
        $line = diagnostics::format('WARNING', 'exception', [
            'error_class' => get_class($error),
            'error_code' => $error->machinecode,
            'httpstatus' => $error->httpstatus,
        ]);
        $decoded = json_decode(substr($line, strlen('[local_mcp] ')), true);
        $this->assertSame('confirmation_required', $decoded['error_code']);
        $this->assertSame(409, $decoded['httpstatus']);
        $this->assertArrayNotHasKey('confirmation_token', $decoded);
    }
}
