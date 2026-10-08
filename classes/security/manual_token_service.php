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
 * manual_token_service.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\security;

use context_system;
use local_mcp\event\manual_token_created;
use local_mcp\event\manual_token_revoked;
use local_mcp\exception\api_exception;

/**
 * Class manual_token_service.
 */
final class manual_token_service {
    /**
     * Method create.
     *
     * @param string $name Parameter name.
     * @param int $userid Parameter userid.
     * @param bool $read Parameter read.
     * @param bool $write Parameter write.
     * @param ?int $expires Parameter expires.
     * @return array Return value.
     */
    public static function create(string $name, int $userid, bool $read, bool $write, ?int $expires): array {
        global $DB;
        if (!$read && !$write) {
            throw new api_exception('invalid_scope', 400);
        }
        $token = secret::generate('mcp_', 40);
        $now = time();
        $id = $DB->insert_record('local_mcp_manual_token', (object)[
            'name' => clean_param($name, PARAM_TEXT),
            'userid' => $userid,
            'prefix' => secret::prefix($token),
            'tokenhash' => secret::hash($token),
            'readenabled' => (int)$read,
            'writeenabled' => (int)$write,
            'expires' => $expires,
            'enabled' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
            'lastused' => null,
            'lastip' => null,
        ]);
        manual_token_created::create([
            'context' => context_system::instance(),
            'objectid' => (int)$id,
            'relateduserid' => $userid,
        ])->trigger();
        return ['id' => (int)$id, 'token' => $token];
    }

    /**
     * Update READ and WRITE on an existing, active manual token.
     *
     * Keeps the same token so the existing MCP server URL remains valid.
     * Any pending WRITE confirmations are invalidated on scope changes.
     *
     * @param int $id Manual token record ID.
     * @param bool $read Whether READ is allowed.
     * @param bool $write Whether WRITE is allowed.
     * @return void
     */
    public static function update_permissions(int $id, bool $read, bool $write): void {
        global $DB;

        if (!$read && !$write) {
            throw new api_exception('invalid_scope', 400);
        }

        $transaction = $DB->start_delegated_transaction();
        $record = $DB->get_record('local_mcp_manual_token', ['id' => $id], '*', MUST_EXIST);
        if (!$record->enabled || ($record->expires && $record->expires <= time())) {
            throw new api_exception('invalid_token', 403);
        }

        if ((bool)$record->readenabled !== $read || (bool)$record->writeenabled !== $write) {
            $DB->update_record('local_mcp_manual_token', (object)[
                'id' => $id,
                'readenabled' => (int)$read,
                'writeenabled' => (int)$write,
                'timemodified' => time(),
            ]);

            // Confirmation records are bound to the token hash, even for manual tokens.
            $DB->delete_records('local_mcp_confirm', ['accesshash' => $record->tokenhash]);
        }
        $transaction->allow_commit();
    }

    /**
     * Method revoke.
     *
     * @param int $id Parameter id.
     * @return void Return value.
     */
    public static function revoke(int $id): void {
        global $DB;
        $record = $DB->get_record('local_mcp_manual_token', ['id' => $id], '*', MUST_EXIST);
        $DB->update_record('local_mcp_manual_token', (object)[
            'id' => $id,
            'enabled' => 0,
            'timemodified' => time(),
        ]);
        manual_token_revoked::create([
            'context' => context_system::instance(),
            'objectid' => $id,
            'relateduserid' => (int)$record->userid,
        ])->trigger();
    }
}
