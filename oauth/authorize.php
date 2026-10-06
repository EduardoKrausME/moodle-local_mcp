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
 * authorize.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');\n\n\\local_mcp\\security\\transport::require_secure();

$clientid = required_param('client_id', PARAM_RAW_TRIMMED);
$redirecturi = required_param('redirect_uri', PARAM_URL);
$responsetype = required_param('response_type', PARAM_ALPHANUMEXT);
$scopeparam = required_param('scope', PARAM_RAW_TRIMMED);
$state = required_param('state', PARAM_RAW_TRIMMED);
$challenge = required_param('code_challenge', PARAM_RAW_TRIMMED);
$method = required_param('code_challenge_method', PARAM_ALPHANUMEXT);

$returnurl = new moodle_url('/local/mcp/oauth/authorize.php', [
    'client_id' => $clientid,
    'redirect_uri' => $redirecturi,
    'response_type' => $responsetype,
    'scope' => $scopeparam,
    'state' => $state,
    'code_challenge' => $challenge,
    'code_challenge_method' => $method,
]);

require_login(null, false, $returnurl);

$PAGE->set_context(context_system::instance());
$PAGE->set_url($returnurl);
$PAGE->set_title(get_string('pluginname', 'local_mcp'));
$PAGE->set_heading(get_string('pluginname', 'local_mcp'));

$context = context_system::instance();
if (!has_capability('moodle/site:config', $context)) {
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('local_mcp/authorization_denied', [
        'title' => get_string('authorizationdeniedtitle', 'local_mcp'),
        'body' => get_string('authorizationdeniedbody', 'local_mcp'),
        'backurl' => (new moodle_url('/'))->out(false),
    ]);
    echo $OUTPUT->footer();
    exit;
}

try {
    [$client, $scopes] = \local_mcp\oauth\authorization_service::validate_request([
        'client_id' => $clientid,
        'redirect_uri' => $redirecturi,
        'response_type' => $responsetype,
        'scope' => $scopeparam,
        'state' => $state,
        'code_challenge' => $challenge,
        'code_challenge_method' => $method,
    ]);
} catch (Throwable $e) {
    throw new moodle_exception('invalidrequest', 'error');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $decision = required_param('decision', PARAM_ALPHA);
    if ($decision === 'cancel') {
        redirect(new moodle_url($redirecturi, ['error' => 'access_denied', 'state' => $state]));
    }
    if ($decision !== 'authorize') {
        throw new moodle_exception('invalidrequest', 'error');
    }
    $code = \local_mcp\oauth\authorization_service::issue_code(
        $client, $USER->id, $redirecturi, $scopes, $challenge);
    redirect(new moodle_url($redirecturi, ['code' => $code, 'state' => $state]));
}

$origin = '';
$parts = parse_url((string)($client->clienturi ?: $redirecturi));
if (!empty($parts['scheme']) && !empty($parts['host'])) {
    $origin = $parts['scheme'] . '://' . $parts['host'];
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_mcp/authorization_consent', [
    'clientname' => s($client->name),
    'origin' => s($origin),
    'read' => in_array('mcp:read', $scopes, true),
    'write' => in_array('mcp:write', $scopes, true),
    'date' => userdate(time()),
    'action' => $returnurl->out(false),
    'sesskey' => sesskey(),
]);
echo $OUTPUT->footer();
