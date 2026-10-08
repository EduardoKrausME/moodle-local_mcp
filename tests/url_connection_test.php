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
 * url_connection_test.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use context_system;
use local_mcp\exception\api_exception;
use local_mcp\protocol\http;
use local_mcp\security\manual_token_service;
use local_mcp\security\scope;
use local_mcp\security\secret;
use local_mcp\security\token_resolver;

defined('MOODLE_INTERNAL') || die();

/**
 * Test temporary URL-token authentication without OAuth.
 */
final class url_connection_test extends advanced_testcase {
    /**
     * @return void
     */
    public function test_query_token_authenticates_and_can_be_revoked(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $created = manual_token_service::create('ChatGPT (teste) - PHPUnit',
            (int)$user->id, true, true, time() + DAYSECS);
        $oldget = $_GET;
        $oldheader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        try {
            unset($_SERVER['HTTP_AUTHORIZATION']);
            $_GET = ['toke' => $created['token']];

            [$authorization, $token] = http::bearer(true);
            $this->assertSame('Bearer ' . $created['token'], $authorization);
            $this->assertSame($created['token'], $token);

            $identity = token_resolver::from_bearer($authorization);
            $this->assertSame((int)$user->id, $identity->userid);
            $this->assertSame('manual', $identity->type);
            $this->assertTrue($identity->has_scope(scope::READ));
            $this->assertTrue($identity->has_scope(scope::WRITE));

            manual_token_service::revoke((int)$created['id']);
            $this->expectException(api_exception::class);
            token_resolver::from_bearer($authorization);
        } finally {
            $_GET = $oldget;
            if ($oldheader === null) {
                unset($_SERVER['HTTP_AUTHORIZATION']);
            } else {
                $_SERVER['HTTP_AUTHORIZATION'] = $oldheader;
            }
        }
    }

    /**
     * @return void
     */
    public function test_query_token_must_be_explicitly_allowed(): void {
        $oldget = $_GET;
        $oldheader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        try {
            $_GET = ['toke' => secret::generate('mcp_', 40)];
            unset($_SERVER['HTTP_AUTHORIZATION']);

            $this->expectException(api_exception::class);
            http::bearer(false);
        } finally {
            $_GET = $oldget;
            if ($oldheader === null) {
                unset($_SERVER['HTTP_AUTHORIZATION']);
            } else {
                $_SERVER['HTTP_AUTHORIZATION'] = $oldheader;
            }
        }
    }

    /**
     * @return void
     */
    public function test_query_token_rejects_oauth_access_tokens(): void {
        $oldget = $_GET;
        $oldheader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        try {
            $_GET = ['toke' => secret::generate('mcp_at_', 40)];
            unset($_SERVER['HTTP_AUTHORIZATION']);
            $this->expectException(api_exception::class);
            http::bearer(true);
        } finally {
            $_GET = $oldget;
            if ($oldheader === null) {
                unset($_SERVER['HTTP_AUTHORIZATION']);
            } else {
                $_SERVER['HTTP_AUTHORIZATION'] = $oldheader;
            }
        }
    }

    /**
     * @return void
     */
    public function test_query_token_rejects_ambiguous_authorization(): void {
        $oldget = $_GET;
        $oldheader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        try {
            $_GET = ['toke' => secret::generate('mcp_', 40)];
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . secret::generate('mcp_', 40);
            $this->expectException(api_exception::class);
            http::bearer(true);
        } finally {
            $_GET = $oldget;
            if ($oldheader === null) {
                unset($_SERVER['HTTP_AUTHORIZATION']);
            } else {
                $_SERVER['HTTP_AUTHORIZATION'] = $oldheader;
            }
        }
    }
    /**
     * Existing test URLs retain the same token while READ/WRITE are edited.
     *
     * @return void
     */
    public function test_update_permissions_keeps_existing_token_and_takes_effect_immediately(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $created = manual_token_service::create('ChatGPT (teste) - Update permissions',
            (int)$user->id, true, false, time() + DAYSECS);
        $authorization = 'Bearer ' . $created['token'];

        $before = token_resolver::from_bearer($authorization);
        $this->assertTrue($before->has_scope(scope::READ));
        $this->assertFalse($before->has_scope(scope::WRITE));

        manual_token_service::update_permissions((int)$created['id'], true, true);
        $after = token_resolver::from_bearer($authorization);
        $this->assertTrue($after->has_scope(scope::READ));
        $this->assertTrue($after->has_scope(scope::WRITE));
        $this->assertSame($before->tokenid, $after->tokenid);
        $record = $DB->get_record('local_mcp_manual_token', ['id' => $created['id']], '*', MUST_EXIST);
        $this->assertSame(secret::hash($created['token']), $record->tokenhash);

        manual_token_service::update_permissions((int)$created['id'], false, true);
        $writeonly = token_resolver::from_bearer($authorization);
        $this->assertFalse($writeonly->has_scope(scope::READ));
        $this->assertTrue($writeonly->has_scope(scope::WRITE));

        manual_token_service::update_permissions((int)$created['id'], true, false);
        $readonly = token_resolver::from_bearer($authorization);
        $this->assertTrue($readonly->has_scope(scope::READ));
        $this->assertFalse($readonly->has_scope(scope::WRITE));
    }

    /**
     * @return void
     */
    public function test_update_permissions_rejects_zero_permissions(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $created = manual_token_service::create('ChatGPT (teste) - Invalid permissions',
            (int)$user->id, true, false, time() + DAYSECS);
        $this->expectException(api_exception::class);
        manual_token_service::update_permissions((int)$created['id'], false, false);
    }

    /**
     * @return void
     */
    public function test_update_permissions_rejects_revoked_tokens(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $created = manual_token_service::create('ChatGPT (teste) - Revoked token',
            (int)$user->id, true, false, time() + DAYSECS);
        manual_token_service::revoke((int)$created['id']);
        $this->expectException(api_exception::class);
        manual_token_service::update_permissions((int)$created['id'], true, true);
    }

    /**
     * @return void
     */
    public function test_update_permissions_clears_outstanding_write_confirmations(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $created = manual_token_service::create('ChatGPT (teste) - Confirmation',
            (int)$user->id, true, true, time() + DAYSECS);
        $DB->insert_record('local_mcp_confirm', (object)[
            'prefix' => 'mcp_confirm_test',
            'tokenhash' => hash('sha256', 'testconfirmation'),
            'userid' => (int)$user->id,
            'clientid' => null,
            'connectionid' => null,
            'accesshash' => secret::hash($created['token']),
            'tool' => 'test_tool',
            'argshash' => hash('sha256', '{}'),
            'contextid' => context_system::instance()->id,
            'expires' => time() + 300,
            'used' => 0,
            'timecreated' => time(),
        ]);
        $this->assertEquals(1, $DB->count_records('local_mcp_confirm'));
        manual_token_service::update_permissions((int)$created['id'], true, false);
        $this->assertEquals(0, $DB->count_records('local_mcp_confirm'));
    }

}
