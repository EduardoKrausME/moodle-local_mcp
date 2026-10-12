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

    if ($oldversion < 2026101203) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_mcp_upload');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('tokenhash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('clientid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('connectionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('filename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
            $table->add_field('filesize', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('expiresat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('tokenhash_uix', XMLDB_INDEX_UNIQUE, ['tokenhash']);
            $table->add_index('owner_ix', XMLDB_INDEX_NOTUNIQUE, ['userid', 'connectionid']);
            $table->add_index('expires_ix', XMLDB_INDEX_NOTUNIQUE, ['expiresat']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026101203, 'local', 'mcp');
    }

    return true;
}
