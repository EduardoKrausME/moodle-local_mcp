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
 * Password policy and MCP password persistence regression tests.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\exception\api_exception;
use local_mcp\protocol\mcp_server;
use local_mcp\read\tool\get_password_policy;
use local_mcp\security\authenticated_identity;
use local_mcp\security\password_policy;
use local_mcp\write\tool\create_user;
use local_mcp\write\tool\update_user;
use ReflectionMethod;

/**
 * Password changes must validate Moodle policy and prove persistence.
 *
 * @covers \local_mcp\security\password_policy
 * @covers \local_mcp\read\tool\get_password_policy
 * @covers \local_mcp\write\tool\create_user
 * @covers \local_mcp\write\tool\update_user
 */
final class password_policy_tools_test extends advanced_testcase {
    /**
     * Set an explicit policy to avoid relying on installation defaults.
     *
     * @return void
     */
    private function configure_policy(): void {
        set_config('passwordpolicy', 1);
        set_config('minpasswordlength', 12);
        set_config('minpassworddigits', 2);
        set_config('minpasswordlower', 2);
        set_config('minpasswordupper', 1);
        set_config('minpasswordnonalphanum', 1);
    }

    /**
     * @return authenticated_identity
     */
    private function identity(): authenticated_identity {
        $this->setAdminUser();
        return new authenticated_identity((int)get_admin()->id, 'test', ['mcp:read', 'mcp:write']);
    }

    /**
     * The client must receive site requirements, not assumed defaults.
     */
    public function test_read_policy_tracks_moodle_configuration(): void {
        $this->resetAfterTest();
        $this->configure_policy();
        $result = (new get_password_policy())->execute([], $this->identity());
        $policy = $result['password_policy'];
        $this->assertTrue($policy['enabled']);
        $this->assertSame(12, $policy['minimum_length']);
        $this->assertSame(2, $policy['minimum_digits']);
        $this->assertSame(2, $policy['minimum_lowercase']);
        $this->assertSame(1, $policy['minimum_uppercase']);
        $this->assertSame(1, $policy['minimum_special_characters']);
        $this->assertSame('moodle/user:update', (new get_password_policy())->get_required_capability());
        $this->assertSame($policy, password_policy::current());
    }

    /**
     * Bad passwords fail with actionable requirements before a write or preview token.
     */
    public function test_invalid_password_reports_violation_without_leaking_secret(): void {
        $this->resetAfterTest();
        $identity = $this->identity();
        $user = $this->getDataGenerator()->create_user(['auth' => 'manual']);
        $this->configure_policy();
        $invalid = 'MissingSpecial1A';
        $before = $user->password;
        $tool = new update_user();

        try {
            $tool->preview(['userid' => (int)$user->id, 'password' => $invalid], $identity);
            $this->fail('Password without a special character must be rejected.');
        } catch (api_exception $e) {
            $this->assertSame('password_policy_violation', $e->machinecode);
            $this->assertSame(400, $e->httpstatus);
            $this->assertSame(1, $e->details['policy']['minimum_special_characters']);
            $this->assertNotEmpty($e->details['violations']);
            $this->assertStringNotContainsString($invalid, json_encode($e->details));
        }
        $stored = $GLOBALS['DB']->get_field('user', 'password', ['id' => $user->id]);
        $this->assertSame($before, $stored);
    }

    /**
     * The two-phase protocol should never expose the password in a preview/result.
     */
    public function test_confirmed_update_persists_and_verifies_password(): void {
        global $DB;
        $this->resetAfterTest();
        $identity = $this->identity();
        $user = $this->getDataGenerator()->create_user(['auth' => 'manual']);
        $this->configure_policy();
        $secret = 'Strong!Abcd12—safe';
        $server = new mcp_server('write');
        $call = new ReflectionMethod(mcp_server::class, 'call_tool');
        $args = ['userid' => (int)$user->id, 'password' => $secret];
        $pending = $call->invoke($server, 'update_user', $args, [], $identity, 'test-access-password');
        $this->assertTrue($pending['confirmation_required']);
        $this->assertTrue($pending['preview']['password_valid']);
        $this->assertStringNotContainsString($secret, json_encode($pending));
        $this->assertFalse(validate_internal_user_password(
            $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST), $secret));

        $confirmed = $args + ['confirmation_token' => $pending['confirmation_token']];
        $result = $call->invoke($server, 'update_user', $confirmed,
            [], $identity, 'test-access-password');
        $this->assertTrue($result['updated']);
        $this->assertTrue($result['password_changed']);
        $this->assertTrue($result['password_verified']);
        $this->assertStringNotContainsString($secret, json_encode($result));
        $this->assertTrue(validate_internal_user_password(
            $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST), $secret));
        $this->assertFalse(validate_internal_user_password(
            $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST), 'Strong!Abcd12-safe'));
    }

    /**
     * External authentication accounts may not receive a local password by accident.
     */
    public function test_external_authentication_rejects_password_change(): void {
        global $DB;
        $this->resetAfterTest();
        $identity = $this->identity();
        $user = $this->getDataGenerator()->create_user(['auth' => 'manual']);
        $DB->set_field('user', 'auth', 'ldap', ['id' => $user->id]);
        $this->configure_policy();
        $this->expectException(api_exception::class);
        $this->expectExceptionMessage('Passwords for this authentication method');
        (new update_user())->preview(['userid' => (int)$user->id,
            'password' => 'Valid!Pass123456'], $identity);
    }

    /**
     * An initial password is accepted only when the core API hashes and stores it.
     */
    public function test_create_user_with_password_hashes_and_verifies(): void {
        global $DB;
        $this->resetAfterTest();
        $identity = $this->identity();
        $this->configure_policy();
        $args = [
            'username' => 'mcppasswordtest',
            'firstname' => 'Password',
            'lastname' => 'Test',
            'email' => 'mcppasswordtest@example.com',
            'auth' => 'manual',
            'password' => 'Create!Strong1234',
        ];
        $tool = new create_user();
        $preview = $tool->preview($args, $identity);
        $this->assertTrue($preview['password_valid']);
        $this->assertStringNotContainsString($args['password'], json_encode($preview));
        $result = $tool->execute($args, $identity);
        $this->assertTrue($result['password_set']);
        $this->assertStringNotContainsString($args['password'], json_encode($result));
        $saved = $DB->get_record('user', ['id' => $result['id']], '*', MUST_EXIST);
        $this->assertTrue(validate_internal_user_password($saved, $args['password']));
        $this->assertNotSame($args['password'], $saved->password);
    }
}
