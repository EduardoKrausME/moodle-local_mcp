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
     * @param array|null $metadata Public lifetime metadata for the issued token.
     * @return string Return value.
     */
    public static function issue(authenticated_identity $identity, string $rawaccess, string $tool,
                                 array $arguments, context $context, ?array &$metadata = null): string {
        global $DB;
        $token = secret::generate('mcp_confirm_', 32);
        $ttl = self::configured_ttl();
        $created = time();
        $expires = $created + $ttl;
        $metadata = self::lifetime_metadata($created, $expires);
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
            'expires' => $expires,
            'used' => 0,
            'timecreated' => $created,
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
        $tokenhash = secret::hash($token);
        $lock = \core\lock\lock_config::get_lock_factory("local_mcp")->get_lock("confirmation_" . $tokenhash, 5);
        if (!$lock) {
            throw new api_exception("confirmation_busy", 409, "", ["reason" => "busy"]);
        }
        try {
            self::consume_locked($tokenhash, $identity, $rawaccess, $tool, $arguments, $context);
        } finally {
            $lock->release();
        }
    }

    /**
     * Validate and consume a token under a cross-request lock.
     */
    private static function consume_locked(string $tokenhash, authenticated_identity $identity, string $rawaccess,
            string $tool, array $arguments, context $context): void {
        global $DB;
        $rec = $DB->get_record("local_mcp_confirm", ["tokenhash" => $tokenhash]);
        // Do not distinguish unknown tokens from tokens belonging to another credential.
        $owned = $rec && (int)$rec->userid === $identity->userid
            && hash_equals($rec->accesshash, secret::hash($rawaccess))
            && (($rec->clientid === null && $identity->clientid === null) || (int)$rec->clientid === (int)$identity->clientid)
            && (($rec->connectionid === null && $identity->connectionid === null) || (int)$rec->connectionid === (int)$identity->connectionid);
        if (!$owned) {
            $ttl = self::configured_ttl();
            throw new api_exception("confirmation_expired", 409, "",
                ["reason" => "invalid_or_unbound", "confirmation_configured_ttl_seconds" => $ttl],
                get_string("confirmation_invalid_or_unbound", "local_mcp", $ttl));
        }
        $now = time();
        if ($rec->used) {
            $reason = "used";
        } else if ($rec->expires < $now) {
            $reason = "expired";
        } else if ($rec->tool !== $tool) {
            $reason = "tool_mismatch";
        } else if ((int)$rec->contextid !== (int)$context->id) {
            $reason = "context_mismatch";
        } else if (!hash_equals($rec->argshash, self::args_hash($arguments))) {
            $reason = "arguments_mismatch";
        } else {
            $reason = null;
        }
        if ($reason !== null) {
            $metadata = self::lifetime_metadata((int)$rec->timecreated, (int)$rec->expires);
            $details = ["reason" => $reason,
                "confirmation_remaining_seconds" => max(0, (int)$rec->expires - $now)] + $metadata;
            $message = get_string("confirmation_" . $reason, "local_mcp") . " "
                . get_string("confirmation_lifetime", "local_mcp", (object)$metadata);
            throw new api_exception("confirmation_expired", 409, "", $details, $message);
        }
        $DB->set_field('local_mcp_confirm', 'used', 1, ['id' => $rec->id]);
    }

    /**
     * Return the configured lifetime for new confirmations in seconds.
     *
     * @return int
     */
    private static function configured_ttl(): int {
        return (int)get_config("local_mcp", "confirmationttl") ?: 1800;
    }

    /**
     * Build public lifetime metadata from the persisted timestamps.
     *
     * @param int $created Creation timestamp.
     * @param int $expires Expiration timestamp.
     * @return array
     */
    private static function lifetime_metadata(int $created, int $expires): array {
        return [
            "confirmation_ttl_seconds" => $expires - $created,
            "confirmation_expires_at" => $expires,
            "confirmation_expires_at_utc" => gmdate("Y-m-d\TH:i:s\Z", $expires),
        ];
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
