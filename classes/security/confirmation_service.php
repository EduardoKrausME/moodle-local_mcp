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
 * confirmation_service.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\security;

use context;
use local_mcp\exception\api_exception;

/**
 * Class confirmation_service.
 */
final class confirmation_service {
    /**
     * Method args_hash.
     *
     * @param array $arguments Parameter arguments.
     * @return string Return value.
     */
    public static function args_hash(array $arguments): string {
        self::ksort_recursive($arguments);
        return hash('sha256', json_encode($arguments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Method issue.
     *
     * @param authenticated_identity $identity Parameter identity.
     * @param string $rawaccess Parameter rawaccess.
     * @param string $tool Parameter tool.
     * @param array $arguments Parameter arguments.
     * @param context $context Parameter context.
     * @return string Return value.
     */
    public static function issue(authenticated_identity $identity, string $rawaccess, string $tool,
                                 array                  $arguments, context $context): string {
        global $DB;
        $token = secret::generate('mcp_confirm_', 32);
        $ttl = (int)get_config('local_mcp', 'confirmationttl') ?: 300;
        $DB->insert_record('local_mcp_confirm', (object)[
            'prefix' => secret::prefix($token),
            'tokenhash' => secret::hash($token),
            'userid' => $identity->userid,
            'clientid' => $identity->clientid,
            'connectionid' => $identity->connectionid,
            'accesshash' => secret::hash($rawaccess),
            'tool' => $tool,
            'argshash' => self::args_hash($arguments),
            'contextid' => $context->id,
            'expires' => time() + $ttl,
            'used' => 0,
            'timecreated' => time(),
        ]);
        return $token;
    }

    /**
     * Method consume.
     *
     * @param string $token Parameter token.
     * @param authenticated_identity $identity Parameter identity.
     * @param string $rawaccess Parameter rawaccess.
     * @param string $tool Parameter tool.
     * @param array $arguments Parameter arguments.
     * @param context $context Parameter context.
     * @return void Return value.
     */
    public static function consume(string $token, authenticated_identity $identity, string $rawaccess,
                                   string $tool, array $arguments, context $context): void {
        global $DB;
        $rec = $DB->get_record('local_mcp_confirm', ['tokenhash' => secret::hash($token)], '*', MUST_EXIST);
        $valid = !$rec->used && $rec->expires >= time()
            && (int)$rec->userid === $identity->userid
            && $rec->tool === $tool
            && hash_equals($rec->argshash, self::args_hash($arguments))
            && hash_equals($rec->accesshash, secret::hash($rawaccess))
            && (int)$rec->contextid === (int)$context->id
            && (($rec->clientid === null && $identity->clientid === null) || (int)$rec->clientid === (int)$identity->clientid)
            && (($rec->connectionid === null && $identity->connectionid === null) || (int)$rec->connectionid === (int)$identity->connectionid);
        if (!$valid) {
            throw new api_exception('confirmation_expired', 409);
        }
        $DB->set_field('local_mcp_confirm', 'used', 1, ['id' => $rec->id]);
    }

    /**
     * Method ksort_recursive.
     *
     * @param array $value Parameter value.
     * @return void Return value.
     */
    private static function ksort_recursive(array &$value): void {
        ksort($value);
        foreach ($value as &$item) {
            if (is_array($item)) {
                self::ksort_recursive($item);
            }
        }
    }
}
