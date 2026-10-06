<?php
namespace local_mcp\task;

defined('MOODLE_INTERNAL') || die;

final class cleanup extends \core\task\scheduled_task {
    public function get_name(): string {
        return 'Moodle MCP token and audit cleanup';
    }

    public function execute(): void {
        global $DB;
        $now = time();
        $DB->delete_records_select('local_mcp_auth_code', 'expires < ? OR used = 1', [$now]);
        $DB->delete_records_select('local_mcp_confirm', 'expires < ? OR used = 1', [$now]);
        $DB->delete_records_select('local_mcp_access_token', 'expiresat < ? AND (revokedat IS NOT NULL OR expiresat < ?)', [$now, $now - DAYSECS]);
        $DB->delete_records_select('local_mcp_refresh_token',
            'expiresat < ? OR (revokedat IS NOT NULL AND revokedat < ?)', [$now, $now - 30 * DAYSECS]);

        $days = max(1, (int)get_config('local_mcp', 'auditretention') ?: 180);
        $DB->delete_records_select('local_mcp_audit', 'timecreated < ?', [$now - $days * DAYSECS]);
    }
}
