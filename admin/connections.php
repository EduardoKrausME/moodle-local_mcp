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
 * connections.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_mcp\oauth\connection_service;
use local_mcp\ui\chatgpt_setup;

require_once(__DIR__ . '/../../../config.php');
require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);
require_capability("local/mcp:manage", $context);
if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST") {
    require_sesskey();
    $action = required_param("action", PARAM_ALPHA);
    $connectionid = required_param("connectionid", PARAM_INT);
    if ($action !== "revoke" || $connectionid <= 0) {
        throw new moodle_exception("invalidrequest", "error");
    }
    connection_service::revoke($connectionid);
    redirect(new moodle_url("/local/mcp/admin/connections.php"), get_string("connectionrevoked", "local_mcp"));
}

$PAGE->set_url('/local/mcp/admin/connections.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('connections', 'local_mcp'));
$PAGE->set_heading(get_string('connections', 'local_mcp'));

$PAGE->add_body_class("mcp-connections-page");

// Always present the onboarding guide, even after a client is connected.
$data = [
    'showchatgptsetup' => !connection_service::has_active_chatgpt_connection(),
    'mcpserverurl' => new moodle_url('/local/mcp/server.php'),
    'settingsurl' => new moodle_url('/admin/settings.php', ['section' => 'local_mcp_settings']),
    'discoveryurl' => new moodle_url('/.well-known/oauth-authorization-server/local/mcp'),
    'registrationdisabled' => !get_config('local_mcp', 'dynamicregistration'),
    'httpsdisabled' => stripos($CFG->wwwroot, 'https://') !== 0,
    'connectionguidenameexample' => "Moodle-{$SITE->shortname}",
    'connectiondocumentationurl' => 'https://eduardokrausme.github.io/moodle-local_mcp/' .
        (strpos(current_language(), 'pt') === 0 ? 'pt_br' : 'en') . '/recursos/conexao-chatgpt.html'
];
echo $OUTPUT->header();
echo html_writer::link(new moodle_url('/local/mcp/admin/dashboard.php'),
    get_string('backtodashboard', 'local_mcp'), ['class' => 'btn btn-secondary mb-3']);
echo $OUTPUT->render_from_template('local_mcp/connections', $data);

// Allow administrators to revoke existing grants without changing OAuth settings.
$sql = "SELECT c.id, c.scopes, c.timecreated, c.lastused,
               u.firstname, u.lastname, cl.name AS clientname
          FROM {local_mcp_connection} c
          JOIN {user} u ON u.id = c.userid
          JOIN {local_mcp_oauth_client} cl ON cl.id = c.clientid
         WHERE c.enabled = :enabled AND c.revokedat IS NULL
      ORDER BY c.timecreated DESC";
$rows = [];
foreach ($DB->get_records_sql($sql, ["enabled" => 1], 0, 100) as $record) {
    $rows[] = [
        "id" => (int)$record->id,
        "user" => fullname($record),
        "client" => $record->clientname,
        "scopes" => $record->scopes,
        "created" => userdate($record->timecreated),
        "lastused" => $record->lastused ? userdate($record->lastused) : "-",
    ];
}
echo $OUTPUT->render_from_template("local_mcp/connections_admin", [
    "rows" => $rows,
    "hasconnections" => !empty($rows),
    "actionurl" => (new moodle_url("/local/mcp/admin/connections.php"))->out(false),
    "sesskey" => sesskey(),
]);
echo $OUTPUT->footer();
