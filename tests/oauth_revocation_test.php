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
 * OAuth connection and client revocation tests.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\exception\api_exception;
use local_mcp\oauth\authorization_service;
use local_mcp\oauth\client_service;
use local_mcp\oauth\connection_service;
use local_mcp\oauth\resource;
use local_mcp\oauth\token_service;
use local_mcp\security\scope;
use local_mcp\security\secret;
use local_mcp\security\token_resolver;

/**
 * Revoked grants must reject previously issued access and refresh tokens.
 *
 * @covers \local_mcp\oauth\connection_service
 * @covers \local_mcp\oauth\client_service
 */
final class oauth_revocation_test extends advanced_testcase {
    /**
     * Return the test client's database ID, connection ID, resource and credentials.
     *
     * @return array
     */
    private function create_connection(): array {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $redirecturi = "https://example.org/oauth/callback";
        $registration = client_service::register_public([
            "client_name" => "Revocation test",
            "redirect_uris" => [$redirecturi],
        ]);
        $client = client_service::by_clientid($registration["client_id"]);
        $verifier = str_repeat("a", 43);
        $challenge = secret::base64url(hash("sha256", $verifier, true));
        $audience = resource::endpoint("server");
        $code = authorization_service::issue_code(
            $client, $user->id, $redirecturi, [scope::READ], $challenge, $audience
        );
        $tokens = token_service::exchange_code(
            $registration["client_id"], $code, $redirecturi, $verifier, $audience
        );
        $access = $DB->get_record("local_mcp_access_token",
            ["tokenhash" => secret::hash($tokens["access_token"])], "*", MUST_EXIST);
        return [$client->id, (int)$access->connectionid, $audience, $tokens, $registration["client_id"]];
    }

    /**
     * Revoking a single connection invalidates both OAuth token types.
     *
     * @return void
     */
    public function test_revoke_connection_invalidates_tokens(): void {
        global $DB;

        $this->resetAfterTest();
        [$clientid, $connectionid, $audience, $tokens, $publicid] = $this->create_connection();
        $identity = token_resolver::from_bearer("Bearer " . $tokens["access_token"], $audience);
        $this->assertSame($connectionid, $identity->connectionid);

        connection_service::revoke($connectionid);
        $this->assertEquals(0, $DB->get_field("local_mcp_connection", "enabled", ["id" => $connectionid]));
        $this->assertNotEmpty($DB->get_field("local_mcp_access_token", "revokedat",
            ["tokenhash" => secret::hash($tokens["access_token"])]));
        $this->assertNotEmpty($DB->get_field("local_mcp_refresh_token", "revokedat",
            ["tokenhash" => secret::hash($tokens["refresh_token"])]));
        $this->assertEquals(1, $DB->get_field("local_mcp_oauth_client", "enabled", ["id" => $clientid]));

        try {
            token_resolver::from_bearer("Bearer " . $tokens["access_token"], $audience);
            $this->fail("Revoked access token was accepted.");
        } catch (api_exception $e) {
            $this->assertSame(401, $e->httpstatus);
        }
        try {
            token_service::refresh($publicid, $tokens["refresh_token"]);
            $this->fail("Revoked refresh token was accepted.");
        } catch (api_exception $e) {
            $this->assertSame("invalid_grant", $e->machinecode);
        }
    }

    /**
     * Disabling an OAuth client also revokes all its active grants.
     *
     * @return void
     */
    public function test_disable_client_invalidates_existing_grants(): void {
        global $DB;

        $this->resetAfterTest();
        [$clientid, $connectionid, $audience, $tokens] = $this->create_connection();
        client_service::disable((int)$clientid);
        $this->assertEquals(0, $DB->get_field("local_mcp_oauth_client", "enabled", ["id" => $clientid]));
        $this->assertEquals(0, $DB->get_field("local_mcp_connection", "enabled", ["id" => $connectionid]));
        $this->assertNotEmpty($DB->get_field("local_mcp_access_token", "revokedat",
            ["tokenhash" => secret::hash($tokens["access_token"])]));
        $this->assertNotEmpty($DB->get_field("local_mcp_refresh_token", "revokedat",
            ["tokenhash" => secret::hash($tokens["refresh_token"])]));
        $this->expectException(api_exception::class);
        token_resolver::from_bearer("Bearer " . $tokens["access_token"], $audience);
    }
}
