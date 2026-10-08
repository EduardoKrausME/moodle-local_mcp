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
 * connection_service.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\oauth;

use context_system;
use local_mcp\event\connection_revoked;

/**
 * Class connection_service.
 */
final class connection_service {
    /**
     * Method revoke.
     *
     * @param int $connectionid Parameter connectionid.
     * @return void Return value.
     */
    public static function revoke(int $connectionid): void {
        global $DB;
        $now = time();
        $transaction = $DB->start_delegated_transaction();
        $DB->update_record('local_mcp_connection', (object)[
            'id' => $connectionid,
            'enabled' => 0,
            'revokedat' => $now,
        ]);
        $DB->set_field_select('local_mcp_access_token', 'revokedat', $now,
            'connectionid = ? AND revokedat IS NULL', [$connectionid]);
        $DB->set_field_select('local_mcp_refresh_token', 'revokedat', $now,
            'connectionid = ? AND revokedat IS NULL', [$connectionid]);
        $connection = $DB->get_record('local_mcp_connection', ['id' => $connectionid], '*', MUST_EXIST);
        $DB->delete_records('local_mcp_auth_code', ['clientid' => $connection->clientid, 'userid' => $connection->userid, 'used' => 0]);
        $DB->delete_records('local_mcp_confirm', ['connectionid' => $connectionid, 'used' => 0]);
        $transaction->allow_commit();
        connection_revoked::create([
            'context' => context_system::instance(),
            'objectid' => $connectionid,
            'relateduserid' => (int)$connection->userid,
        ])->trigger();
    }

    /**
     * Check whether an enabled Moodle OAuth connection belongs to ChatGPT.
     *
     * OAuth client names are user supplied, so identify ChatGPT through its
     * registered redirect URI, not a display name that could be renamed.
     *
     * @return bool True if a ChatGPT connection has been authorized.
     */
    public static function has_active_chatgpt_connection(): bool {
        global $DB;

        $sql = "SELECT c.id, o.redirecturis
                  FROM {local_mcp_connection} c
                  JOIN {local_mcp_oauth_client} o ON o.id = c.clientid
                 WHERE c.enabled = :connectionenabled
                   AND o.enabled = :clientenabled
                   AND c.revokedat IS NULL";
        $connections = $DB->get_recordset_sql($sql, [
            'connectionenabled' => 1,
            'clientenabled' => 1,
        ]);
        try {
            foreach ($connections as $connection) {
                $redirects = json_decode($connection->redirecturis, true);
                if (!is_array($redirects)) {
                    continue;
                }
                foreach ($redirects as $redirect) {
                    if (is_string($redirect) && self::is_chatgpt_redirect_uri($redirect)) {
                        return true;
                    }
                }
            }
        } finally {
            $connections->close();
        }
        return false;
    }

    /**
     * Recognize official ChatGPT OAuth callback URLs, without trusting names
     * or matching arbitrary domains that contain the word chatgpt.
     *
     * @param string $redirecturi OAuth redirect URI.
     * @return bool True for ChatGPT callback URLs.
     */
    public static function is_chatgpt_redirect_uri(string $redirecturi): bool {
        $parts = parse_url($redirecturi);
        if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https'
                || strtolower($parts['host'] ?? '') !== 'chatgpt.com') {
            return false;
        }

        $path = $parts['path'] ?? '';
        return $path === '/connector_platform_oauth_redirect'
            || (bool)preg_match('~^/connector/oauth/[^/]+$~', $path);
    }

}
