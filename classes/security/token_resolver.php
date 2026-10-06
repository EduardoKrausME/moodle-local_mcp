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

use local_mcp\exception\api_exception;

/**
 * Class token_resolver.
 */
final class token_resolver {
    /**
     * Method from_bearer.
     *
     * @param ?string $authorization Parameter authorization.
     * @return authenticated_identity Return value.
     */
    public static function from_bearer(?string $authorization): authenticated_identity {
        global $DB;
        if (!$authorization || !preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $m)) {
            throw new api_exception('invalid_token', 401);
        }
        $token = trim($m[1]);
        $prefix = secret::prefix($token);
        $hash = secret::hash($token);

        if (str_starts_with($token, 'mcp_at_')) {
            $records = $DB->get_records('local_mcp_access_token', ['prefix' => $prefix]);
            foreach ($records as $rec) {
                if (!hash_equals($rec->tokenhash, $hash)) {
                    continue;
                }
                if ($rec->revokedat || $rec->expiresat < time()) {
                    throw new api_exception('expired_token', 401);
                }
                $connection = $DB->get_record('local_mcp_connection', ['id' => $rec->connectionid, 'enabled' => 1]);
                if (!$connection || $connection->revokedat) {
                    throw new api_exception('invalid_token', 401);
                }
                self::touch('local_mcp_access_token', $rec->id);
                return new authenticated_identity(
                    (int)$rec->userid,
                    'oauth',
                    scope::parse($rec->scopes),
                    (int)$rec->clientid,
                    (int)$rec->connectionid,
                    (int)$rec->id,
                    $rec->family
                );
            }
        }

        if (str_starts_with($token, 'mcp_') && !str_starts_with($token, 'mcp_at_') && !str_starts_with($token, 'mcp_rt_')) {
            $records = $DB->get_records('local_mcp_manual_token', ['prefix' => $prefix, 'enabled' => 1]);
            foreach ($records as $rec) {
                if (!hash_equals($rec->tokenhash, $hash)) {
                    continue;
                }
                if ($rec->expires && $rec->expires < time()) {
                    throw new api_exception('expired_token', 401);
                }
                self::touch('local_mcp_manual_token', $rec->id);
                $scopes = [];
                if ($rec->readenabled) {
                    $scopes[] = scope::READ;
                }
                if ($rec->writeenabled) {
                    $scopes[] = scope::WRITE;
                }
                return new authenticated_identity((int)$rec->userid, 'manual', $scopes, null, null, (int)$rec->id);
            }
        }

        throw new api_exception('invalid_token', 401);
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
