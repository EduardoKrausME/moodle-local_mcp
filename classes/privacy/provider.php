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
 * Privacy metadata, export and erasure for Moodle MCP.
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
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for user-bound MCP connections, OAuth credentials and audit records.
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Tables are ordered so credentials are removed before their connections.
     */
    private const USER_TABLES = [
        "local_mcp_confirm",
        "local_mcp_access_token",
        "local_mcp_refresh_token",
        "local_mcp_auth_code",
        "local_mcp_connection",
        "local_mcp_audit",
    ];

    /**
     * Describe all locally stored personal data and the external MCP destination.
     *
     * @param collection $collection Privacy metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $fields = [
            "local_mcp_connection" => [
                "id", "userid", "clientid", "scopes", "enabled", "timecreated",
                "lastused", "lastip", "revokedat",
            ],
            "local_mcp_audit" => [
                "id", "connectionid", "clientid", "userid", "event", "tool", "side",
                "destructive", "contextid", "ip", "durationms", "status", "dryrun",
                "confirmation", "metadata", "timecreated",
            ],
            "local_mcp_auth_code" => [
                "id", "prefix", "codehash", "clientid", "userid", "redirecturi",
                "scopes", "resource", "codechallenge", "challengemethod",
                "timecreated", "expires", "used",
            ],
            "local_mcp_access_token" => [
                "id", "prefix", "tokenhash", "userid", "clientid", "connectionid",
                "scopes", "resource", "family", "issuedat", "expiresat",
                "revokedat", "lastused", "lastip",
            ],
            "local_mcp_refresh_token" => [
                "id", "prefix", "tokenhash", "userid", "clientid", "connectionid",
                "scopes", "resource", "family", "generation", "issuedat",
                "expiresat", "usedat", "revokedat",
            ],
            "local_mcp_confirm" => [
                "id", "prefix", "tokenhash", "userid", "clientid", "connectionid",
                "accesshash", "tool", "argshash", "contextid", "expires",
                "used", "timecreated",
            ],
        ];
        foreach ($fields as $table => $columns) {
            $key = match ($table) {
                "local_mcp_connection" => "privacy:metadata:local_mcp_connections",
                "local_mcp_audit" => "privacy:metadata:local_mcp_audit",
                "local_mcp_auth_code" => "privacy:metadata:local_mcp_auth_code",
                "local_mcp_access_token" => "privacy:metadata:local_mcp_access_token",
                "local_mcp_refresh_token" => "privacy:metadata:local_mcp_refresh_token",
                "local_mcp_confirm" => "privacy:metadata:local_mcp_confirm",
            };
            $collection->add_database_table($table, array_fill_keys($columns, $key), $key);
        }

        // Each authorized MCP client (including ChatGPT) receives tool responses outside Moodle.
        $collection->add_external_location_link("mcp_client", [
            "userid" => "privacy:metadata:mcp_client:userid",
            "userprofile" => "privacy:metadata:mcp_client:userprofile",
            "courses" => "privacy:metadata:mcp_client:courses",
            "activities" => "privacy:metadata:mcp_client:activities",
            "grades" => "privacy:metadata:mcp_client:grades",
            "otherdata" => "privacy:metadata:mcp_client:otherdata",
        ], "privacy:metadata:mcp_client");

        return $collection;
    }

    /**
     * Identify the system context even when a user has only an OAuth token or confirmation.
     *
     * @param int $userid Moodle user ID.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $list = new contextlist();
        foreach (self::USER_TABLES as $table) {
            if ($DB->record_exists($table, ["userid" => $userid])) {
                $list->add_system_context();
                break;
            }
        }
        return $list;
    }

    /**
     * Identify users who have MCP data in the system context.
     *
     * @param userlist $userlist Requested user list.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        foreach (self::USER_TABLES as $table) {
            $userlist->add_from_sql("userid", "SELECT userid FROM {{$table}} WHERE userid IS NOT NULL", []);
        }
    }

    /**
     * Export the user's connections, audit and OAuth records, excluding credential material.
     *
     * @param approved_contextlist $contextlist Approved context list.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $context = context_system::instance();
        if (!self::has_system_context($contextlist)) {
            return;
        }
        $userid = (int)$contextlist->get_user()->id;
        $exports = [
            "connections" => "local_mcp_connection",
            "audit" => "local_mcp_audit",
            "authorization_codes" => "local_mcp_auth_code",
            "access_tokens" => "local_mcp_access_token",
            "refresh_tokens" => "local_mcp_refresh_token",
            "confirmations" => "local_mcp_confirm",
        ];
        $data = new \stdClass();
        foreach ($exports as $name => $table) {
            $records = array_values($DB->get_records($table, ["userid" => $userid]));
            foreach ($records as $record) {
                // Credential digests, prefixes, PKCE challenges and argument hashes are not safe exports.
                unset($record->prefix, $record->tokenhash, $record->codehash,
                    $record->codechallenge, $record->accesshash, $record->argshash);
            }
            $data->{$name} = $records;
        }
        writer::with_context($context)->export_data([], $data);
    }

    /**
     * Erase user-related MCP data when the system context expires.
     *
     * @param context $context Context to erase.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $transaction = $DB->start_delegated_transaction();
        foreach (self::USER_TABLES as $table) {
            $DB->delete_records($table);
        }
        $transaction->allow_commit();
    }

    /**
     * Erase an individual's records only in an approved system context.
     *
     * @param approved_contextlist $contextlist Approved contexts and user.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        if (!self::has_system_context($contextlist)) {
            return;
        }
        self::delete_user_records([(int)$contextlist->get_user()->id]);
    }

    /**
     * Erase the records for multiple approved users in the system context.
     *
     * @param approved_userlist $userlist Approved user list.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        self::delete_user_records($userlist->get_userids());
    }

    /**
     * Check approved context IDs, which may be returned as numeric strings by DML.
     *
     * @param approved_contextlist $contextlist Approved user contexts.
     * @return bool
     */
    private static function has_system_context(approved_contextlist $contextlist): bool {
        return in_array(
            (int)context_system::instance()->id,
            array_map("intval", $contextlist->get_contextids()),
            true
        );
    }

    /**
     * Remove credentials, connections and audit records for the requested user IDs.
     *
     * @param int[] $userids User IDs to erase.
     * @return void
     */
    private static function delete_user_records(array $userids): void {
        global $DB;

        if (!$userids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, "mcpuserid");
        $transaction = $DB->start_delegated_transaction();
        foreach (self::USER_TABLES as $table) {
            $DB->delete_records_select($table, "userid {$insql}", $params);
        }
        $transaction->allow_commit();
    }
}
