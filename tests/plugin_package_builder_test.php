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
//
/**
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\plugin\package_builder;

/**
 * Verify ZIP manifests contain the actual Moodle URL, not an app ID reference.
 *
 * @covers \local_mcp\plugin\package_builder
 */
final class plugin_package_builder_test extends advanced_testcase {
    /**
     * Portable and legacy formats must both use the selected site's MCP URL.
     */
    public function test_manifests_include_mcp_server_and_oauth_dcr(): void {
        $url = 'https://hagorah.com.br/local/mcp/server.php';
        $files = package_builder::get_files($url, 'Hagorah');

        $this->assertCount(5, $files);
        $portable = json_decode($files['mcp.json'], true, 512, JSON_THROW_ON_ERROR);
        $legacy = json_decode($files['.mcp.json'], true, 512, JSON_THROW_ON_ERROR);
        $plugin = json_decode($files['plugin.json'], true, 512, JSON_THROW_ON_ERROR);
        $legacyplugin = json_decode($files['.codex-plugin/plugin.json'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($url, $portable['mcpServers']['moodle']['url']);
        $this->assertSame('streamable-http', $portable['mcpServers']['moodle']['type']);
        $this->assertSame($url, $legacy['mcpServers']['moodle']['url']);
        $this->assertSame('http', $legacy['mcpServers']['moodle']['type']);
        $auth = $portable['mcpServers']['moodle']['extensions']['com.openai']['auth'];
        $this->assertSame('oauth', $auth['type']);
        $this->assertSame('dcr', $auth['client']['mode']);
        $this->assertSame($url, $auth['authorizationServerBase']);
        $this->assertSame($url, $auth['resource']);
        $this->assertSame('https://hagorah.com.br/local/mcp/oauth/register.php', $auth['registrationUrl']);
        $this->assertSame('moodle-hagorah-com-br', $plugin['name']);
        $this->assertSame('./.mcp.json', $legacyplugin['mcpServers']);
        $this->assertStringContainsString($url, $files['README.txt']);
        $this->assertStringNotContainsString('asdk_app_', implode("\n", $files));
    }

    /**
     * HTTPS is required before offering a remotely accessible MCP package.
     */
    public function test_http_endpoint_is_rejected(): void {
        $this->expectException(\invalid_argument_exception::class);
        package_builder::get_files('http://example.org/local/mcp/server.php', 'Test site');
    }

    /**
     * A non-MCP URL should not be embedded as a downloadable server.
     */
    public function test_unrelated_endpoint_is_rejected(): void {
        $this->expectException(\invalid_argument_exception::class);
        package_builder::get_files('https://example.org/api/another-endpoint.php', 'Test site');
    }
}
