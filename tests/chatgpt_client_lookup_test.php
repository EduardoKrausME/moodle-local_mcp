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
 * Tests for memory-efficient ChatGPT OAuth client discovery.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\audit\chatgpt_client_lookup;

/**
 * Verify ChatGPT OAuth client recognition used by audit reports.
 *
 * @covers \local_mcp\audit\chatgpt_client_lookup
 */
final class chatgpt_client_lookup_test extends advanced_testcase {
    /**
     * ChatGPT clients are found by callback URL even after renaming.
     */
    public function test_finds_chatgpt_clients_independently_of_names(): void {
        $this->resetAfterTest();
        $first = $this->insert_client("one", "Renamed connection", json_encode([
            "https://example.org/oauth/callback",
            "https://chatgpt.com/connector_platform_oauth_redirect",
        ]));
        $this->insert_client("two", "ChatGPT", json_encode([
            "https://example.org/oauth/callback",
        ]));
        $second = $this->insert_client("three", "Another custom name", json_encode([
            "https://chatgpt.com/connector/oauth/test-id",
        ]));

        $this->assertEqualsCanonicalizing([$first, $second], chatgpt_client_lookup::get_client_ids());
    }

    /**
     * Malformed data or non-ChatGPT callbacks must not become matches.
     */
    public function test_ignores_invalid_and_non_chatgpt_redirects(): void {
        $this->resetAfterTest();
        $this->insert_client("invalid", "Invalid JSON", "not JSON");
        $this->insert_client("empty", "Empty callbacks", "[]");
        $this->insert_client("other", "Wrong host", json_encode([
            "https://not-chatgpt.com/connector_platform_oauth_redirect",
            "http://chatgpt.com/connector_platform_oauth_redirect",
            "https://chatgpt.com/unrelated",
            ["uri" => "https://chatgpt.com/connector_platform_oauth_redirect"],
        ]));

        $this->assertSame([], chatgpt_client_lookup::get_client_ids());
    }

    /**
     * Add an OAuth client with the fields required by the database schema.
     *
     * @param string $key Unique client identifier suffix.
     * @param string $name Client display name.
     * @param string $redirecturis JSON redirect URI list.
     * @return int Created client record ID.
     */
    private function insert_client(string $key, string $name, string $redirecturis): int {
        global $DB;

        return (int)$DB->insert_record("local_mcp_oauth_client", (object)[
            "clientid" => "test-client-" . $key,
            "name" => $name,
            "redirecturis" => $redirecturis,
            "enabled" => 1,
            "clienttype" => "public",
            "dynamic" => 0,
            "timecreated" => time(),
            "timemodified" => time(),
        ]);
    }
}
