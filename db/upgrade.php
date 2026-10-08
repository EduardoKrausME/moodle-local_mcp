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
 * Database upgrades for audience-bound MCP access tokens.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_mcp\security\rate_limiter;

defined('MOODLE_INTERNAL') || die;

/**
 * Upgrade the MCP plugin schema.
 *
 * @param int $oldversion Previously installed plugin version.
 * @return bool
 */
function xmldb_local_mcp_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();
    if ($oldversion < 2026100800) {
        foreach (['local_mcp_auth_code', 'local_mcp_access_token', 'local_mcp_refresh_token'] as $name) {
            $table = new xmldb_table($name);
            $field = new xmldb_field('resource', XMLDB_TYPE_TEXT, null, null, null, null, null, 'scopes');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2026100800, 'local', 'mcp');
    }
    if ($oldversion < 2026100811) {
        // Raise only the former defaults, leaving other configured values intact.
        $defaults = [
            'rateread' => [120, rate_limiter::DEFAULT_READ_PER_MINUTE],
            'ratewrite' => [30, rate_limiter::DEFAULT_WRITE_PER_MINUTE],
            'rateoauth' => [30, rate_limiter::DEFAULT_OAUTH_PER_MINUTE],
        ];
        foreach ($defaults as $name => [$olddefault, $newdefault]) {
            $configured = get_config('local_mcp', $name);
            if ($configured !== false && (int)$configured === $olddefault) {
                set_config($name, $newdefault, 'local_mcp');
            }
        }
        upgrade_plugin_savepoint(true, 2026100811, 'local', 'mcp');
    }
    return true;
}
