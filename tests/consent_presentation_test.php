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
 * OAuth consent screen identity and trust indication tests.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\oauth\client_service;
use local_mcp\oauth\consent_presentation;

/**
 * The consent screen must not use unverified client-declared website metadata.
 *
 * @covers \local_mcp\oauth\consent_presentation
 */
final class consent_presentation_test extends advanced_testcase {
    /**
     * Dynamically registered clients cannot impersonate the callback origin.
     *
     * @return void
     */
    public function test_dynamic_client_displays_registered_callback_not_claimed_website(): void {
        $this->resetAfterTest();
        $redirecturi = "https://attacker.example:8443/oauth/callback?source=chatgpt&state=1";
        $registration = client_service::register_public([
            "client_name" => "ChatGPT",
            "client_uri" => "https://chatgpt.com",
            "redirect_uris" => [$redirecturi],
        ]);
        $client = client_service::by_clientid($registration["client_id"]);

        $details = consent_presentation::details($client, $redirecturi);
        $this->assertSame("https://attacker.example:8443", $details["origin"]);
        $this->assertSame($redirecturi, $details["redirecturi"]);
        $this->assertTrue($details["unverified"]);
        $this->assertStringNotContainsString("chatgpt.com", $details["origin"]);
    }

    /**
     * The origin uses the actual hostname, even when the URI includes user info.
     *
     * @return void
     */
    public function test_origin_ignores_misleading_userinfo(): void {
        $redirecturi = "https://chatgpt.com@attacker.example/oauth/callback";
        $details = consent_presentation::details((object)["dynamic" => 1], $redirecturi);
        $this->assertSame("https://attacker.example", $details["origin"]);
        $this->assertSame($redirecturi, $details["redirecturi"]);
        $this->assertTrue($details["unverified"]);
    }

    /**
     * The warning is only attached to dynamically registered clients.
     *
     * @return void
     */
    public function test_manually_registered_client_is_not_marked_dynamic(): void {
        $redirecturi = "http://[::1]:8912/callback";
        $client = (object)["dynamic" => 0, "clienturi" => "https://other.example"];
        $details = consent_presentation::details($client, $redirecturi);
        $this->assertSame("http://[::1]:8912", $details["origin"]);
        $this->assertSame($redirecturi, $details["redirecturi"]);
        $this->assertFalse($details["unverified"]);
    }
}
