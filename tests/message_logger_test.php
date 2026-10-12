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
 * Test protocol message logging.
 *
 * @package local_mcp
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\protocol\message_logger;

/**
 * Test request/response logging without exposing credentials.
 */
final class message_logger_test extends advanced_testcase {
    /**
     * @return void
     */
    public function test_initialize_logs_server_version(): void {
        $lines = message_logger::format('outgoing', 'server', [
            'result' => ['serverInfo' => ['version' => '1.3.20']],
        ], 200);
        $this->assertCount(1, $lines);
        $result = json_decode(substr($lines[0], strlen('[local_mcp] ')), true);
        $this->assertSame('outgoing', $result['direction']);
        $this->assertSame(200, $result['httpstatus']);
        $this->assertSame('1.3.20', $result['payload']['result']['serverInfo']['version']);
    }

    /**
     * @return void
     */
    public function test_redacts_secrets_and_embedded_json(): void {
        $token = 'mcp_at_' . str_repeat('x', 40);
        $confirm = 'mcp_confirm_' . str_repeat('z', 40);
        $lines = message_logger::format('incoming', 'server', [
            'method' => 'tools/call',
            'params' => ['arguments' => [
                'shortname' => 'COURSE-001',
                'confirmation_token' => $confirm,
                'password' => 'sensitive-value',
                'image_base64' => str_repeat('A', 200),
            ]],
            'content' => [['type' => 'text', 'text' => json_encode([
                'authorization' => 'Bearer ' . $token,
                'confirmation_token' => $confirm,
            ])]],
        ]);
        $combined = implode("\n", $lines);
        $this->assertStringContainsString('COURSE-001', $combined);
        $this->assertStringNotContainsString($confirm, $combined);
        $this->assertStringNotContainsString($token, $combined);
        $this->assertStringNotContainsString('sensitive-value', $combined);
        $this->assertStringNotContainsString(str_repeat('A', 200), $combined);
    }

    /**
     * @return void
     */
    public function test_large_tool_list_is_split_and_correlated(): void {
        $lines = message_logger::format('outgoing', 'server', [
            'result' => ['tools' => array_fill(0, 200, [
                'name' => 'get_course', 'description' => str_repeat('description ', 15),
            ])],
        ], 200);
        $this->assertGreaterThan(1, count($lines));
        $first = json_decode(substr($lines[0], strlen('[local_mcp] ')), true);
        $last = json_decode(substr(end($lines), strlen('[local_mcp] ')), true);
        $this->assertSame($first['request'], $last['request']);
        $this->assertSame(count($lines), $last['part']);
        $this->assertSame(count($lines), $first['parts']);
        $this->assertFalse($first['truncated']);
    }
}
