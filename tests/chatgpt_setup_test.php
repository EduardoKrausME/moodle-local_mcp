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
 * chatgpt_setup_test.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\oauth\connection_service;
use local_mcp\ui\chatgpt_setup;

defined('MOODLE_INTERNAL') || die;

/**
 * Tests for the conditional ChatGPT getting-started guide.
 */
final class chatgpt_setup_test extends advanced_testcase {
    /**
     * @return void
     */
    public function test_official_chatgpt_redirects(): void {
        $this->assertTrue(connection_service::is_chatgpt_redirect_uri(
            'https://chatgpt.com/connector/oauth/example-callback'));
        $this->assertTrue(connection_service::is_chatgpt_redirect_uri(
            'https://chatgpt.com/connector_platform_oauth_redirect'));
        $this->assertFalse(connection_service::is_chatgpt_redirect_uri(
            'https://chatgpt.com.evil.example/connector/oauth/example'));
        $this->assertFalse(connection_service::is_chatgpt_redirect_uri(
            'http://chatgpt.com/connector/oauth/example'));
        $this->assertFalse(connection_service::is_chatgpt_redirect_uri(
            'https://example.com/connector/oauth/example'));
    }

    /**
     * @return void
     */
    public function test_guide_hides_only_when_chatgpt_is_connected(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->assertFalse(connection_service::has_active_chatgpt_connection());
        $this->assertTrue(chatgpt_setup::template_data()['showchatgptsetup']);

        $clientid = $DB->insert_record('local_mcp_oauth_client', (object)[
            'clientid' => 'mcp_other_test',
            'name' => 'Another MCP client',
            'description' => '',
            'clienturi' => '',
            'redirecturis' => json_encode(['https://example.org/oauth/redirect']),
            'enabled' => 1,
            'clienttype' => 'public',
            'dynamic' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $connectionid = $DB->insert_record('local_mcp_connection', (object)[
            'userid' => $user->id,
            'clientid' => $clientid,
            'scopes' => 'mcp:read',
            'enabled' => 1,
            'timecreated' => time(),
        ]);
        $this->assertFalse(connection_service::has_active_chatgpt_connection());

        $DB->set_field('local_mcp_oauth_client', 'redirecturis',
            json_encode(['https://chatgpt.com/connector/oauth/example']), ['id' => $clientid]);
        $this->assertTrue(connection_service::has_active_chatgpt_connection());
        $this->assertFalse(chatgpt_setup::template_data()['showchatgptsetup']);

        $DB->set_field('local_mcp_connection', 'enabled', 0, ['id' => $connectionid]);
        $this->assertFalse(connection_service::has_active_chatgpt_connection());
        $this->assertTrue(chatgpt_setup::template_data()['showchatgptsetup']);
    }
}
