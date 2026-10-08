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
 * Confirm WRITE tools return a preview before any Moodle mutation.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\protocol\mcp_server;
use local_mcp\security\authenticated_identity;
use ReflectionMethod;

defined('MOODLE_INTERNAL') || die();

/**
 * Test the two-phase MCP WRITE flow with a real course category tool.
 */
final class confirmation_flow_test extends advanced_testcase {
    /**
     * The first call may issue a token, but must not modify Moodle.
     *
     * @return void
     */
    public function test_write_requires_explicit_second_call_with_bound_token(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $identity = new authenticated_identity((int)get_admin()->id, 'test', ['mcp:write']);
        $server = new mcp_server('write');
        $method = new ReflectionMethod(mcp_server::class, 'call_tool');
        $args = ['name' => 'MCP Pending Approval Test', 'parentid' => 0];
        $access = 'test-access-token';

        $pending = $method->invoke($server, 'create_category', $args, [], $identity, $access);
        $this->assertTrue($pending['confirmation_required']);
        $this->assertSame('confirmation_pending', $pending['status']);
        $this->assertSame('create_category', $pending['tool']);
        $this->assertSame('MCP Pending Approval Test', $pending['preview']['name']);
        $this->assertStringStartsWith('mcp_confirm_', $pending['confirmation_token']);
        $this->assertFalse($DB->record_exists('course_categories', [
            'name' => $args['name'], 'parent' => 0,
        ]));

        // Simulate user approval followed by the second authenticated tool call.
        $confirmed = $args + ['confirmation_token' => $pending['confirmation_token']];
        $created = $method->invoke($server, 'create_category', $confirmed, [], $identity, $access);
        $this->assertSame('MCP Pending Approval Test', $created['name']);
        $this->assertTrue($DB->record_exists('course_categories', ['id' => $created['id']]));
    }
}
