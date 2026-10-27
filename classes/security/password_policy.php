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
 * Moodle password policy for MCP password operations.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\security;

use local_mcp\exception\api_exception;
use stdClass;

/**
 * Return effective site requirements and delegate validation to Moodle.
 */
final class password_policy {
    /**
     * Only expose password requirements, never credentials or hashes.
     *
     * @return array
     */
    public static function current(): array {
        global $CFG;
        return [
            'enabled' => !empty($CFG->passwordpolicy),
            'minimum_length' => (int)($CFG->minpasswordlength ?? 0),
            'minimum_digits' => (int)($CFG->minpassworddigits ?? 0),
            'minimum_lowercase' => (int)($CFG->minpasswordlower ?? 0),
            'minimum_uppercase' => (int)($CFG->minpasswordupper ?? 0),
            'minimum_special_characters' => (int)($CFG->minpasswordnonalphanum ?? 0),
            'maximum_length' => 128,
            'note' => 'Use Moodle validation before saving. Other authentication methods may manage passwords externally.',
        ];
    }

    /**
     * Reject an invalid password before issuing a confirmation or changing data.
     *
     * @param string $password Plaintext password (never logged or returned).
     * @param stdClass|null $user Target user where relevant.
     * @return void
     * @throws api_exception
     */
    public static function validate(string $password, ?stdClass $user = null): void {
        if ($password === '') {
            throw new api_exception('password_policy_violation', 400,
                'The password cannot be empty.', [
                    'policy' => self::current(),
                    'violations' => ['A non-empty password is required.'],
                ]);
        }
        if (function_exists('exceeds_password_length') && exceeds_password_length($password)) {
            throw new api_exception('password_policy_violation', 400,
                'The password is too long for this Moodle site.', [
                    'policy' => self::current(),
                    'violations' => ['Password length exceeds the supported limit.'],
                ]);
        }
        $message = null;
        if (check_password_policy($password, $message, $user)) {
            return;
        }
        $errors = get_password_policy_errors($password, $user);
        if (!$errors) {
            $errors = [$message ?: 'The password does not comply with the Moodle password policy.'];
        }
        throw new api_exception('password_policy_violation', 400,
            'The password does not comply with the Moodle password policy.', [
                'policy' => self::current(),
                'violations' => array_values(array_map('strip_tags', $errors)),
            ]);
    }
}
