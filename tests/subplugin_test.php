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
 * Moodle MCP activity subplugin contracts.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use coding_exception;
use core_component;
use local_mcp\extension\manager;
use local_mcp\extension\read_provider_interface;
use local_mcp\extension\write_provider_interface;
use local_mcp\write\registry;
use local_mcp\write\tool_interface;

/**
 * Subplugins are visible to Moodle and exposed without core activity switches.
 *
 * @covers \local_mcp\extension\manager
 * @covers \local_mcp\write\registry
 */
final class subplugin_test extends advanced_testcase {
    /**
     * test_activity_subplugins_are_discovered
     *
     * @return void
     */
    public function test_activity_subplugins_are_discovered(): void {
        $names = core_component::get_plugin_list('mcptool');
        foreach (['forum', 'page', 'book'] as $name) {
            $this->assertArrayHasKey($name, $names);
            $class = '\\mcptool_' . $name . '\\provider';
            $this->assertTrue(class_exists($class));
            $provider = new $class();
            $this->assertInstanceOf(
                read_provider_interface::class, $provider);
            $this->assertInstanceOf(
                write_provider_interface::class, $provider);
        }
    }

    /**
     * test_mcp_registry_includes_activity_tools
     *
     * @return void
     * @throws coding_exception
     */
    public function test_mcp_registry_includes_activity_tools(): void {
        $read = read\registry::get_tools();
        $write = registry::get_tools();

        foreach (['forum_list_discussions', 'forum_get_posts',
                     'page_get_content', 'book_list_chapters', 'book_get_chapter'] as $name) {
            $this->assertArrayHasKey($name, $read);
            $this->assertArrayNotHasKey($name, $write);
        }
        foreach (['forum_create_activity', 'page_create_activity', 'book_create_activity',
                     'forum_create_discussion', 'forum_reply_to_post',
                     'page_update_content', 'book_create_chapter', 'book_update_chapter'] as $name) {
            $this->assertArrayHasKey($name, $write);
            $this->assertArrayNotHasKey($name, $read);
            $this->assertTrue($write[$name]->requires_confirmation());
        }
    }

    /**
     * test_no_activity_tool_requires_plugin_controller_special_cases
     *
     * @return void
     */
    public function test_no_activity_tool_requires_plugin_controller_special_cases(): void {
        foreach (manager::read_providers() as $provider) {
            foreach ($provider->get_read_tools() as $tool) {
                $this->assertInstanceOf(read\tool_interface::class, $tool);
            }
        }
        foreach (manager::write_providers() as $provider) {
            foreach ($provider->get_write_tools() as $tool) {
                $this->assertInstanceOf(tool_interface::class, $tool);
            }
        }
    }
    /**
     * Class-based external plugins must expose both tool registries and the approval flow.
     *
     * @return void
     */
    public function test_external_class_provider_read_write_and_confirmation_contracts(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $readtool = new class extends \local_mcp\read\tool\base_tool {
            /**
             * Return the unique MCP identifier.
             *
             * @return string
             */
            public function get_name(): string {
                return "test_external_provider_read";
            }

            /**
             * Return the tool title.
             *
             * @return string
             */
            public function get_title(): string {
                return "External provider read";
            }

            /**
             * Return the tool description.
             *
             * @return string
             */
            public function get_description(): string {
                return "Read fixture data.";
            }

            /**
             * Return the accepted JSON input schema.
             *
             * @return array
             */
            public function get_input_schema(): array {
                return $this->object_schema(["value" => ["type" => "string"]], ["value"]);
            }

            /**
             * Return the Moodle permission needed to call the tool.
             *
             * @return string
             */
            public function get_required_capability(): string {
                return "moodle/site:config";
            }

            /**
             * Resolve the protected Moodle context.
             *
             * @param array $arguments Tool arguments.
             * @return \context
             */
            public function resolve_context(array $arguments): \context {
                return \context_system::instance();
            }

            /**
             * Return fixture content without changing any database records.
             *
             * @param array $arguments Tool arguments.
             * @param \local_mcp\security\authenticated_identity $identity Connected user.
             * @return array
             */
            public function execute(array $arguments,
                    \local_mcp\security\authenticated_identity $identity): array {
                return ["value" => $arguments["value"], "userid" => $identity->userid];
            }
        };

        $writetool = new class extends \local_mcp\write\tool\base_tool {
            /** Number of committed writes by this fixture. */
            public int $writes = 0;

            /**
             * Return the unique MCP identifier.
             *
             * @return string
             */
            public function get_name(): string {
                return "test_external_provider_write";
            }

            /**
             * Return the tool title.
             *
             * @return string
             */
            public function get_title(): string {
                return "External provider write";
            }

            /**
             * Return the tool description.
             *
             * @return string
             */
            public function get_description(): string {
                return "Execute a confirmed fixture write.";
            }

            /**
             * Return the accepted JSON input schema.
             *
             * @return array
             */
            public function get_input_schema(): array {
                return $this->object_schema(["value" => ["type" => "string"]], ["value"]);
            }

            /**
             * Return the Moodle permission needed to call the tool.
             *
             * @return string
             */
            public function get_required_capability(): string {
                return "moodle/site:config";
            }

            /**
             * Resolve the protected Moodle context.
             *
             * @param array $arguments Tool arguments.
             * @return \context
             */
            public function resolve_context(array $arguments): \context {
                return \context_system::instance();
            }

            /**
             * Describe the requested change without executing it.
             *
             * @param array $arguments Tool arguments.
             * @param \local_mcp\security\authenticated_identity $identity Connected user.
             * @return array
             */
            public function preview(array $arguments,
                    \local_mcp\security\authenticated_identity $identity): array {
                return ["value" => $arguments["value"], "will_write" => true];
            }

            /**
             * Count an approved write operation.
             *
             * @param array $arguments Tool arguments.
             * @param \local_mcp\security\authenticated_identity $identity Connected user.
             * @return array
             */
            public function execute(array $arguments,
                    \local_mcp\security\authenticated_identity $identity): array {
                $this->writes++;
                return ["value" => $arguments["value"], "writes" => $this->writes];
            }
        };

        $provider = new class ($readtool, $writetool) implements
            read_provider_interface,
            write_provider_interface
        {
            /**
             * Set up the class-based provider.
             *
             * @param \local_mcp\read\tool_interface $readtool Read tool.
             * @param \local_mcp\write\tool_interface $writetool Write tool.
             */
            public function __construct(
                    private \local_mcp\read\tool_interface $readtool,
                    private \local_mcp\write\tool_interface $writetool) {
            }

            /**
             * Return tools supporting the READ contract.
             *
             * @return array
             */
            public function get_read_tools(): array {
                return [$this->readtool];
            }

            /**
             * Return tools supporting the WRITE contract.
             *
             * @return array
             */
            public function get_write_tools(): array {
                return [$this->writetool];
            }
        };

        // Supply a class-discovered fixture without installing a dummy Moodle plugin.
        $property = new \ReflectionProperty(manager::class, "classproviders");
        $previous = $property->getValue();
        $property->setValue(null, ["test_external_provider" => $provider]);
        try {
            $readtools = read\registry::get_tools();
            $writetools = registry::get_tools();
            $this->assertSame($readtool, $readtools["test_external_provider_read"]);
            $this->assertSame($writetool, $writetools["test_external_provider_write"]);
            $this->assertArrayNotHasKey("test_external_provider_write", $readtools);
            $this->assertArrayNotHasKey("test_external_provider_read", $writetools);
            $this->assertTrue($writetool->requires_confirmation());

            $identity = new \local_mcp\security\authenticated_identity(
                (int)get_admin()->id, "test", ["mcp:read", "mcp:write"]
            );
            $method = new \ReflectionMethod(\local_mcp\protocol\mcp_server::class, "call_tool");

            $read = $method->invoke(new \local_mcp\protocol\mcp_server("read"),
                "test_external_provider_read", ["value" => "hello"], [], $identity, "test-access");
            $this->assertSame("hello", $read["value"]);

            $server = new \local_mcp\protocol\mcp_server("write");
            $args = ["value" => "ready"];
            $pending = $method->invoke($server, "test_external_provider_write",
                $args, [], $identity, "test-access");
            $this->assertTrue($pending["confirmation_required"]);
            $this->assertSame(0, $writetool->writes);
            $this->assertSame("ready", $pending["preview"]["value"]);

            $confirmed = $method->invoke($server, "test_external_provider_write",
                $args + ["confirmation_token" => $pending["confirmation_token"]],
                [], $identity, "test-access");
            $this->assertSame(1, $confirmed["writes"]);
            $this->assertSame(1, $writetool->writes);
        } finally {
            $property->setValue(null, $previous);
        }
    }


}
