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
 * search_users.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\read\tool;

use context;
use context_system;
use local_mcp\security\authenticated_identity;

defined('MOODLE_INTERNAL') || die;

/**
 * Class search_users.
 */
final class search_users extends base_tool {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string {
        return 'search_users';
    }

    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string {
        return 'Search users';
    }

    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string {
        return 'Search Moodle user accounts.';
    }

    /**
     * Method get_required_capability.
     *
     * @return string Return value.
     */
    public function get_required_capability(): string {
        return 'moodle/user:viewdetails';
    }

    /**
     * Method get_input_schema.
     *
     * @return array Return value.
     */
    public function get_input_schema(): array {
        return $this->object_schema(['query' => ['type' => 'string'], 'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]], ['query']);
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
        global $DB;
        $needle = '%' . $DB->sql_like_escape(trim((string)$arguments['query'])) . '%';
        $where = 'deleted = 0 AND (' . $DB->sql_like('firstname', ':q1', false) . ' OR ' .
            $DB->sql_like('lastname', ':q2', false) . ' OR ' . $DB->sql_like('email', ':q3', false) . ')';
        $users = $DB->get_records_select('user', $where, ['q1' => $needle, 'q2' => $needle, 'q3' => $needle],
            'lastname,firstname', 'id,firstname,lastname,email,suspended', 0, min(100, (int)($arguments['limit'] ?? 20)));
        $out = [];
        foreach ($users as $u) {
            $out[] = ['id' => (int)$u->id, 'fullname' => fullname($u), 'email' => $u->email, 'suspended' => (bool)$u->suspended];
        }
        return ['users' => $out];
    }
}
