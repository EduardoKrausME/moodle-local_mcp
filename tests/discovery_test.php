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
 * discovery_test.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\protocol\discovery;
use local_mcp\protocol\mcp_server;
use local_mcp\security\authenticated_identity;
use ReflectionMethod;

/**
 * Validate the new MCP handshake without needing an HTTP session.
 *
 * @covers \local_mcp\protocol\discovery
 */
final class discovery_test extends advanced_testcase {
    /**
     * Reproduce ChatGPT's server/discover request.
     *
     * @return void
     */
    public function test_discovery_is_mcp_2026_07_28_compliant(): void {
        $result = discovery::result('server', '1.3.1');

        $this->assertSame('complete', $result['resultType']);
        $this->assertContains('2026-07-28', $result['supportedVersions']);
        $this->assertContains('2025-06-18', $result['supportedVersions']);
        $this->assertArrayHasKey('tools', $result['capabilities']);
        $this->assertIsObject($result['capabilities']['tools']);
        $this->assertSame('Moodle MCP SERVER', $result['_meta']['io.modelcontextprotocol/serverInfo']['name']);
        $this->assertSame('1.3.1', $result['_meta']['io.modelcontextprotocol/serverInfo']['version']);
        $this->assertSame('private', $result['cacheScope']);
        $this->assertSame(discovery::CACHE_TTL_MS, $result['ttlMs']);
    }

    /**
     * test_discovery_identity_reflects_endpoint_and_release
     *
     * @return void
     */
    public function test_discovery_identity_reflects_endpoint_and_release(): void {
        $read = discovery::result('read', '1.3.1');
        $write = discovery::result('write', '1.3.1');
        $this->assertSame('Moodle MCP READ',
            $read['_meta']['io.modelcontextprotocol/serverInfo']['name']);
        $this->assertSame('Moodle MCP WRITE',
            $write['_meta']['io.modelcontextprotocol/serverInfo']['name']);
        $this->assertEquals($read['capabilities'], $write['capabilities']);
    }

    /**
     * The Moodle URL must be available in all discovery variants and tool payloads.
     *
     * @return void
     */
    public function test_wwwroot_identifies_the_moodle_instance(): void {
        global $CFG;
        $wwwroot = rtrim((string)$CFG->wwwroot, "/");

        $modern = discovery::result("server", "1.3.1");
        $this->assertSame($wwwroot, $modern["_meta"]["moodle_wwwroot"]);
        $this->assertSame($wwwroot, $modern["_meta"]["io.modelcontextprotocol/serverInfo"]["websiteUrl"]);
        $this->assertStringContainsString($wwwroot, $modern["instructions"]);

        $legacy = discovery::initialize_result("read", "1.3.1");
        $this->assertSame($wwwroot, $legacy["serverInfo"]["websiteUrl"]);
        $this->assertStringContainsString($wwwroot, $legacy["instructions"]);

        $data = discovery::with_site_identity([
            "confirmation_required" => true,
            "moodle_wwwroot" => "https://wrong.example",
        ]);
        $this->assertSame($wwwroot, $data["moodle_wwwroot"]);
        $this->assertTrue($data["confirmation_required"]);
        $this->assertSame(["moodle_wwwroot" => $wwwroot], discovery::site_meta());
    }

    /**
     * Tool descriptions must identify the Moodle site, even with multiple connections.
     *
     * @return void
     */
    public function test_tools_list_descriptions_include_wwwroot(): void {
        global $CFG;
        $identity = new authenticated_identity(2, "test", ["mcp:read"]);
        $method = new ReflectionMethod(mcp_server::class, "tool_definitions");
        $tools = $method->invoke(new mcp_server("read"), $identity);
        $this->assertNotEmpty($tools);
        foreach ($tools as $tool) {
            $this->assertStringContainsString("Moodle wwwroot: " . rtrim((string)$CFG->wwwroot, "/"),
                $tool["description"]);
        }
    }

    /**
     * Legacy clients must keep working unchanged.
     *
     * @return void
     */
    public function test_initialize_legacy_compatibility(): void {
        $result = discovery::initialize_result('server', '1.3.1');
        $this->assertSame('2025-06-18', $result['protocolVersion']);
        $this->assertSame('1.3.1', $result['serverInfo']['version']);
        $this->assertArrayHasKey('tools', $result['capabilities']);
        $this->assertArrayNotHasKey('resultType', $result);
    }

    /**
     * test_detects_modern_request_from_client_meta
     *
     * @return void
     */
    public function test_detects_modern_request_from_client_meta(): void {
        $params = [
            '_meta' => [
                'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                'io.modelcontextprotocol/clientInfo' => [
                    'name' => 'openai-mcp',
                    'version' => '1.0.0',
                ],
                'io.modelcontextprotocol/clientCapabilities' => ['experimental' => []],
            ],
        ];
        $this->assertTrue(discovery::is_modern($params));
        $this->assertTrue(discovery::is_modern([], '2026-07-28'));
        $this->assertFalse(discovery::is_modern([]));
        $this->assertFalse(discovery::is_modern([
            '_meta' => ['io.modelcontextprotocol/protocolVersion' => '2025-06-18'],
        ]));
    }

    /**
     * test_modern_tools_list_has_private_cache_fields
     *
     * @return void
     */
    public function test_modern_tools_list_has_private_cache_fields(): void {
        $tools = [['name' => 'get_course']];
        $result = discovery::decorate_result(['tools' => $tools], true, true);
        $this->assertSame('complete', $result['resultType']);
        $this->assertSame('private', $result['cacheScope']);
        $this->assertSame(discovery::CACHE_TTL_MS, $result['ttlMs']);
        $this->assertSame($tools, $result['tools']);
        $legacy = discovery::decorate_result(['tools' => $tools], false, true);
        $this->assertSame(['tools' => $tools], $legacy);
    }

    /**
     * test_tool_calls_are_complete_without_cache_hints
     *
     * @return void
     */
    public function test_tool_calls_are_complete_without_cache_hints(): void {
        $result = discovery::decorate_result([
            'content' => [['type' => 'text', 'text' => 'OK']],
            'isError' => false,
        ], true);
        $this->assertSame('complete', $result['resultType']);
        $this->assertArrayNotHasKey('ttlMs', $result);
        $this->assertArrayNotHasKey('cacheScope', $result);
    }
}
