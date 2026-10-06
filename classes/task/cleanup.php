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
 * cleanup.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\task;

defined('MOODLE_INTERNAL') || die;

/**
 * Class cleanup.
 */
final class cleanup extends \core\task\scheduled_task {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string {
        return 'Moodle MCP token and audit cleanup';
    }

    /**
     * Method execute.
     *
     * @return void Return value.
     */
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
