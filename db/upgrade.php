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

/**
 * Upgrade the MCP plugin schema.
 *
 * @param int $oldversion Previously installed plugin version.
 * @return bool
 */
function xmldb_local_mcp_upgrade(int $oldversion): bool {
    global $DB;

    if ($oldversion < 2026101104) {
        // Manual-token authentication is no longer supported. Remove its legacy
        // credentials from existing installations after upgrading to OAuth-only.
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_mcp_manual_token');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        upgrade_plugin_savepoint(true, 2026101104, 'local', 'mcp');
    }

    return true;
}
