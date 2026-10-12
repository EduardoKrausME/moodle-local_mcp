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
 * token_resolver.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\security;

use local_mcp\diagnostics;
use local_mcp\exception\api_exception;

/**
 * Class token_resolver.
 */
final class token_resolver {
    /**
     * Method from_bearer.
     *
     * @param ?string $authorization Parameter authorization.
     * @param ?string $resource Expected audience for OAuth tokens.
     * @return authenticated_identity Return value.
     */
    public static function from_bearer(?string $authorization, ?string $resource = null): authenticated_identity {
        global $DB;
        if (!$authorization || !preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $m)) {
            self::reject('missing_or_malformed_bearer');
        }
        $token = trim($m[1]);
        if (!str_starts_with($token, 'mcp_at_')) {
            self::reject('unsupported_access_token_format');
        }
        $prefix = secret::prefix($token);
        $hash = secret::hash($token);
        $records = $DB->get_records('local_mcp_access_token', ['prefix' => $prefix]);
        foreach ($records as $rec) {
            if (!hash_equals($rec->tokenhash, $hash)) {
                continue;
            }
            if (!$resource || !hash_equals((string)$rec->resource, $resource)) {
                self::reject('access_token_wrong_audience', 'invalid_token_audience');
            }
            if ($rec->revokedat || $rec->expiresat < time()) {
                self::reject('access_token_revoked_or_expired', 'expired_token');
            }
            $connection = $DB->get_record('local_mcp_connection', ['id' => $rec->connectionid, 'enabled' => 1]);
            if (!$connection || $connection->revokedat ||
                (int)$connection->userid !== (int)$rec->userid ||
                (int)$connection->clientid !== (int)$rec->clientid ||
                !$DB->record_exists('local_mcp_oauth_client', ['id' => $rec->clientid, 'enabled' => 1])) {
                self::reject('oauth_connection_or_client_invalid');
            }
            // A connection can have its permissions reduced after an access token was issued.
            // Do not let an older token restore the permissions that were removed.
            $scopes = array_values(array_intersect(scope::parse($rec->scopes), scope::parse($connection->scopes)));
            if (!$scopes) {
                self::reject('access_token_scopes_revoked');
            }
            self::touch('local_mcp_access_token', $rec->id);
            return new authenticated_identity(
                (int)$rec->userid,
                'oauth',
                $scopes,
                (int)$rec->clientid,
                (int)$rec->connectionid,
                (int)$rec->id,
                $rec->family
            );
        }
        self::reject('access_token_not_found');
    }

    /**
     * Reject credentials with a safe internal reason and unchanged OAuth response.
     *
     * @param string $reason Stable, non-secret rejection reason.
     * @param string $code Public OAuth error code.
     * @return never
     */
    private static function reject(string $reason, string $code = 'invalid_token'): never {
        diagnostics::event('auth_rejected', [
            'phase' => 'token_validation',
            'reason' => $reason,
        ], 'WARNING');
        throw new api_exception($code, 401);
    }

    /**
     * Method touch.
     *
     * @param string $table Parameter table.
     * @param int $id Parameter id.
     * @return void Return value.
     */
    private static function touch(string $table, int $id): void {
        global $DB;
        $DB->update_record($table, (object)[
            'id' => $id,
            'lastused' => time(),
            'lastip' => getremoteaddr(),
        ]);
    }
}
