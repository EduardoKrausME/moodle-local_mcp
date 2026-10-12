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
 * Filterable read-only MCP audit timeline.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$side = optional_param('side', '', PARAM_ALPHA);
$status = optional_param('status', '', PARAM_ALPHANUMEXT);
$tool = optional_param('tool', '', PARAM_ALPHANUMEXT);
$userid = optional_param('userid', 0, PARAM_INT);
$page = max(0, optional_param('page', 0, PARAM_INT));
$filtering = optional_param('filtering', 0, PARAM_BOOL);
$onlychatgpt = optional_param('onlychatgpt', $filtering ? 0 : 1, PARAM_BOOL);
$export = optional_param('export', '', PARAM_ALPHA);

if (!in_array($side, ['', 'read', 'write'], true)) {
    $side = '';
}
if (!in_array($status, ['', 'ok', 'preview', 'confirmation_pending', 'error'], true)) {
    $status = '';
}
$filterparams = ['filtering' => 1, 'side' => $side, 'status' => $status,
    'tool' => $tool, 'userid' => $userid, 'onlychatgpt' => $onlychatgpt];
$url = new moodle_url('/local/mcp/admin/audit.php', $filterparams);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('audit', 'local_mcp'));
$PAGE->set_heading(get_string('audit', 'local_mcp'));

global $DB;
$where = ['1 = 1'];
$params = [];
if ($side !== '') {
    $where[] = 'a.side = :side';
    $params['side'] = $side;
}
if ($status !== '') {
    $where[] = 'a.status = :status';
    $params['status'] = $status;
}
if ($tool !== '') {
    $where[] = 'a.tool = :tool';
    $params['tool'] = $tool;
}
if ($userid > 0) {
    $where[] = 'a.userid = :userid';
    $params['userid'] = $userid;
}

// Client names can be arbitrarily renamed. Identify ChatGPT by its OAuth redirect URI.
$chatgptids = [];
$clients = $DB->get_records('local_mcp_oauth_client', null, '', 'id, name, redirecturis');
foreach ($clients as $client) {
    $redirects = json_decode($client->redirecturis, true);
    if (!is_array($redirects)) {
        continue;
    }
    foreach ($redirects as $redirect) {
        if (is_string($redirect) &&
                \local_mcp\oauth\connection_service::is_chatgpt_redirect_uri($redirect)) {
            $chatgptids[] = (int)$client->id;
            break;
        }
    }
}
if ($onlychatgpt) {
    if (!$chatgptids) {
        $where[] = '1 = 0';
    } else {
        [$insql, $inparams] = $DB->get_in_or_equal($chatgptids, SQL_PARAMS_NAMED, 'chatclient');
        $where[] = 'a.clientid ' . $insql;
        $params += $inparams;
    }
}

$from = ' FROM {local_mcp_audit} a
    LEFT JOIN {user} u ON u.id = a.userid
    LEFT JOIN {local_mcp_oauth_client} c ON c.id = a.clientid
    WHERE ' . implode(' AND ', $where);
$total = $DB->count_records_sql('SELECT COUNT(a.id)' . $from, $params);
$pagesize = 50;
$maxrows = $export === 'csv' ? 5000 : $pagesize;
$offset = $export === 'csv' ? 0 : $page * $pagesize;
$records = $DB->get_records_sql('SELECT a.*, u.firstname, u.lastname, c.name AS clientname'
    . $from . ' ORDER BY a.timecreated DESC, a.id DESC', $params, $offset, $maxrows);
$rows = [];
foreach ($records as $record) {
    $metadata = json_decode($record->metadata ?? '', true);
    if (!is_array($metadata)) {
        $metadata = [];
    }
    $requestid = $metadata['request_id'] ?? '';
    unset($metadata['request_id']);
    $clientname = $record->clientname ?? '-';
    if (in_array((int)$record->clientid, $chatgptids, true)) {
        $clientname = 'ChatGPT (' . $clientname . ')';
    }
    $username = trim(fullname((object)['firstname' => $record->firstname ?? '',
        'lastname' => $record->lastname ?? '']));
    $rows[] = [
        'time' => userdate($record->timecreated),
        'event' => $record->event,
        'side' => $record->side ?? '',
        'tool' => $record->tool ?? '',
        'user' => $username !== '' ? $username . ' (#' . $record->userid . ')' : ($record->userid ?: '-'),
        'client' => $clientname,
        'connection' => $record->connectionid ?: '-',
        'status' => $record->status ?? '-',
        'objects' => $metadata ? json_encode($metadata,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) : '-',
        'requestid' => $requestid ?: '-',
        'duration' => $record->durationms === null ? '-' : (string)$record->durationms,
        'confirmation' => $record->confirmation ? get_string('yes') : get_string('no'),
        'ip' => $record->ip ?? '',
    ];
}

// Export selected audit metadata, never raw MCP inputs or responses.
if ($export === 'csv') {
    \core\session\manager::write_close();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="mcp-audit.csv"');
    $output = fopen('php://output', 'w');
    $columns = ['time', 'event', 'side', 'tool', 'user', 'client', 'connection',
        'status', 'objects', 'requestid', 'duration', 'confirmation', 'ip'];
    fputcsv($output, $columns);
    foreach ($rows as $row) {
        $cells = [];
        foreach ($columns as $column) {
            $value = (string)$row[$column];
            // Protect spreadsheet users against CSV formula injection.
            if (preg_match('/^[=+\-@\t\r]/', $value)) {
                $value = "'" . $value;
            }
            $cells[] = $value;
        }
        fputcsv($output, $cells);
    }
    fclose($output);
    exit;
}

echo $OUTPUT->header();
echo html_writer::link(new moodle_url('/local/mcp/admin/dashboard.php'),
    get_string('backtodashboard', 'local_mcp'), ['class' => 'btn btn-secondary mb-3']);
echo html_writer::start_tag('form', ['method' => 'get',
    'action' => (new moodle_url('/local/mcp/admin/audit.php'))->out(false),
    'class' => 'mb-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'filtering', 'value' => 1]);
echo html_writer::label(get_string('auditside', 'local_mcp'), 'audit-side');
echo html_writer::select(['' => get_string('all'), 'read' => 'READ', 'write' => 'WRITE'],
    'side', $side, false, ['id' => 'audit-side', 'class' => 'mr-2']);
echo html_writer::label(get_string('auditstatus', 'local_mcp'), 'audit-status');
echo html_writer::select(['' => get_string('all'), 'ok' => 'OK', 'preview' => 'Preview',
    'confirmation_pending' => 'Confirmation pending', 'error' => 'Error'],
    'status', $status, false, ['id' => 'audit-status', 'class' => 'mr-2']);
echo html_writer::label(get_string('audittool', 'local_mcp'), 'audit-tool');
echo html_writer::empty_tag('input', ['id' => 'audit-tool', 'name' => 'tool',
    'value' => $tool, 'type' => 'text', 'maxlength' => 128, 'class' => 'mr-2']);
echo html_writer::label(get_string('audituserid', 'local_mcp'), 'audit-userid');
echo html_writer::empty_tag('input', ['id' => 'audit-userid', 'name' => 'userid',
    'value' => $userid ?: '', 'type' => 'number', 'min' => 1, 'class' => 'mr-2']);
echo html_writer::checkbox('onlychatgpt', 1, $onlychatgpt,
    get_string('auditonlychatgpt', 'local_mcp'));
echo html_writer::empty_tag('input', ['type' => 'submit',
    'value' => get_string('filter'), 'class' => 'btn btn-primary ml-2']);
echo html_writer::end_tag('form');
echo html_writer::tag('p', get_string('auditprivacy', 'local_mcp'), ['class' => 'text-muted']);
echo html_writer::tag('p', get_string('auditresults', 'local_mcp', $total));
echo html_writer::link(new moodle_url('/local/mcp/admin/audit.php',
    $filterparams + ['export' => 'csv']), get_string('auditexport', 'local_mcp'),
    ['class' => 'btn btn-secondary mb-3']);
echo $OUTPUT->render_from_template('local_mcp/audit', ['rows' => $rows,
    'empty' => empty($rows)]);
echo $OUTPUT->render(new paging_bar($total, $page, $pagesize, $url));
echo $OUTPUT->footer();
