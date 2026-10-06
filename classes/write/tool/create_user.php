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
use local_mcp\security\authenticated_identity;

/**
 * Class create_user.
 */
final class create_user extends base_tool {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string {
        return 'create_user';
    }

    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string {
        return 'Create user';
    }

    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string {
        return 'Create a Moodle user using the core user API.';
    }

    /**
     * Method get_required_capability.
     *
     * @return string Return value.
     */
    public function get_required_capability(): string {
        return 'moodle/user:create';
    }

    /**
     * Method get_input_schema.
     *
     * @return array Return value.
     */
    public function get_input_schema(): array {
        return $this->object_schema([
            'username' => ['type' => 'string'], 'firstname' => ['type' => 'string'], 'lastname' => ['type' => 'string'], 'email' => ['type' => 'string'], 'auth' => ['type' => 'string']
        ], ['username', 'firstname', 'lastname', 'email']);
    }

    /**
     * Method resolve_context.
     *
     * @param array $arguments Parameter arguments.
     * @return context Return value.
     */
    public function resolve_context(array $arguments): context {
        return context_system::instance();
    }

    /**
     * Method execute.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        global $CFG;
        require_once($CFG->dirroot . '/user/lib.php');
        $user = (object)[
            'username' => clean_param($arguments['username'], PARAM_USERNAME),
            'firstname' => clean_param($arguments['firstname'], PARAM_TEXT),
            'lastname' => clean_param($arguments['lastname'], PARAM_TEXT),
            'email' => clean_param($arguments['email'], PARAM_EMAIL),
            'auth' => clean_param($arguments['auth'] ?? 'manual', PARAM_PLUGIN),
            'confirmed' => 1, 'mnethostid' => $CFG->mnet_localhost_id
        ];
        $id = user_create_user($user, true, false);
        return ['id' => (int)$id, 'username' => $user->username, 'email' => $user->email];
    }
}
