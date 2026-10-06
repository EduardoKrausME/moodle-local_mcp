<?php
namespace local_mcp\oauth;

defined('MOODLE_INTERNAL') || die;

final class authorization_service {
    public static function validate_request(array $params): array {
        foreach (['client_id', 'redirect_uri', 'response_type', 'scope', 'state', 'code_challenge', 'code_challenge_method'] as $required) {
            if (!isset($params[$required]) || $params[$required] === '') {
                throw new \local_mcp\exception\api_exception('invalid_request', 400);
            }
        }
        if ($params['response_type'] !== 'code' || $params['code_challenge_method'] !== 'S256') {
            throw new \local_mcp\exception\api_exception('unsupported_response_type', 400);
        }
        if (!preg_match('/^[A-Za-z0-9\-._~]{43,128}$/', $params['code_challenge'])) {
            throw new \local_mcp\exception\api_exception('invalid_code_challenge', 400);
        }
        $client = client_service::by_clientid($params['client_id']);
        client_service::validate_redirect_uri($client, $params['redirect_uri']);
        $scopes = \local_mcp\security\scope::parse($params['scope']);
        if (!$scopes) {
            throw new \local_mcp\exception\api_exception('invalid_scope', 400);
        }
        return [$client, $scopes];
    }

    public static function issue_code(\stdClass $client, int $userid, string $redirecturi, array $scopes,
            string $challenge): string {
        global $DB;
        $code = \local_mcp\security\secret::generate('mcp_code_', 32);
        $ttl = (int) get_config('local_mcp', 'codettl') ?: 300;
        $DB->insert_record('local_mcp_auth_code', (object) [
            'prefix' => \local_mcp\security\secret::prefix($code),
            'codehash' => \local_mcp\security\secret::hash($code),
            'clientid' => $client->id,
            'userid' => $userid,
            'redirecturi' => $redirecturi,
            'scopes' => \local_mcp\security\scope::to_string($scopes),
            'codechallenge' => $challenge,
            'challengemethod' => 'S256',
            'timecreated' => time(),
            'expires' => time() + $ttl,
            'used' => 0,
        ]);
        return $code;
    }
}
