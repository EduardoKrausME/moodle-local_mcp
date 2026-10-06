<?php
namespace local_mcp\oauth;

defined('MOODLE_INTERNAL') || die;

final class client_service {
    public static function by_clientid(string $clientid): \stdClass {
        global $DB;
        $client = $DB->get_record('local_mcp_oauth_client', ['clientid' => $clientid, 'enabled' => 1], '*', MUST_EXIST);
        return $client;
    }

    public static function validate_redirect_uri(\stdClass $client, string $redirecturi): void {
        $uris = json_decode($client->redirecturis, true);
        if (!is_array($uris) || !in_array($redirecturi, $uris, true)) {
            throw new \local_mcp\exception\api_exception('invalid_redirect_uri', 400);
        }
    }

    public static function register_public(array $metadata): array {
        global $DB;
        $uris = array_values(array_unique($metadata['redirect_uris'] ?? []));
        if (!$uris) {
            throw new \local_mcp\exception\api_exception('invalid_client_metadata', 400);
        }
        foreach ($uris as $uri) {
            if (!filter_var($uri, FILTER_VALIDATE_URL)) {
                throw new \local_mcp\exception\api_exception('invalid_redirect_uri', 400);
            }
        }
        $clientid = 'mcp_client_' . bin2hex(random_bytes(16));
        $record = (object) [
            'clientid' => $clientid,
            'name' => clean_param($metadata['client_name'] ?? 'MCP client', PARAM_TEXT),
            'description' => '',
            'clienturi' => isset($metadata['client_uri']) ? clean_param($metadata['client_uri'], PARAM_URL) : null,
            'redirecturis' => json_encode($uris),
            'enabled' => 1,
            'clienttype' => 'public',
            'dynamic' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $DB->insert_record('local_mcp_oauth_client', $record);
        return [
            'client_id' => $clientid,
            'client_name' => $record->name,
            'redirect_uris' => $uris,
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
        ];
    }
}
