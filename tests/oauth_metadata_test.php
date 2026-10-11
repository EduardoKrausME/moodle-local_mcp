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
 * OAuth discovery endpoint interoperability tests.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\oauth\metadata;
use local_mcp\oauth\resource;

/**
 * Tests RFC 8414 discovery and compatibility with path-aware MCP clients.
 */
final class oauth_metadata_test extends advanced_testcase {
    /**
     * @return void
     */
    public function test_oauth_issuer_matches_the_requested_well_known_path(): void {
        global $CFG;

        $this->resetAfterTest();
        $base = $CFG->wwwroot;
        $this->assertSame($base . '/local/mcp', metadata::authorization_server(
            '/.well-known/oauth-authorization-server/local/mcp')['issuer']);
        $this->assertSame($base . '/local/mcp/server.php', metadata::authorization_server(
            '/.well-known/oauth-authorization-server/local/mcp/server.php')['issuer']);
        $this->assertSame($base . '/local/mcp/server.php', metadata::authorization_server(
            '/local/mcp/server.php/.well-known/oauth-authorization-server')['issuer']);
        $this->assertSame($base, metadata::authorization_server(
            '/.well-known/oauth-authorization-server')['issuer']);
        $this->assertSame($base, metadata::authorization_server(
            '/.well-known/openid-configuration')['issuer']);
        $this->assertSame($base . '/local/mcp/server.php', metadata::authorization_server(
            '/.well-known/openid-configuration/local/mcp/server.php')['issuer']);
    }

    /**
     * @return void
     */
    public function test_oauth_discovery_only_advertises_configured_endpoints(): void {
        global $CFG;

        $this->resetAfterTest();
        set_config('dynamicregistration', 1, 'local_mcp');
        $data = metadata::authorization_server('/.well-known/oauth-authorization-server/local/mcp');
        $base = $CFG->wwwroot . '/local/mcp';
        $this->assertSame($base . '/oauth/authorize.php', $data['authorization_endpoint']);
        $this->assertSame($base . '/oauth/token.php', $data['token_endpoint']);
        $this->assertSame($base . '/oauth/register.php', $data['registration_endpoint']);
        $this->assertSame(['S256'], $data['code_challenge_methods_supported']);
        set_config('dynamicregistration', 0, 'local_mcp');
        $this->assertArrayNotHasKey('registration_endpoint', metadata::authorization_server());
    }

    /**
     * @return void
     */
    public function test_protected_resource_metadata_is_bound_to_mcp_endpoint(): void {
        global $CFG;

        $this->assertSame(resource::endpoint('server'),
            metadata::protected_resource('server')['resource']);
        $this->assertSame(resource::endpoint('write'),
            metadata::protected_resource('server', '/.well-known/oauth-protected-resource/local/mcp/write.php')['resource']);
        $this->assertSame(['mcp:write'], metadata::protected_resource('write')['scopes_supported']);
        $this->assertSame([$CFG->wwwroot . '/local/mcp'],
            metadata::protected_resource()['authorization_servers']);
    }

    /**
     * @return void
     */
    public function test_appended_discovery_only_matches_known_metadata_paths(): void {
        $this->assertTrue(metadata::is_appended_discovery('server',
            '/local/mcp/server.php/.well-known/oauth-authorization-server'));
        $this->assertTrue(metadata::is_appended_discovery('server',
            '/local/mcp/server.php/.well-known/openid-configuration'));
        $this->assertFalse(metadata::is_appended_discovery('server', '/local/mcp/server.php'));
        $this->assertFalse(metadata::is_appended_discovery('server',
            '/local/mcp/read.php/.well-known/oauth-authorization-server'));
    }
}
