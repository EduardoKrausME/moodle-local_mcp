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
use local_mcp\security\manual_token_service;

require_once(__DIR__ . '/../../../config.php');
require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url('/local/mcp/admin/connections.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('connections', 'local_mcp'));
$PAGE->set_heading(get_string('connections', 'local_mcp'));

global $DB, $USER;
$serverurl = null;
$shownew = optional_param('new', 0, PARAM_BOOL);
$editid = optional_param('edit', 0, PARAM_INT);
$expiresat = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $action = required_param('action', PARAM_ALPHA);

    if ($action === 'createtest') {
        $name = trim(required_param('name', PARAM_TEXT));
        $read = optional_param('read', 0, PARAM_BOOL);
        $write = optional_param('write', 0, PARAM_BOOL);

        if ($name === '' || (!$read && !$write)) {
            throw new moodle_exception('invalidrequest', 'error');
        }

        // Test connections are tied to the logged-in admin, not an arbitrary userid.
        // The token expires automatically, can be revoked, and is shown only once.
        $expiresat = time() + 7 * DAYSECS;
        $created = manual_token_service::create(
            'ChatGPT (teste) - ' . $name,
            (int)$USER->id,
            (bool)$read,
            (bool)$write,
            $expiresat
        );
        $serverurl = (new moodle_url('/local/mcp/server.php', [
            'toke' => $created['token'],
        ]))->out(false);
        header('Cache-Control: private, no-store, max-age=0');
        header('Pragma: no-cache');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow');
        unset($created);
    } else if ($action === 'updatepermissions') {
        $tokenid = required_param('tokenid', PARAM_INT);
        $read = optional_param('read', 0, PARAM_BOOL);
        $write = optional_param('write', 0, PARAM_BOOL);
        if (!$read && !$write) {
            throw new moodle_exception('invalidrequest', 'error');
        }
        $token = $DB->get_record('local_mcp_manual_token', ['id' => $tokenid], '*', MUST_EXIST);
        if (!str_starts_with($token->name, 'ChatGPT (teste) - ')) {
            throw new moodle_exception('invalidrequest', 'error');
        }
        manual_token_service::update_permissions($tokenid, (bool)$read, (bool)$write);
        redirect($PAGE->url);
    } else if ($action === 'revoketest') {
        $tokenid = required_param('tokenid', PARAM_INT);
        $token = $DB->get_record('local_mcp_manual_token', [
            'id' => $tokenid,
        ], '*', MUST_EXIST);
        // This screen must only revoke its own test connections.
        if (!str_starts_with($token->name, 'ChatGPT (teste) - ')) {
            throw new moodle_exception('invalidrequest', 'error');
        }
        manual_token_service::revoke($tokenid);
        redirect($PAGE->url);
    } else if ($action === 'revokeoauth') {
        connection_service::revoke(required_param('connectionid', PARAM_INT));
        redirect($PAGE->url);
    } else {
        throw new moodle_exception('invalidrequest', 'error');
    }
}

$editing = false;
if ($editid > 0) {
    $token = $DB->get_record('local_mcp_manual_token', ['id' => $editid], '*', MUST_EXIST);
    if (!str_starts_with($token->name, 'ChatGPT (teste) - ')
            || !$token->enabled || ($token->expires && $token->expires <= time())) {
        throw new moodle_exception('invalidrequest', 'error');
    }
    $editing = [
        'id' => (int)$token->id,
        'name' => substr($token->name, strlen('ChatGPT (teste) - ')),
        'readenabled' => (bool)$token->readenabled,
        'writeenabled' => (bool)$token->writeenabled,
    ];
}

// OAuth connections remain listed and revocable, but the test setup requires no OAuth.
$sql = "SELECT c.*, o.name AS clientname, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
               u.middlename, u.alternatename
          FROM {local_mcp_connection} c
          JOIN {local_mcp_oauth_client} o ON o.id = c.clientid
          JOIN {user} u ON u.id = c.userid
      ORDER BY c.timecreated DESC";
$rows = [];
foreach ($DB->get_records_sql($sql) as $record) {
    $rows[] = [
        'id' => (int)$record->id,
        'clientname' => $record->clientname,
        'user' => fullname($record),
        'scopes' => $record->scopes,
        'created' => userdate($record->timecreated),
        'lastused' => $record->lastused ? userdate($record->lastused) : '-',
        'enabled' => (bool)$record->enabled,
    ];
}

$testtokens = [];
$like = $DB->sql_like('t.name', ':nameprefix', false);
$sql = "SELECT t.*, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
               u.middlename, u.alternatename
          FROM {local_mcp_manual_token} t
          JOIN {user} u ON u.id = t.userid
         WHERE {$like}
      ORDER BY t.timecreated DESC";
foreach ($DB->get_records_sql($sql, ['nameprefix' => $DB->sql_like_escape('ChatGPT (teste) - ') . '%']) as $record) {
    $testtokens[] = [
        'id' => (int)$record->id,
        'name' => substr($record->name, strlen('ChatGPT (teste) - ')),
        'user' => fullname($record),
        'prefix' => $record->prefix,
        'editurl' => (new moodle_url('/local/mcp/admin/connections.php',
            ['edit' => (int)$record->id]))->out(false),
        'readenabled' => (bool)$record->readenabled,
        'writeenabled' => (bool)$record->writeenabled,
        'expires' => $record->expires ? userdate($record->expires) : '-',
        'enabled' => (bool)$record->enabled && (!$record->expires || $record->expires > time()),
    ];
}

$data = [
    'rows' => $rows,
    'testtokens' => $testtokens,
    'hastesttokens' => !empty($testtokens),
    'hasoauthrows' => !empty($rows),
    'shownew' => (bool)$shownew,
    'editing' => $editing,
    'newurl' => (new moodle_url('/local/mcp/admin/connections.php', ['new' => 1]))->out(false),
    'cancelurl' => (new moodle_url('/local/mcp/admin/connections.php'))->out(false),
    'chatgpturl' => 'https://chatgpt.com/',
    'serverurl' => $serverurl,
    'expiresat' => $expiresat ? userdate($expiresat) : '',
    'sesskey' => sesskey(),
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_mcp/connections', $data);
echo $OUTPUT->footer();
