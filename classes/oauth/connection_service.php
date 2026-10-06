<?php
namespace local_mcp\oauth;

defined('MOODLE_INTERNAL') || die;

final class connection_service {
    public static function revoke(int $connectionid): void {
        global $DB;
        $now = time();
        $transaction = $DB->start_delegated_transaction();
        $DB->update_record('local_mcp_connection', (object)[
            'id' => $connectionid,
            'enabled' => 0,
            'revokedat' => $now,
        ]);
        $DB->set_field_select('local_mcp_access_token', 'revokedat', $now,
            'connectionid = ? AND revokedat IS NULL', [$connectionid]);
        $DB->set_field_select('local_mcp_refresh_token', 'revokedat', $now,
            'connectionid = ? AND revokedat IS NULL', [$connectionid]);
        $connection = $DB->get_record('local_mcp_connection', ['id' => $connectionid], '*', MUST_EXIST);
        $DB->delete_records('local_mcp_auth_code', ['clientid' => $connection->clientid, 'userid' => $connection->userid, 'used' => 0]);
        $DB->delete_records('local_mcp_confirm', ['connectionid' => $connectionid, 'used' => 0]);
        $transaction->allow_commit();
        \\local_mcp\\event\\connection_revoked::create([
            'context' => \\context_system::instance(),
            'objectid' => $connectionid,
            'relateduserid' => (int)$connection->userid,
        ])->trigger();
    }
}
