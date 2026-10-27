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
 * create_user.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;

use context;
use context_system;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\security\password_policy;

/**
 * Create Moodle accounts with optional policy-validated local passwords.
 */
final class create_user extends base_tool {
    /** @return string */
    public function get_name(): string {
        return 'create_user';
    }

    /** @return string */
    public function get_title(): string {
        return 'Create user';
    }

    /** @return string */
    public function get_description(): string {
        return 'Create a Moodle user using the core user API. A password is optional for manual '
            . 'authentication accounts. Before generating a password call get_password_policy; '
            . 'any supplied password is validated by Moodle and verified after creation. '
            . 'Never include the password in a preview or result. Current site password policy: '
            . json_encode(password_policy::current());
    }

    /** @return string */
    public function get_required_capability(): string {
        return 'moodle/user:create';
    }

    /** @return array */
    public function get_input_schema(): array {
        return $this->object_schema([
            'username' => ['type' => 'string'],
            'firstname' => ['type' => 'string'],
            'lastname' => ['type' => 'string'],
            'email' => ['type' => 'string'],
            'auth' => ['type' => 'string'],
            'password' => [
                'type' => 'string',
                'minLength' => 1,
                'description' => 'Optional local password for manual authentication. '
                    . 'Read get_password_policy before generating the password.',
            ],
        ], ['username', 'firstname', 'lastname', 'email']);
    }

    /** @return context */
    public function resolve_context(array $arguments): context {
        return context_system::instance();
    }

    /**
     * @param array $arguments Tool arguments.
     * @return void
     * @throws api_exception
     */
    private function validate_password_request(array $arguments): void {
        if (!array_key_exists('password', $arguments)) {
            return;
        }
        if (clean_param($arguments['auth'] ?? 'manual', PARAM_PLUGIN) !== 'manual') {
            throw new api_exception('password_auth_unsupported', 400,
                'Only accounts using manual authentication can receive a local password.');
        }
        // Supply account fields so site-specific password callbacks can reject
        // passwords containing a new user's name or other profile data.
        $candidateuser = (object)[
            'username' => clean_param($arguments['username'], PARAM_USERNAME),
            'firstname' => clean_param($arguments['firstname'], PARAM_TEXT),
            'lastname' => clean_param($arguments['lastname'], PARAM_TEXT),
            'email' => clean_param($arguments['email'], PARAM_EMAIL),
        ];
        password_policy::validate($arguments['password'], $candidateuser);
    }

    /**
     * Suppress password from the confirmation preview.
     *
     * @param array $arguments Tool arguments.
     * @param authenticated_identity $identity Actor.
     * @return array
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        $this->validate_password_request($arguments);
        $safe = $arguments;
        unset($safe['password']);
        $preview = ['tool' => $this->get_name(), 'arguments' => $safe];
        if (array_key_exists('password', $arguments)) {
            $preview['password_change_requested'] = true;
            $preview['password_valid'] = true;
            $preview['password_redacted'] = true;
            $preview['password_policy'] = password_policy::current();
        }
        return $preview;
    }

    /**
     * @param array $arguments Tool arguments.
     * @param authenticated_identity $identity Actor.
     * @return array
     * @throws api_exception
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');
        $this->validate_password_request($arguments);
        $user = (object)[
            'username' => clean_param($arguments['username'], PARAM_USERNAME),
            'firstname' => clean_param($arguments['firstname'], PARAM_TEXT),
            'lastname' => clean_param($arguments['lastname'], PARAM_TEXT),
            'email' => clean_param($arguments['email'], PARAM_EMAIL),
            'auth' => clean_param($arguments['auth'] ?? 'manual', PARAM_PLUGIN),
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
        ];
        $withpassword = array_key_exists('password', $arguments);
        if ($withpassword) {
            $user->password = $arguments['password'];
        }
        $transaction = $DB->start_delegated_transaction();
        if (method_exists(\core\user::class, 'create_user')) {
            $id = \core\user::create_user($user, true, false);
        } else {
            $id = user_create_user($user, true, false);
        }
        if ($withpassword) {
            $saved = $DB->get_record('user', ['id' => (int)$id],
                'id,password', MUST_EXIST);
            if (!validate_internal_user_password($saved, $arguments['password'])) {
                throw new api_exception('password_update_not_persisted', 500,
                    'The new account password could not be verified after saving.');
            }
        }
        $transaction->allow_commit();
        return [
            'id' => (int)$id,
            'username' => $user->username,
            'email' => $user->email,
            'password_set' => $withpassword,
        ];
    }
}
