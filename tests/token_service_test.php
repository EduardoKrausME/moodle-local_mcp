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
 * OAuth authorization code redemption tests.
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
use local_mcp\oauth\resource;
use local_mcp\oauth\token_service;
use local_mcp\security\scope;
use local_mcp\security\secret;

/**
 * Authorization code single-use and PKCE validation.
 *
 * @covers \local_mcp\oauth\token_service
 */
final class token_service_test extends advanced_testcase {
    /**
     * Authorization codes cannot be exchanged twice.
     *
     * @return void
     */
    public function test_code_is_single_use(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $redirecturi = "https://example.org/oauth/callback";
        $registered = client_service::register_public([
            "client_name" => "OAuth PHPUnit client",
            "redirect_uris" => [$redirecturi],
        ]);
        $client = client_service::by_clientid($registered["client_id"]);
        $verifier = str_repeat("a", 43);
        $challenge = secret::base64url(hash("sha256", $verifier, true));
        $resource = resource::endpoint("server");
        $code = authorization_service::issue_code(
            $client, $user->id, $redirecturi, [scope::READ], $challenge, $resource
        );

        $tokens = token_service::exchange_code($registered["client_id"], $code, $redirecturi, $verifier, $resource);
        $this->assertNotEmpty($tokens["access_token"]);
        $this->assertSame(1, (int)$DB->get_field("local_mcp_auth_code", "used", ["codehash" => secret::hash($code)]));

        try {
            token_service::exchange_code($registered["client_id"], $code, $redirecturi, $verifier, $resource);
            $this->fail("The same authorization code must not be redeemed twice.");
        } catch (api_exception $e) {
            $this->assertSame("invalid_grant", $e->machinecode);
        }
    }

    /**
     * An invalid PKCE verifier must not consume the authorization code.
     *
     * @return void
     */
    public function test_invalid_verifier_preserves_code(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $redirecturi = "https://example.org/oauth/callback";
        $registered = client_service::register_public([
            "client_name" => "OAuth PHPUnit client",
            "redirect_uris" => [$redirecturi],
        ]);
        $client = client_service::by_clientid($registered["client_id"]);
        $verifier = str_repeat("a", 43);
        $challenge = secret::base64url(hash("sha256", $verifier, true));
        $resource = resource::endpoint("server");
        $code = authorization_service::issue_code(
            $client, $user->id, $redirecturi, [scope::READ], $challenge, $resource
        );

        try {
            token_service::exchange_code(
                $registered["client_id"], $code, $redirecturi, str_repeat("b", 43), $resource
            );
            $this->fail("The invalid PKCE verifier should be rejected.");
        } catch (api_exception $e) {
            $this->assertSame("invalid_grant", $e->machinecode);
        }
        $this->assertSame(0, (int)$DB->get_field("local_mcp_auth_code", "used", ["codehash" => secret::hash($code)]));
        $tokens = token_service::exchange_code($registered["client_id"], $code, $redirecturi, $verifier, $resource);
        $this->assertNotEmpty($tokens["access_token"]);
    }
}
