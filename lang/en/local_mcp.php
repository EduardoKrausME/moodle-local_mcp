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
$string['auditclient'] = 'OAuth client';
$string['auditconfirmed'] = 'Confirmed';
$string['auditconnection'] = 'Connection ID';
$string['auditduration'] = 'Duration (ms)';
$string['auditevent'] = 'Event';
$string['auditexport'] = 'Export filtered CSV (up to 5,000 rows)';
$string['auditnone'] = 'No matching audit records.';
$string['auditobjects'] = 'Field names / entity IDs / counts';
$string['auditonlychatgpt'] = 'ChatGPT connections only';
$string['auditprivacy'] = 'Records MCP tool calls, confirmations, failures and safe entity IDs. It does not store conversation text, credentials or content values. Only actions sent to this Moodle MCP server can be audited.';
$string['auditrequestid'] = 'Request ID';
$string['auditresults'] = 'Matching audit records: {$a}';
$string['auditside'] = 'Side';
$string['auditstatus'] = 'Status';
$string['audittool'] = 'Tool';
$string['audituser'] = 'User';
$string['audituserid'] = 'Moodle user ID';
$string['auditwhen'] = 'When';
$string['authorizationdeniedbody'] = 'You do not have permission to connect this Moodle to external applications. Only a Moodle administrator can authorize an MCP connection.';
$string['authorizationdeniedtitle'] = 'Connection not authorized';
$string['authorize'] = 'Authorize connection';
$string['authorizeheading'] = 'Connect {$a} to Moodle';
$string['authorizeintro'] = '{$a} is requesting access to this Moodle.';
$string['backtodashboard'] = 'Back to Dashboard';
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
$string['connectionguideacknowledgement'] = 'I understand and want to continue';
$string['connectionguideadvanced'] = 'Advanced OAuth settings';
$string['connectionguideadvancedhelp'] = 'Normally leave automatic discovery selected. If the OAuth panel asks for a client registration method, use dynamic client registration. Enable it in Moodle MCP settings if disabled.';
$string['connectionguideadvancedpreview'] = 'Keep automatic OAuth discovery and dynamic client registration enabled.';
$string['connectionguideauthentication'] = 'Authentication';
$string['connectionguideconnected'] = 'An active ChatGPT OAuth connection was detected. You can use these instructions to configure another connection.';
$string['connectionguideconnection'] = 'Connection';
$string['connectionguidecreate'] = 'Create as plugin';
$string['connectionguidecreatehelp'] = 'Click Create as plugin in ChatGPT, complete the installation, then sign in to Moodle and approve the requested READ/WRITE permissions.';
$string['connectionguidedescription'] = 'Description (optional)';
$string['connectionguidedescriptionexample'] = 'Access Moodle courses, activities and tools through ChatGPT.';
$string['connectionguidedescriptionhelp'] = 'Describe what the server does. For example: Access courses, users and activities in Moodle.';
$string['connectionguidedocumentation'] = 'Read the full connection guide';
$string['connectionguideeyebrow'] = 'Moodle MCP · ChatGPT setup';
$string['connectionguidefinalbody'] = 'Open a chat with the new plugin, connect through OAuth and authorize access in Moodle. You can review authorized OAuth clients and connections from the Moodle MCP admin area.';
$string['connectionguidefinaltitle'] = 'After creating the plugin';
$string['connectionguideformtitle'] = 'Add custom MCP server';
$string['connectionguideicon'] = 'Icon (optional)';
$string['connectionguideiconhelp'] = 'Click the plus sign in ChatGPT and upload your own PNG icon. Use a square icon of at least 256 × 256 pixels and compress it to 10 KB or less. The icon is optional, but helps identify your Moodle.';
$string['connectionguideiconhint'] = 'PNG · 256 × 256 px or more · up to 10 KB';
$string['connectionguideinstructionsintro'] = 'Match each numbered field to the sample on the left.';
$string['connectionguideintro'] = 'Follow the fields shown in ChatGPT\'s Add custom MCP server dialog. This page is an illustrated guide; create the plugin in ChatGPT.';
$string['connectionguidename'] = 'Name';
$string['connectionguidenamehelp'] = 'Enter Moodle MCP, or a distinctive name such as Moodle MCP – My School.';
$string['connectionguideoauthhelp'] = 'Choose OAuth. This Moodle MCP uses the OAuth authorization flow; no manual token or No authentication option is needed.';
$string['connectionguidepreviewnotice'] = 'Example of the fields in ChatGPT (not an active form).';
$string['connectionguidesafety'] = 'Security confirmation';
$string['connectionguidesafetyhelp'] = 'Read the security warning and select I understand and want to continue only if you trust this Moodle server.';
$string['connectionguidesteps'] = 'What to fill in each field';
$string['connectionguidetitle'] = 'Connect your Moodle to ChatGPT';
$string['connectionguidetunnelmode'] = 'Tunnel';
$string['connectionguideurlhelp'] = 'Select Server URL (not Tunnel). Copy the address from the sample and paste it in ChatGPT. Use the HTTPS URL ending in /local/mcp/server.php, without additional parameters.';
$string['connectionguideurlmode'] = 'Server URL';
$string['connections'] = 'Connections';
$string['dashboard'] = 'Dashboard';
$string['dashboardactivitychart'] = 'READ and WRITE calls by day';
$string['dashboardactivityheading'] = 'Tool activity';
$string['dashboardactivityhelp'] = 'Last 7 days, in your timezone. Calls include successful executions, previews, pending confirmations and errors; only completed WRITE operations modify data.';
$string['dashboardcalls'] = 'Calls';
$string['dashboardclients'] = 'Active OAuth clients';
$string['dashboardconnections'] = 'Active connections';
$string['dashboarddestructivetoday'] = 'Destructive WRITE executed today';
$string['dashboardexamples'] = 'Example APIs';
$string['dashboardmoretools'] = '+{$a} more';
$string['dashboardnoactivity'] = 'No MCP tool calls were audited in the last 7 days.';
$string['dashboardoverview'] = 'MCP overview';
$string['dashboardplugin'] = 'Plugin';
$string['dashboardpluginschart'] = 'Available WRITE APIs by plugin';
$string['dashboardquicklinks'] = 'Dashboard quick links';
$string['dashboardreadtoday'] = 'READ calls today';
$string['dashboardstatuschart'] = 'Call statuses (last 7 days)';
$string['dashboardstatuserror'] = 'Error';
$string['dashboardstatusok'] = 'Completed successfully';
$string['dashboardstatuspending'] = 'Awaiting confirmation';
$string['dashboardstatuspreview'] = 'Dry-run';
$string['dashboardtodaydescription'] = 'Current connection status and today\'s audited tool activity.';
$string['dashboardtools'] = 'Tools';
$string['dashboardtotaltools'] = 'Available WRITE APIs';
$string['dashboardviewall'] = 'View all WRITE APIs';
$string['dashboardwritecompletedtoday'] = 'WRITE operations completed today';
$string['dashboardwritesummary'] = 'WRITE APIs by plugin';
$string['dashboardwritesummaryhelp'] = 'Discovered tools from the active WRITE registry, grouped by their Moodle component.';
$string['dashboardwritetoday'] = 'WRITE calls today';
$string["importgiftquestionbank"] = "Import GIFT question bank";
$string["importgiftquestionbank_desc"] = "Import up to 500 UTF-8 GIFT questions into an existing category (categoryid) or create a new one (name), in one confirmed operation.";
$string['mcp:manage'] = 'Manage Moodle MCP';
$string['newconnection'] = 'New connection';
$string['oauthclients'] = 'OAuth clients';
$string['oauthconsentapplication'] = 'External application';
$string['oauthconsentconnection'] = 'Connection between the external application and this Moodle site';
$string['oauthconsentdestination'] = 'Your Moodle site';
$string['oauthconsenteyebrow'] = 'OAuth authorization request';
$string['oauthconsentfooter'] = 'Only authorize applications you recognize and trust.';
$string['oauthconsentpermissions'] = 'Requested permissions';
$string['oauthconsentpermissionsintro'] = 'Review what this application will be allowed to do on your behalf.';
$string['oauthconsentreadsummary'] = 'View information available through Moodle MCP read-only tools.';
$string['oauthconsentsecuritybody'] = 'This connection cannot exceed the permissions of your Moodle account. Some sensitive operations require additional confirmation. You can revoke access later.';
$string['oauthconsentsecuritytitle'] = 'Your Moodle permissions still apply';
$string['oauthconsenttitle'] = 'Authorize a new connection';
$string['oauthconsentwritesummary'] = 'Create, edit or delete data using the Moodle MCP write tools available to your account.';
$string['pluginname'] = 'Moodle MCP';
$string['privacy:metadata:local_mcp_audit'] = 'Stores security and operation metadata for MCP activity.';
$string['privacy:metadata:local_mcp_connections'] = 'Stores authorized MCP connections.';
$string['readapis'] = 'READ APIs';
$string['readapiscount'] = 'READ tools: {$a}';
$string['readapisempty'] = 'No READ tools available.';
$string['readapisviewall'] = 'View all READ tools in this plugin';
$string['readpermissions'] = 'Read permissions';
$string['settings'] = 'Settings';
$string['subplugintype_mcptool'] = 'MCP activity tool';
$string['subplugintype_mcptool_plural'] = 'MCP activity tools';
$string['writeapis'] = 'WRITE APIs';
$string['writeapiscolcapability'] = 'Capability';
$string['writeapiscolconfirmation'] = 'Confirmation';
$string['writeapiscoldescription'] = 'Description';
$string['writeapiscoldestructive'] = 'Destructive';
$string['writeapiscoldryrun'] = 'Dry-run';
$string['writeapiscolname'] = 'Name';
$string['writeapiscount'] = 'WRITE tools: {$a}';
$string['writeapisempty'] = 'No WRITE tools available.';
$string['writeapisonlyread'] = 'Installed plugins with READ-only APIs';
$string['writeapisonlyreadhelp'] = 'These integrations are available through MCP READ, not WRITE. Open their READ API lists to see every tool. Installation order does not change the API type.';
$string['writepermissions'] = 'Write permissions';
