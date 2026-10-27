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
 * update_user.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;

use context;
use context_user;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\security\password_policy;
use stdClass;

/**
 * Update a user, optionally changing a locally managed password.
 */
final class update_user extends base_tool {
    /** @return string */
    public function get_name(): string {
        return 'update_user';
    }

    /** @return string */
    public function get_title(): string {
        return 'Update user';
    }

    /** @return string */
    public function get_description(): string {
        return 'Update a Moodle user and optionally set a password for a manual-authentication account. '
            . 'Call get_password_policy before generating a password. A password is checked against '
            . 'Moodle policy before confirmation and again before saving; failures return actionable errors. '
            . 'The password is never included in previews, results or audit logs. '
            . 'Current site password policy: ' . json_encode(password_policy::current());
    }

    /** @return string */
    public function get_required_capability(): string {
        return 'moodle/user:update';
    }

    /** @return array */
    public function get_input_schema(): array {
        return $this->object_schema([
            'userid' => ['type' => 'integer', 'minimum' => 1],
            'firstname' => ['type' => 'string'],
            'lastname' => ['type' => 'string'],
            'email' => ['type' => 'string'],
            'password' => [
                'type' => 'string',
                'minLength' => 1,
                'description' => 'New manual-account password. Read get_password_policy first. '
                    . 'Never send an existing hash or include the password in a summary.',
            ],
        ], ['userid']);
    }

    /**
     * @param array $arguments Tool arguments.
     * @return context
     */
    public function resolve_context(array $arguments): context {
        return context_user::instance((int)$arguments['userid'], MUST_EXIST);
    }

    /**
     * Guard password updates from accidentally modifying externally managed accounts.
     *
     * @param int $userid Target account.
     * @param authenticated_identity $identity Actor.
     * @return stdClass
     * @throws api_exception
     */
    private function password_target(int $userid, authenticated_identity $identity): stdClass {
        global $DB;
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0],
            'id,auth,password', MUST_EXIST);
        if ($user->auth !== 'manual') {
            throw new api_exception('password_auth_unsupported', 400,
                'Passwords for this authentication method must be changed in its identity provider.');
        }
        if (is_siteadmin($userid) && !is_siteadmin($identity->userid)) {
            throw new api_exception('permission_denied', 403,
                'Only a site administrator may change an administrator password.');
        }
        return $user;
    }

    /**
     * Password validation and redaction happen before the confirmation token is issued.
     *
     * @param array $arguments Tool arguments.
     * @param authenticated_identity $identity Actor.
     * @return array
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        $fields = array_values(array_intersect(['firstname', 'lastname', 'email'], array_keys($arguments)));
        $preview = ['userid' => (int)$arguments['userid'], 'fields' => $fields];
        if (array_key_exists('password', $arguments)) {
            $user = $this->password_target((int)$arguments['userid'], $identity);
            password_policy::validate($arguments['password'], $user);
            $preview['password_change_requested'] = true;
            $preview['password_valid'] = true;
            $preview['password_policy'] = password_policy::current();
            $preview['password_redacted'] = true;
        }
        return $preview;
    }

    /**
     * Update the profile and password using Moodle APIs and verify saved values.
     *
     * @param array $arguments Tool arguments.
     * @param authenticated_identity $identity Actor.
     * @return array
     * @throws api_exception
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');
        $userid = (int)$arguments['userid'];
        $changepassword = array_key_exists('password', $arguments);
        if ($changepassword) {
            $target = $this->password_target($userid, $identity);
            password_policy::validate($arguments['password'], $target);
        }
        $user = (object)['id' => $userid];
        $fields = [];
        foreach (['firstname', 'lastname'] as $field) {
            if (array_key_exists($field, $arguments)) {
                $user->{$field} = clean_param($arguments[$field], PARAM_TEXT);
                $fields[] = $field;
            }
        }
        if (array_key_exists('email', $arguments)) {
            $user->email = clean_param($arguments['email'], PARAM_EMAIL);
            $fields[] = 'email';
        }
        if (!$fields && !$changepassword) {
            throw new api_exception('no_changes_requested', 400);
        }

        $transaction = $DB->start_delegated_transaction();
        if ($fields) {
            if (method_exists(\core\user::class, 'update_user')) {
                \core\user::update_user($user, false, false);
            } else {
                user_update_user($user, false, false);
            }
        }
        if ($changepassword) {
            $auth = get_auth_plugin('manual');
            if (!$auth->user_update_password($target, $arguments['password'])) {
                throw new api_exception('password_update_failed', 500,
                    'Moodle did not accept the password update.');
            }
        }
        $saved = $DB->get_record('user', ['id' => $userid, 'deleted' => 0],
            'id,firstname,lastname,email,password', MUST_EXIST);
        foreach ($fields as $field) {
            if ($saved->{$field} !== $user->{$field}) {
                throw new api_exception('user_update_not_persisted', 500,
                    'Moodle did not persist all requested profile changes.');
            }
        }
        if ($changepassword && !validate_internal_user_password($saved, $arguments['password'])) {
            throw new api_exception('password_update_not_persisted', 500,
                'The new password could not be verified after saving.');
        }
        $transaction->allow_commit();
        return [
            'updated' => true,
            'userid' => $userid,
            'updated_fields' => $fields,
            'password_changed' => $changepassword,
            'password_verified' => $changepassword,
        ];
    }
}
