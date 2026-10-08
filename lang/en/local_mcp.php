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
$string['chatgptsetupcopied'] = 'Server URL copied.';
$string['chatgptsetupcopy'] = 'Copy URL';
$string['chatgptsetupcopyfallback'] = 'Select and copy the highlighted URL.';
$string['chatgptsetupdcrdisabled'] = 'Dynamic OAuth client registration is disabled. Enable it before connecting ChatGPT.';
$string['chatgptsetupdiscoveryhelp'] = 'If ChatGPT reports an OAuth discovery error, verify that the web server routes this RFC 8414 address to the Moodle MCP authorization-server metadata endpoint:';
$string['chatgptsetupfinish'] = 'After completing authorization, reload this page. This guide automatically disappears as soon as an active ChatGPT connection is detected.';
$string['chatgptsetuphttpsdisabled'] = 'This Moodle site is not configured with an HTTPS URL. ChatGPT requires an internet-accessible HTTPS MCP endpoint.';
$string['chatgptsetupintro'] = 'There are no active ChatGPT connections to this Moodle site. Follow these steps to add the MCP server to ChatGPT.';
$string['chatgptsetupnewtab'] = 'opens in a new tab';
$string['chatgptsetupopenchatgpt'] = 'Open ChatGPT';
$string['chatgptsetupopensettings'] = 'Open Moodle MCP settings';
$string['chatgptsetupserverlabel'] = 'MCP server URL';
$string['chatgptsetupstep1body'] = 'Click the button below to open ChatGPT in a new tab and sign in.';
$string['chatgptsetupstep1title'] = 'Open ChatGPT';
$string['chatgptsetupstep2body'] = 'In ChatGPT on the web, open Plugins, click +, then select Add custom MCP server. Name it Moodle MCP and choose OAuth authentication with dynamic client registration (DCR).';
$string['chatgptsetupstep2title'] = 'Add a custom MCP server';
$string['chatgptsetupstep3body'] = 'Paste the following address into the Server URL field in ChatGPT:';
$string['chatgptsetupstep3title'] = 'Enter this Moodle server URL';
$string['chatgptsetupstep4body'] = 'Review the security warning, select I understand and want to continue, and click Create as a plugin. Install the created plugin if prompted.';
$string['chatgptsetupstep4title'] = 'Create and install the plugin';
$string['chatgptsetupstep5body'] = 'Open a chat and use the Moodle MCP plugin. When asked to connect, sign in to Moodle as a site administrator and approve the requested read/write permissions.';
$string['chatgptsetupstep5title'] = 'Authorize access to Moodle';
$string['chatgptsetuptitle'] = 'Connect your Moodle site to ChatGPT';
$string['chatgptsetuptroubleshooting'] = 'Troubleshooting OAuth discovery';
$string['connections'] = 'Connections';
$string['dashboard'] = 'Dashboard';
$string['manualtokencreated'] = 'Manual token created. Copy the secret now; it will not be shown again.';
$string['manualtokens'] = 'Manual tokens';
$string['mcp:manage'] = 'Manage Moodle MCP';
$string['newconnection'] = 'New connection';
$string['oauthclients'] = 'OAuth clients';
$string['pluginname'] = 'Moodle MCP';
$string['privacy:metadata:local_mcp_audit'] = 'Stores security and operation metadata for MCP activity.';
$string['privacy:metadata:local_mcp_connections'] = 'Stores authorized MCP connections.';
$string['readapis'] = 'READ APIs';
$string['readpermissions'] = 'Read permissions';
$string['settings'] = 'Settings';
$string['subplugintype_mcptool'] = 'MCP activity tool';
$string['subplugintype_mcptool_plural'] = 'MCP activity tools';
$string['testconnectionactive'] = 'Active';
$string['testconnectioncreate'] = 'Create test connection';
$string['testconnectioncreated'] = 'Test connection created';
$string['testconnectioneditintro'] = 'Change READ and WRITE access for this existing connection. At least one permission must remain selected.';
$string['testconnectioneditpermissions'] = 'Edit permissions';
$string['testconnectioneditwarning'] = 'The token and MCP server URL will not change. Permissions take effect on the next request. Reconnect or refresh the ChatGPT plugin to reload the available tools.';
$string['testconnectionempty'] = 'No test connections have been created.';
$string['testconnectionexpireslabel'] = 'Expires';
$string['testconnectionformintro'] = 'Create a temporary connection without OAuth. The server URL will contain a unique token.';
$string['testconnectionhowtotitle'] = 'Connect ChatGPT without OAuth';
$string['testconnectioninactive'] = 'Inactive or expired';
$string['testconnectionname'] = 'Connection name';
$string['testconnectionoauthheading'] = 'Previous OAuth connections';
$string['testconnectionoauthnone'] = 'No OAuth connections.';
$string['testconnectiononeview'] = 'Copy this URL now. The token is only shown once and cannot be recovered later.';
$string['testconnectionpermissions'] = 'READ lets ChatGPT inspect Moodle. Enable WRITE if you also want ChatGPT to modify content. All writes continue to require tool confirmation.';
$string['testconnectionpermissionslabel'] = 'Permissions';
$string['testconnectionrevoke'] = 'Revoke';
$string['testconnectionsavepermissions'] = 'Save permissions';
$string['testconnectionserverurl'] = 'MCP Server URL';
$string['testconnectionsheading'] = 'Test connections without OAuth';
$string['testconnectionstatus'] = 'Status';
$string['testconnectionstep1'] = 'Open';
$string['testconnectionstep2'] = 'Go to Plugins and add a custom MCP server.';
$string['testconnectionstep3'] = 'Paste the URL above into the MCP Server URL field and choose No authentication. Do not configure OAuth.';
$string['testconnectionstep4'] = 'Create and enable the plugin. It will use the token embedded in the URL.';
$string['testconnectionuser'] = 'User';
$string['testconnectionwarning'] = 'TEST ONLY: the URL includes a secret token. Anyone with this URL can use its permissions until expiry or revocation. Query strings can be recorded in proxy and server logs. Do not share it; use HTTPS. This token expires in seven days.';
$string['writeapis'] = 'WRITE APIs';
$string['writepermissions'] = 'Write permissions';
