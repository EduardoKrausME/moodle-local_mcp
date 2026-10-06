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
 * registry_test.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\read\tool_interface;
use local_mcp\security\authenticated_identity;
use local_mcp\write\registry;

defined('MOODLE_INTERNAL') || die;

/**
 * Class registry_test.
 */
final class registry_test extends advanced_testcase {
    /**
     * Method test_read_and_write_registries_are_structurally_separate.
     *
     * @return void Return value.
     */
    public function test_read_and_write_registries_are_structurally_separate(): void {
        $read = \local_mcp\read\registry::get_tools();
        $write = registry::get_tools();

        $this->assertNotEmpty($read);
        $this->assertNotEmpty($write);
        $this->assertEmpty(array_intersect(array_keys($read), array_keys($write)));

        foreach ($read as $tool) {
            $this->assertInstanceOf(tool_interface::class, $tool);
            $this->assertNotInstanceOf(\local_mcp\write\tool_interface::class, $tool);
        }
        foreach ($write as $tool) {
            $this->assertInstanceOf(\local_mcp\write\tool_interface::class, $tool);
            $this->assertNotInstanceOf(tool_interface::class, $tool);
        }
    }

    /**
     * Method test_expected_scopes_are_independent.
     *
     * @return void Return value.
     */
    public function test_expected_scopes_are_independent(): void {
        $read = new authenticated_identity(2, 'test', ['mcp:read']);
        $write = new authenticated_identity(2, 'test', ['mcp:write']);
        $this->assertTrue($read->has_scope('mcp:read'));
        $this->assertFalse($read->has_scope('mcp:write'));
        $this->assertTrue($write->has_scope('mcp:write'));
        $this->assertFalse($write->has_scope('mcp:read'));
    }
}
