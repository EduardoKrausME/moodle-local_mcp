<?php
namespace local_mcp\privacy;

defined('MOODLE_INTERNAL') || die;

final class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider {

    public static function get_metadata(\core_privacy\local\metadata\collection $collection): \core_privacy\local\metadata\collection {
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

    public static function get_contexts_for_userid(int $userid): \core_privacy\local\request\contextlist {
        $list = new \core_privacy\local\request\contextlist();
        global $DB;
        if ($DB->record_exists('local_mcp_connection', ['userid' => $userid])
                || $DB->record_exists('local_mcp_audit', ['userid' => $userid])) {
            $list->add_system_context();
        }
        return $list;
    }

    public static function export_user_data(\core_privacy\local\request\approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $context = \context_system::instance();
        if (!in_array($context->id, $contextlist->get_contextids(), true)) {
            return;
        }
        $data = (object)[
            'connections' => array_values($DB->get_records('local_mcp_connection', ['userid' => $userid])),
            'audit' => array_values($DB->get_records('local_mcp_audit', ['userid' => $userid])),
        ];
        \core_privacy\local\request\writer::with_context($context)->export_data([], $data);
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $DB->delete_records('local_mcp_audit');
    }

    public static function delete_data_for_user(\core_privacy\local\request\approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $DB->delete_records('local_mcp_audit', ['userid' => $userid]);
    }
}
