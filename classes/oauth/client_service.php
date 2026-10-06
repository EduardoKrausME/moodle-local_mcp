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
 * client_service.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\oauth;

use local_mcp\exception\api_exception;
use stdClass;

/**
 * Class client_service.
 */
final class client_service {
    /**
     * Method by_clientid.
     *
     * @param string $clientid Parameter clientid.
     * @return stdClass Return value.
     */
    public static function by_clientid(string $clientid): stdClass {
        global $DB;
        $client = $DB->get_record('local_mcp_oauth_client', ['clientid' => $clientid, 'enabled' => 1], '*', MUST_EXIST);
        return $client;
    }

    /**
     * Method validate_redirect_uri.
     *
     * @param stdClass $client Parameter client.
     * @param string $redirecturi Parameter redirecturi.
     * @return void Return value.
     */
    public static function validate_redirect_uri(stdClass $client, string $redirecturi): void {
        $uris = json_decode($client->redirecturis, true);
        if (!is_array($uris) || !in_array($redirecturi, $uris, true)) {
            throw new api_exception('invalid_redirect_uri', 400);
        }
    }

    /**
     * Method register_public.
     *
     * @param array $metadata Parameter metadata.
     * @return array Return value.
     */
    public static function register_public(array $metadata): array {
        global $DB;
        $uris = array_values(array_unique($metadata['redirect_uris'] ?? []));
        if (!$uris) {
            throw new api_exception('invalid_client_metadata', 400);
        }
        foreach ($uris as $uri) {
            if (!filter_var($uri, FILTER_VALIDATE_URL)) {
                throw new api_exception('invalid_redirect_uri', 400);
            }
            $parts = parse_url($uri);
            $scheme = strtolower((string)($parts['scheme'] ?? ''));
            $host = strtolower((string)($parts['host'] ?? ''));
            $loopback = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
            if ($scheme !== 'https' && !($loopback && $scheme === 'http')) {
                throw new api_exception('invalid_redirect_uri', 400);
            }
        }
        $clientid = 'mcp_client_' . bin2hex(random_bytes(16));
        $record = (object)[
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
