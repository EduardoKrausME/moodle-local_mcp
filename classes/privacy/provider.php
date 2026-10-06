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
 * provider.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\privacy;

use context;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die;

/**
 * Class provider.
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Method get_metadata.
     *
     * @param collection $collection Parameter collection.
     * @return collection Return value.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_mcp_connection', [
            'userid' => 'privacy:metadata:local_mcp_connections',
            'scopes' => 'privacy:metadata:local_mcp_connections',
            'lastused' => 'privacy:metadata:local_mcp_connections',
            'lastip' => 'privacy:metadata:local_mcp_connections',
        ], 'privacy:metadata:local_mcp_connections');
        $collection->add_database_table('local_mcp_audit', [
            'userid' => 'privacy:metadata:local_mcp_audit',
            'tool' => 'privacy:metadata:local_mcp_audit',
            'ip' => 'privacy:metadata:local_mcp_audit',
            'timecreated' => 'privacy:metadata:local_mcp_audit',
        ], 'privacy:metadata:local_mcp_audit');
        return $collection;
    }

    /**
     * Method get_contexts_for_userid.
     *
     * @param int $userid Parameter userid.
     * @return contextlist Return value.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        global $DB;
        if ($DB->record_exists('local_mcp_connection', ['userid' => $userid])
            || $DB->record_exists('local_mcp_audit', ['userid' => $userid])) {
            $list->add_system_context();
        }
        return $list;
    }

    /**
     * Method export_user_data.
     *
     * @param approved_contextlist $contextlist Parameter contextlist.
     * @return void Return value.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $context = context_system::instance();
        if (!in_array($context->id, $contextlist->get_contextids(), true)) {
            return;
        }
        $data = (object)[
            'connections' => array_values($DB->get_records('local_mcp_connection', ['userid' => $userid])),
            'audit' => array_values($DB->get_records('local_mcp_audit', ['userid' => $userid])),
        ];
        writer::with_context($context)->export_data([], $data);
    }

    /**
     * Method delete_data_for_all_users_in_context.
     *
     * @param context $context Parameter context.
     * @return void Return value.
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $DB->delete_records('local_mcp_audit');
    }

    /**
     * Method delete_data_for_user.
     *
     * @param approved_contextlist $contextlist Parameter contextlist.
     * @return void Return value.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $DB->delete_records('local_mcp_audit', ['userid' => $userid]);
    }
}
