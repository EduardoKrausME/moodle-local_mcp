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
 * clients.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);
require_capability("local/mcp:manage", $context);
if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST") {
    require_sesskey();
    $action = required_param("action", PARAM_ALPHA);
    $clientid = required_param("clientid", PARAM_INT);
    if ($action !== "disable" || $clientid <= 0) {
        throw new moodle_exception("invalidrequest", "error");
    }
    \local_mcp\oauth\client_service::disable($clientid);
    redirect(new moodle_url("/local/mcp/admin/clients.php"), get_string("oauthclientdisabled", "local_mcp"));
}
// Limit the administrative list to one page of OAuth clients at a time.
$page = max(0, optional_param('page', 0, PARAM_INT));
$perpage = 50;
$totalclients = $DB->count_records('local_mcp_oauth_client');
$page = $totalclients > 0 ? min($page, intdiv($totalclients - 1, $perpage)) : 0;
$PAGE->set_url(new moodle_url('/local/mcp/admin/clients.php', ['page' => $page]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('oauthclients', 'local_mcp'));
$PAGE->set_heading(get_string('oauthclients', 'local_mcp'));
global $DB;
$yes = get_string("yes");
$no = get_string("no");
$rows = [];
$clients = $DB->get_recordset('local_mcp_oauth_client', null, 'timecreated DESC, id DESC',
    'id, enabled, clientid, name, clienttype, dynamic, timecreated, lastused', $page * $perpage, $perpage);
try {
    foreach ($clients as $client) {
        $rows[] = [
            "id" => (int)$client->id,
            "active" => (bool)$client->enabled,
            "clientid" => s($client->clientid),
            "name" => s($client->name),
            "type" => s($client->clienttype),
            "dynamic" => $client->dynamic ? $yes : $no,
            "enabled" => $client->enabled ? $yes : $no,
            "created" => userdate($client->timecreated),
            "lastused" => $client->lastused ? userdate($client->lastused) : "-",
        ];
    }
} finally {
    $clients->close();
}
echo $OUTPUT->header();
echo html_writer::link(new moodle_url('/local/mcp/admin/dashboard.php'),
    get_string('backtodashboard', 'local_mcp'), ['class' => 'btn btn-secondary mb-3']);
echo $OUTPUT->paging_bar($totalclients, $page, $perpage, $PAGE->url);
echo $OUTPUT->render_from_template("local_mcp/clients", [
    "rows" => $rows,
    "actionurl" => (new moodle_url("/local/mcp/admin/clients.php"))->out(false),
    "sesskey" => sesskey(),
]);
echo $OUTPUT->paging_bar($totalclients, $page, $perpage, $PAGE->url);
echo $OUTPUT->footer();
