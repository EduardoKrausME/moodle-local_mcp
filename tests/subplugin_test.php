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
use core_component;
use local_mcp\extension\manager;
use local_mcp\extension\read_provider_interface;
use local_mcp\extension\write_provider_interface;
use local_mcp\write\registry;
use local_mcp\write\tool_interface;

defined('MOODLE_INTERNAL') || die();

/**
 * Subplugins are visible to Moodle and exposed without core activity switches.
 */
final class subplugin_test extends advanced_testcase {
    /**
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
     * @return void
     */
    public function test_mcp_registry_includes_activity_tools(): void {
        $read = \local_mcp\read\registry::get_tools();
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
     * @return void
     */
    public function test_no_activity_tool_requires_plugin_controller_special_cases(): void {
        foreach (manager::read_providers() as $provider) {
            foreach ($provider->get_read_tools() as $tool) {
                $this->assertInstanceOf(\local_mcp\read\tool_interface::class, $tool);
            }
        }
        foreach (manager::write_providers() as $provider) {
            foreach ($provider->get_write_tools() as $tool) {
                $this->assertInstanceOf(tool_interface::class, $tool);
            }
        }
    }
}
