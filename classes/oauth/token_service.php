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
 * token_service.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\oauth;

use context_system;
use local_mcp\event\connection_authorized;
use local_mcp\exception\api_exception;
use local_mcp\security\secret;

/**
 * Class token_service.
 */
final class token_service {
    /**
     * Method exchange_code.
     *
     * @param string $clientid Parameter clientid.
     * @param string $code Parameter code.
     * @param string $redirecturi Parameter redirecturi.
     * @param string $verifier Parameter verifier.
     * @param string $resource MCP resource identifier.
     * @return array Return value.
     */
    public static function exchange_code(string $clientid, string $code, string $redirecturi, string $verifier, string $resource): array {
        global $DB;
        $client = client_service::by_clientid($clientid);
        client_service::validate_redirect_uri($client, $redirecturi);
        $resource = resource::validate($resource);
        $hash = secret::hash($code);
        $rec = $DB->get_record('local_mcp_auth_code', ['codehash' => $hash, 'clientid' => $client->id], '*', MUST_EXIST);
        if ($rec->used || $rec->expires < time() || !hash_equals($rec->redirecturi, $redirecturi)
            || !hash_equals((string)$rec->resource, $resource)) {
            throw new api_exception('invalid_grant', 400);
        }
        $challenge = secret::base64url(hash('sha256', $verifier, true));
        if (!hash_equals($rec->codechallenge, $challenge)) {
            throw new api_exception('invalid_grant', 400);
        }
        $DB->set_field('local_mcp_auth_code', 'used', 1, ['id' => $rec->id]);
        $connectionid = self::connection($rec->userid, $client->id, $rec->scopes);
        return self::issue_pair($rec->userid, $client->id, $connectionid, $rec->scopes, $resource);
    }

    /**
     * Method refresh.
     *
     * @param string $clientid Parameter clientid.
     * @param string $refresh Parameter refresh.
     * @return array Return value.
     */
    public static function refresh(string $clientid, string $refresh): array {
        global $DB;
        $client = client_service::by_clientid($clientid);
        $hash = secret::hash($refresh);
        $rec = $DB->get_record('local_mcp_refresh_token', ['tokenhash' => $hash, 'clientid' => $client->id], '*', MUST_EXIST);
        if ($rec->revokedat || $rec->usedat || $rec->expiresat < time()) {
            self::revoke_family($rec->family);
            throw new api_exception('invalid_grant', 400);
        }
        $DB->set_field('local_mcp_refresh_token', 'usedat', time(), ['id' => $rec->id]);
        return self::issue_pair($rec->userid, $client->id, $rec->connectionid, $rec->scopes,
            resource::validate((string)$rec->resource), $rec->family, $rec->generation + 1);
    }

    /**
     * Method issue_pair.
     *
     * @param int $userid Parameter userid.
     * @param int $clientid Parameter clientid.
     * @param int $connectionid Parameter connectionid.
     * @param string $scopes Parameter scopes.
     * @param string $resource MCP resource identifier.
     * @param ?string $family Parameter family.
     * @param int $generation Parameter generation.
     * @return array Return value.
     */
    private static function issue_pair(int     $userid, int $clientid, int $connectionid, string $scopes, string $resource,
                                       ?string $family = null, int $generation = 0): array {
        global $DB;
        $resource = resource::validate($resource);
        $family = $family ?: bin2hex(random_bytes(24));
        $access = secret::generate('mcp_at_', 32);
        $refresh = secret::generate('mcp_rt_', 48);
        $now = time();
        $accessttl = (int)get_config('local_mcp', 'accessttl') ?: 3600;
        $refreshttl = (int)get_config('local_mcp', 'refreshttl') ?: 90 * DAYSECS;
        $DB->insert_record('local_mcp_access_token', (object)[
            'prefix' => secret::prefix($access), 'tokenhash' => secret::hash($access),
            'userid' => $userid, 'clientid' => $clientid, 'connectionid' => $connectionid, 'scopes' => $scopes,
            'resource' => $resource,
            'family' => $family, 'issuedat' => $now, 'expiresat' => $now + $accessttl, 'revokedat' => null,
            'lastused' => null, 'lastip' => null,
        ]);
        $DB->insert_record('local_mcp_refresh_token', (object)[
            'prefix' => secret::prefix($refresh), 'tokenhash' => secret::hash($refresh),
            'userid' => $userid, 'clientid' => $clientid, 'connectionid' => $connectionid, 'scopes' => $scopes,
            'resource' => $resource,
            'family' => $family, 'generation' => $generation, 'issuedat' => $now, 'expiresat' => $now + $refreshttl,
            'usedat' => null, 'revokedat' => null,
        ]);
        return ['access_token' => $access, 'refresh_token' => $refresh, 'token_type' => 'Bearer',
            'expires_in' => $accessttl, 'scope' => $scopes, 'resource' => $resource];
    }

    /**
     * Method connection.
     *
     * @param int $userid Parameter userid.
     * @param int $clientid Parameter clientid.
     * @param string $scopes Parameter scopes.
     * @return int Return value.
     */
    private static function connection(int $userid, int $clientid, string $scopes): int {
        global $DB;
        $rec = $DB->get_record('local_mcp_connection', ['userid' => $userid, 'clientid' => $clientid, 'enabled' => 1]);
        if ($rec) {
            $rec->scopes = $scopes;
            $rec->lastused = time();
            $DB->update_record('local_mcp_connection', $rec);
            return $rec->id;
        }
        $id = $DB->insert_record('local_mcp_connection', (object)[
            'userid' => $userid, 'clientid' => $clientid, 'scopes' => $scopes, 'enabled' => 1,
            'timecreated' => time(), 'lastused' => null, 'lastip' => null, 'revokedat' => null,
        ]);
        connection_authorized::create([
            'context' => context_system::instance(),
            'objectid' => (int)$id,
            'relateduserid' => $userid,
        ])->trigger();
        return (int)$id;
    }

    /**
     * Method revoke_family.
     *
     * @param string $family Parameter family.
     * @return void Return value.
     */
    public static function revoke_family(string $family): void {
        global $DB;
        $now = time();
        $DB->set_field_select('local_mcp_access_token', 'revokedat', $now, 'family = ? AND revokedat IS NULL', [$family]);
        $DB->set_field_select('local_mcp_refresh_token', 'revokedat', $now, 'family = ? AND revokedat IS NULL', [$family]);
    }
}
