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

/**
 * Test the two-phase MCP WRITE flow with a real course category tool.
 *
 * @covers \local_mcp\protocol\mcp_server
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
    /**
     * Invalid confirmation tokens must be reported as approval conflicts, not database errors.
     *
     * @return void
     */
    public function test_unknown_token_is_rejected_without_consuming_real_approval(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $identity = new authenticated_identity((int)get_admin()->id, "test", ["mcp:write"]);
        $server = new mcp_server("write");
        $method = new ReflectionMethod(mcp_server::class, "call_tool");
        $args = ["name" => "MCP Unknown Token Regression", "parentid" => 0];
        $access = "test-token-approval";

        $pending = $method->invoke($server, "create_category", $args, [], $identity, $access);
        $this->assertTrue($pending["confirmation_required"]);
        $this->assertFalse($DB->record_exists("course_categories", ["name" => $args["name"]]));

        try {
            $method->invoke($server, "create_category",
                $args + ["confirmation_token" => "mcp_confirm_nonexistent"],
                [], $identity, $access);
            $this->fail("An unknown confirmation token must be rejected.");
        } catch (\local_mcp\exception\api_exception $e) {
            $this->assertSame("confirmation_expired", $e->machinecode);
            $this->assertSame(409, $e->httpstatus);
        }
        $this->assertFalse($DB->record_exists("course_categories", ["name" => $args["name"]]));

        // A rejected token must not consume a separate, valid pending approval.
        $created = $method->invoke($server, "create_category",
            $args + ["confirmation_token" => $pending["confirmation_token"]],
            [], $identity, $access);
        $this->assertSame($args["name"], $created["name"]);
    }



    /**
     * Default WRITE confirmation tokens last thirty minutes, unless explicitly configured.
     *
     * @return void
     */
    public function test_confirmation_lifetime_default_and_override(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        unset_config("confirmationttl", "local_mcp");

        $identity = new authenticated_identity((int)get_admin()->id, "test", ["mcp:write"]);
        $context = \context_system::instance();
        $arguments = ["name" => "MCP Confirmation TTL", "parentid" => 0];
        $before = time();
        $token = \local_mcp\security\confirmation_service::issue(
            $identity, "test-access-token", "create_category", $arguments, $context
        );
        $record = $DB->get_record("local_mcp_confirm", [
            "tokenhash" => \local_mcp\security\secret::hash($token),
        ], "*", MUST_EXIST);

        $this->assertGreaterThanOrEqual($before + 1800, (int)$record->expires);
        $this->assertLessThanOrEqual(time() + 1800, (int)$record->expires);

        set_config("confirmationttl", 600, "local_mcp");
        $before = time();
        $token = \local_mcp\security\confirmation_service::issue(
            $identity, "test-access-token", "create_category", $arguments, $context
        );
        $record = $DB->get_record("local_mcp_confirm", [
            "tokenhash" => \local_mcp\security\secret::hash($token),
        ], "*", MUST_EXIST);

        $this->assertGreaterThanOrEqual($before + 600, (int)$record->expires);
        $this->assertLessThanOrEqual(time() + 600, (int)$record->expires);
    }

    /**
     * A token is valid after five minutes, but it cannot be consumed after expiry.
     *
     * @return void
     */
    public function test_confirmation_after_five_minutes_and_after_expiry(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config("confirmationttl", 1800, "local_mcp");

        $identity = new authenticated_identity((int)get_admin()->id, "test", ["mcp:write"]);
        $context = \context_system::instance();
        $arguments = ["name" => "MCP Delayed Confirmation", "parentid" => 0];
        $access = "test-access-token";

        $token = \local_mcp\security\confirmation_service::issue(
            $identity, $access, "create_category", $arguments, $context
        );
        $tokenhash = \local_mcp\security\secret::hash($token);
        // Simulate approval five minutes after the preview was generated.
        $DB->set_field("local_mcp_confirm", "timecreated", time() - 300, ["tokenhash" => $tokenhash]);
        $DB->set_field("local_mcp_confirm", "expires", time() + 1500, ["tokenhash" => $tokenhash]);

        \local_mcp\security\confirmation_service::consume(
            $token, $identity, $access, "create_category", $arguments, $context
        );
        $this->assertEquals(1, $DB->get_field("local_mcp_confirm", "used", ["tokenhash" => $tokenhash]));

        $expiredtoken = \local_mcp\security\confirmation_service::issue(
            $identity, $access, "create_category", $arguments, $context
        );
        $expiredhash = \local_mcp\security\secret::hash($expiredtoken);
        $DB->set_field("local_mcp_confirm", "expires", time() - 1, ["tokenhash" => $expiredhash]);

        try {
            \local_mcp\security\confirmation_service::consume(
                $expiredtoken, $identity, $access, "create_category", $arguments, $context
            );
            $this->fail("An expired confirmation must be rejected.");
        } catch (\local_mcp\exception\api_exception $e) {
            $this->assertSame("confirmation_expired", $e->machinecode);
            $this->assertSame(409, $e->httpstatus);
        }
        $this->assertEquals(0, $DB->get_field("local_mcp_confirm", "used", ["tokenhash" => $expiredhash]));
    }

}
