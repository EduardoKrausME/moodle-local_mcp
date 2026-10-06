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
 * authorization_service.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\oauth;

defined('MOODLE_INTERNAL') || die;

/**
 * Class authorization_service.
 */
final class authorization_service {
    /**
     * Method validate_request.
     *
     * @param array $params Parameter params.
     * @return array Return value.
     */
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

    /**
     * Method issue_code.
     *
     * @param \stdClass $client Parameter client.
     * @param int $userid Parameter userid.
     * @param string $redirecturi Parameter redirecturi.
     * @param array $scopes Parameter scopes.
     * @param string $challenge Parameter challenge.
     * @return string Return value.
     */
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
