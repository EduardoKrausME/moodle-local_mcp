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
 * local_mcp.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$string['audit'] = 'Audit';
$string['authorizationdeniedbody'] = 'You do not have permission to connect this Moodle to external applications. Only a Moodle administrator can authorize an MCP connection.';
$string['authorizationdeniedtitle'] = 'Connection not authorized';
$string['authorize'] = 'Authorize connection';
$string['authorizeheading'] = 'Connect {$a} to Moodle';
$string['authorizeintro'] = '{$a} is requesting access to this Moodle.';
$string['cachedef_ratelimit'] = 'Rate limit cache';
$string['cachedef_tooldefinitions'] = 'Tool definitions cache';
$string['cancel'] = 'Cancel';
$string['connections'] = 'Connections';
$string['dashboard'] = 'Dashboard';
$string['manualtokencreated'] = 'Manual token created. Copy the secret now; it will not be shown again.';
$string['manualtokens'] = 'Manual tokens';
$string['mcp:manage'] = 'Manage Moodle MCP';
$string['oauthclients'] = 'OAuth clients';
$string['pluginname'] = 'Moodle MCP';
$string['privacy:metadata:local_mcp_audit'] = 'Stores security and operation metadata for MCP activity.';
$string['privacy:metadata:local_mcp_connections'] = 'Stores authorized MCP connections.';
$string['readapis'] = 'READ APIs';
$string['readpermissions'] = 'Read permissions';
$string['settings'] = 'Settings';
$string['subplugintype_mcptool'] = 'MCP activity tool';
$string['subplugintype_mcptool_plural'] = 'MCP activity tools';
$string['writeapis'] = 'WRITE APIs';
$string['writepermissions'] = 'Write permissions';
