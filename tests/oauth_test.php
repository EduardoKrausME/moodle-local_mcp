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
 * oauth_test.php
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

defined('MOODLE_INTERNAL') || die;

/**
 * Class oauth_test.
 */
final class oauth_test extends advanced_testcase {
    /**
     * Method test_redirect_uri_requires_exact_match.
     *
     * @return void Return value.
     */
    public function test_redirect_uri_requires_exact_match(): void {
        $client = (object)['redirecturis' => json_encode(['https://client.example/callback'])];
        client_service::validate_redirect_uri($client, 'https://client.example/callback');
        $this->expectException(api_exception::class);
        client_service::validate_redirect_uri($client, 'https://client.example/callback/evil');
    }

    /**
     * Method test_authorization_request_rejects_non_s256.
     *
     * @return void Return value.
     */
    public function test_authorization_request_rejects_non_s256(): void {
        $this->resetAfterTest();
        global $DB;
        $clientid = 'test_' . bin2hex(random_bytes(4));
        $DB->insert_record('local_mcp_oauth_client', (object)[
            'clientid' => $clientid, 'name' => 'Test', 'description' => '', 'clienturi' => 'https://client.example',
            'redirecturis' => json_encode(['https://client.example/callback']), 'enabled' => 1, 'clienttype' => 'public',
            'dynamic' => 0, 'timecreated' => time(), 'timemodified' => time(), 'lastused' => null,
        ]);
        $this->expectException(api_exception::class);
        authorization_service::validate_request([
            'client_id' => $clientid, 'redirect_uri' => 'https://client.example/callback', 'response_type' => 'code',
            'scope' => 'mcp:read', 'state' => 'state', 'code_challenge' => str_repeat('a', 43), 'code_challenge_method' => 'plain',
            'resource' => resource::endpoint('server'),
        ]);
    }
    /**
     * Confirm that OAuth audiences cannot be exchanged for other MCP servers.
     *
     * @return void
     */
    public function test_resource_identifiers_are_exact_and_endpoint_specific(): void {
        $read = resource::endpoint('read');
        $write = resource::endpoint('write');
        $combined = resource::endpoint('server');
        $this->assertNotEquals($read, $write);
        $this->assertSame($read, resource::validate($read));
        $this->assertSame($combined, resource::validate($combined));
        $this->expectException(api_exception::class);
        resource::validate($read . '/other');
    }

    /**
     * An OAuth access token and a manual token authenticate independently.
     *
     * @return void
     */
    public function test_oauth_and_manual_tokens_coexist(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $clientid = 'test_' . bin2hex(random_bytes(8));
        $redirect = 'https://client.example/oauth/callback';
        $id = $DB->insert_record('local_mcp_oauth_client', (object)[
            'clientid' => $clientid, 'name' => 'OAuth test', 'description' => '',
            'clienturi' => 'https://client.example',
            'redirecturis' => json_encode([$redirect]), 'enabled' => 1, 'clienttype' => 'public',
            'dynamic' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $client = $DB->get_record('local_mcp_oauth_client', ['id' => $id], '*', MUST_EXIST);
        $verifier = str_repeat('v', 43);
        $challenge = \local_mcp\security\secret::base64url(hash('sha256', $verifier, true));
        $resource = resource::endpoint('server');
        $code = authorization_service::issue_code($client, (int)$USER->id, $redirect,
            ['mcp:read', 'mcp:write'], $challenge, $resource);
        $pair = \local_mcp\oauth\token_service::exchange_code($clientid, $code, $redirect, $verifier, $resource);

        $identity = \local_mcp\security\token_resolver::from_bearer('Bearer ' . $pair['access_token'], $resource);
        $this->assertSame('oauth', $identity->type);
        $this->assertTrue($identity->has_scope('mcp:read'));
        $this->assertTrue($identity->has_scope('mcp:write'));

        $manual = \local_mcp\security\manual_token_service::create(
            'Legacy token', (int)$USER->id, true, false, time() + DAYSECS);
        $legacy = \local_mcp\security\token_resolver::from_bearer('Bearer ' . $manual['token'], $resource);
        $this->assertSame('manual', $legacy->type);
        $this->assertTrue($legacy->has_scope('mcp:read'));
        $this->assertFalse($legacy->has_scope('mcp:write'));

        // Tightening an OAuth connection must immediately affect issued credentials.
        $DB->set_field('local_mcp_connection', 'scopes', 'mcp:read', ['id' => $identity->connectionid]);
        $reduced = \local_mcp\security\token_resolver::from_bearer('Bearer ' . $pair['access_token'], $resource);
        $this->assertFalse($reduced->has_scope('mcp:write'));
        $rotated = \local_mcp\oauth\token_service::refresh($clientid, $pair['refresh_token'], $resource);
        $this->assertSame('mcp:read', $rotated['scope']);

        // Disabling an OAuth client never disables manually issued tokens.
        $DB->set_field('local_mcp_oauth_client', 'enabled', 0, ['id' => $id]);
        try {
            \local_mcp\security\token_resolver::from_bearer('Bearer ' . $rotated['access_token'], $resource);
            $this->fail('Disabled clients must not authenticate.');
        } catch (api_exception $e) {
            $this->assertSame(401, $e->httpstatus);
        }
        $this->assertSame('manual',
            \local_mcp\security\token_resolver::from_bearer('Bearer ' . $manual['token'], $resource)->type);
    }

    /**
     * OAuth rejects unknown scopes rather than silently granting a subset.
     *
     * @return void
     */
    public function test_reject_unknown_oauth_scopes(): void {
        global $DB;

        $this->resetAfterTest();
        $clientid = 'test_' . bin2hex(random_bytes(8));
        $DB->insert_record('local_mcp_oauth_client', (object)[
            'clientid' => $clientid, 'name' => 'OAuth test', 'description' => '',
            'clienturi' => 'https://client.example',
            'redirecturis' => json_encode(['https://client.example/callback']), 'enabled' => 1,
            'clienttype' => 'public', 'dynamic' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $this->expectException(api_exception::class);
        authorization_service::validate_request([
            'client_id' => $clientid, 'redirect_uri' => 'https://client.example/callback',
            'response_type' => 'code', 'scope' => 'mcp:read admin:everything',
            'state' => 'state', 'code_challenge' => str_repeat('a', 43),
            'code_challenge_method' => 'S256', 'resource' => resource::endpoint('server'),
        ]);
    }

    /**
     * Without a scope parameter OAuth falls back to read-only.
     *
     * @return void
     */
    public function test_missing_oauth_scope_defaults_to_read(): void {
        global $DB;

        $this->resetAfterTest();
        $clientid = 'test_' . bin2hex(random_bytes(8));
        $DB->insert_record('local_mcp_oauth_client', (object)[
            'clientid' => $clientid, 'name' => 'OAuth test', 'description' => '',
            'clienturi' => 'https://client.example',
            'redirecturis' => json_encode(['https://client.example/callback']), 'enabled' => 1,
            'clienttype' => 'public', 'dynamic' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        [, $scopes] = authorization_service::validate_request([
            'client_id' => $clientid, 'redirect_uri' => 'https://client.example/callback',
            'response_type' => 'code', 'state' => 'state',
            'code_challenge' => str_repeat('a', 43),
            'code_challenge_method' => 'S256', 'resource' => resource::endpoint('server'),
        ]);
        $this->assertSame(['mcp:read'], $scopes);
    }

}
