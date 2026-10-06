<?php
namespace local_mcp\oauth;

defined('MOODLE_INTERNAL') || die;

final class token_service {
    public static function exchange_code(string $clientid, string $code, string $redirecturi, string $verifier): array {
        global $DB;
        $client = client_service::by_clientid($clientid);
        client_service::validate_redirect_uri($client, $redirecturi);
        $hash = \local_mcp\security\secret::hash($code);
        $rec = $DB->get_record('local_mcp_auth_code', ['codehash' => $hash, 'clientid' => $client->id], '*', MUST_EXIST);
        if ($rec->used || $rec->expires < time() || !hash_equals($rec->redirecturi, $redirecturi)) {
            throw new \local_mcp\exception\api_exception('invalid_grant', 400);
        }
        $challenge = \local_mcp\security\secret::base64url(hash('sha256', $verifier, true));
        if (!hash_equals($rec->codechallenge, $challenge)) {
            throw new \local_mcp\exception\api_exception('invalid_grant', 400);
        }
        $DB->set_field('local_mcp_auth_code', 'used', 1, ['id' => $rec->id]);
        $connectionid = self::connection($rec->userid, $client->id, $rec->scopes);
        return self::issue_pair($rec->userid, $client->id, $connectionid, $rec->scopes);
    }

    public static function refresh(string $clientid, string $refresh): array {
        global $DB;
        $client = client_service::by_clientid($clientid);
        $hash = \local_mcp\security\secret::hash($refresh);
        $rec = $DB->get_record('local_mcp_refresh_token', ['tokenhash' => $hash, 'clientid' => $client->id], '*', MUST_EXIST);
        if ($rec->revokedat || $rec->usedat || $rec->expiresat < time()) {
            self::revoke_family($rec->family);
            throw new \local_mcp\exception\api_exception('invalid_grant', 400);
        }
        $DB->set_field('local_mcp_refresh_token', 'usedat', time(), ['id' => $rec->id]);
        return self::issue_pair($rec->userid, $client->id, $rec->connectionid, $rec->scopes, $rec->family, $rec->generation + 1);
    }

    private static function issue_pair(int $userid, int $clientid, int $connectionid, string $scopes,
            ?string $family = null, int $generation = 0): array {
        global $DB;
        $family = $family ?: bin2hex(random_bytes(24));
        $access = \local_mcp\security\secret::generate('mcp_at_', 32);
        $refresh = \local_mcp\security\secret::generate('mcp_rt_', 48);
        $now = time();
        $accessttl = (int) get_config('local_mcp', 'accessttl') ?: 3600;
        $refreshttl = (int) get_config('local_mcp', 'refreshttl') ?: 90 * DAYSECS;
        $DB->insert_record('local_mcp_access_token', (object) [
            'prefix' => \local_mcp\security\secret::prefix($access), 'tokenhash' => \local_mcp\security\secret::hash($access),
            'userid' => $userid, 'clientid' => $clientid, 'connectionid' => $connectionid, 'scopes' => $scopes,
            'family' => $family, 'issuedat' => $now, 'expiresat' => $now + $accessttl, 'revokedat' => null,
            'lastused' => null, 'lastip' => null,
        ]);
        $DB->insert_record('local_mcp_refresh_token', (object) [
            'prefix' => \local_mcp\security\secret::prefix($refresh), 'tokenhash' => \local_mcp\security\secret::hash($refresh),
            'userid' => $userid, 'clientid' => $clientid, 'connectionid' => $connectionid, 'scopes' => $scopes,
            'family' => $family, 'generation' => $generation, 'issuedat' => $now, 'expiresat' => $now + $refreshttl,
            'usedat' => null, 'revokedat' => null,
        ]);
        return ['access_token' => $access, 'refresh_token' => $refresh, 'token_type' => 'Bearer',
            'expires_in' => $accessttl, 'scope' => $scopes];
    }

    private static function connection(int $userid, int $clientid, string $scopes): int {
        global $DB;
        $rec = $DB->get_record('local_mcp_connection', ['userid' => $userid, 'clientid' => $clientid, 'enabled' => 1]);
        if ($rec) {
            $rec->scopes = $scopes;
            $rec->lastused = time();
            $DB->update_record('local_mcp_connection', $rec);
            return $rec->id;
        }
        $id = $DB->insert_record('local_mcp_connection', (object) [
            'userid' => $userid, 'clientid' => $clientid, 'scopes' => $scopes, 'enabled' => 1,
            'timecreated' => time(), 'lastused' => null, 'lastip' => null, 'revokedat' => null,
        ]);
        \\local_mcp\\event\\connection_authorized::create([
            'context' => \\context_system::instance(),
            'objectid' => (int)$id,
            'relateduserid' => $userid,
        ])->trigger();
        return (int)$id;
    }

    public static function revoke_family(string $family): void {
        global $DB;
        $now = time();
        $DB->set_field_select('local_mcp_access_token', 'revokedat', $now, 'family = ? AND revokedat IS NULL', [$family]);
        $DB->set_field_select('local_mcp_refresh_token', 'revokedat', $now, 'family = ? AND revokedat IS NULL', [$family]);
    }
}
