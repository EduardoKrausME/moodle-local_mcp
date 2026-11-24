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
 * Regression tests for isolated tool providers and registry failures.
 *
 * @package local_mcp
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\exception\api_exception;
use local_mcp\extension\read_provider_interface;
use local_mcp\extension\tool_provider_loader;
use local_mcp\protocol\mcp_server;
use local_mcp\read\tool_interface;
use local_mcp\security\authenticated_identity;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use stdClass;

/**
 * Exercise isolated registration without changing access-policy semantics.
 *
 * @covers \local_mcp\extension\tool_provider_loader
 * @covers \local_mcp\protocol\mcp_server
 */
final class tool_provider_loader_test extends advanced_testcase {
    /**
     * Broken constructors, missing classes and invalid interfaces are excluded.
     */
    public function test_provider_load_isolation(): void {
        $broken = static function() {
            throw new RuntimeException('private debug detail');
        };
        $this->assertNull(tool_provider_loader::load($broken, read_provider_interface::class, 'local_test', 'broken'));
        $this->assertNull(tool_provider_loader::load_class('\local_test\missing_provider', 'local_test'));
        $this->assertNull(tool_provider_loader::load(static function() {
            return new stdClass();
        }, read_provider_interface::class, 'local_test', 'invalid'));
        $valid = $this->createMock(read_provider_interface::class);
        $this->assertSame($valid, tool_provider_loader::load(static function() use ($valid) {
            return $valid;
        }, read_provider_interface::class, 'local_test', 'valid'));
    }

    /**
     * A malformed entry or throwing provider must not discard independent tools.
     */
    public function test_collection_and_duplicate_isolation(): void {
        $broken = $this->createMock(read_provider_interface::class);
        $broken->method('get_read_tools')->willThrowException(new RuntimeException('private'));
        $good = $this->createMock(read_provider_interface::class);
        $duplicate = $this->createMock(tool_interface::class);
        $duplicate->method('get_name')->willReturn('duplicate');
        $valid = $this->createMock(tool_interface::class);
        $valid->method('get_name')->willReturn('valid');
        $badname = $this->createMock(tool_interface::class);
        $badname->method('get_name')->willThrowException(new RuntimeException('bad name'));
        $good->method('get_read_tools')->willReturn([new stdClass(), $badname, $duplicate, $duplicate, $valid]);
        $conflicts = [];
        $result = tool_provider_loader::collect(['core' => $valid], [$broken, $good],
            'get_read_tools', tool_interface::class, $conflicts);
        $this->assertSame(['core' => $valid, 'valid' => $valid], $result);
        $this->assertSame(['duplicate' => true], $conflicts);
        $this->assertSame(['core' => $valid], tool_provider_loader::merge(
            $result, ['valid' => $duplicate, 'duplicate' => $duplicate], $conflicts));
    }

    /**
     * The server reuses its completed registry and isolates failing tool definitions.
     */
    public function test_registry_snapshot_and_definition_isolation(): void {
        $server = new mcp_server('read');
        $registry = new ReflectionMethod(mcp_server::class, 'get_tools');
        $registry->setAccessible(true);
        $first = $registry->invoke($server);
        $this->assertArrayHasKey('get_course', $first);
        $this->assertSame($first['get_course'], $registry->invoke($server)['get_course']);

        $broken = $this->createMock(tool_interface::class);
        $broken->method('get_name')->willReturn('broken');
        $broken->method('get_input_schema')->willThrowException(new RuntimeException('private'));
        $snapshot = new ReflectionProperty(mcp_server::class, 'tools');
        $snapshot->setAccessible(true);
        $snapshot->setValue($server, ['broken' => $broken, 'get_course' => $first['get_course']]);
        $list = new ReflectionMethod(mcp_server::class, 'tool_definitions');
        $list->setAccessible(true);
        $definitions = $list->invoke($server, new authenticated_identity(2, 'test', ['mcp:read']));
        $this->assertSame(['get_course'], array_column($definitions, 'name'));
    }

    /**
     * A missing WRITE scope is rejected before any tool metadata or execution.
     *
     * @return void
     */
    public function test_write_scope_is_checked_before_provider_methods(): void {
        $server = new mcp_server('server');
        $tool = $this->createMock(\local_mcp\write\tool_interface::class);
        $tool->expects($this->never())->method('is_destructive');
        $tool->expects($this->never())->method('get_input_schema');
        $tool->expects($this->never())->method('preview');
        $tool->expects($this->never())->method('execute');
        $snapshot = new ReflectionProperty(mcp_server::class, 'tools');
        $snapshot->setAccessible(true);
        $snapshot->setValue($server, ['blocked_write' => $tool]);
        $call = new ReflectionMethod(mcp_server::class, 'call_tool');
        $call->setAccessible(true);
        try {
            $call->invoke($server, 'blocked_write', [], [],
                new authenticated_identity(2, 'test', ['mcp:read']), '');
            $this->fail('A READ identity must not invoke a WRITE tool.');
        } catch (api_exception $failure) {
            $this->assertSame('insufficient_scope', $failure->machinecode);
            $this->assertSame(403, $failure->httpstatus);
        }
    }

    /**
     * Failure auditing does not initiate discovery when no registry exists.
     */
    public function test_failed_call_does_not_reload_registry(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $server = new mcp_server('server');
        $method = new ReflectionMethod(mcp_server::class, 'record_failed_call');
        $method->setAccessible(true);
        $method->invoke($server, new authenticated_identity(2, 'test', ['mcp:read']),
            'get_course', ['arguments' => ['courseid' => 2]], 'internal_error', microtime(true));
        $snapshot = new ReflectionProperty(mcp_server::class, 'tools');
        $snapshot->setAccessible(true);
        $this->assertNull($snapshot->getValue($server));
        $this->assertTrue($DB->record_exists('local_mcp_audit', ['event' => 'tool_failed', 'tool' => 'get_course']));
    }
}
