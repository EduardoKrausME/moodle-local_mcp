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

use local_mcp\oauth\authorization_service;
use local_mcp\security\transport;

// phpcs:disable moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../../config.php');

transport::require_secure();

$clientid = required_param('client_id', PARAM_RAW_TRIMMED);
$redirecturi = required_param('redirect_uri', PARAM_URL);
$responsetype = required_param('response_type', PARAM_ALPHANUMEXT);
$scopeparam = optional_param('scope', 'mcp:read', PARAM_RAW_TRIMMED);
$state = required_param('state', PARAM_RAW_TRIMMED);
$challenge = required_param('code_challenge', PARAM_RAW_TRIMMED);
$method = required_param('code_challenge_method', PARAM_ALPHANUMEXT);
$resource = required_param('resource', PARAM_URL);

$returnurl = new moodle_url('/local/mcp/oauth/authorize.php', [
    'client_id' => $clientid,
    'redirect_uri' => $redirecturi,
    'response_type' => $responsetype,
    'scope' => $scopeparam,
    'state' => $state,
    'code_challenge' => $challenge,
    'code_challenge_method' => $method,
    'resource' => $resource,
]);

// Preserve the OAuth request URL through Moodle's standard login redirect.
// The third argument of require_login() is a course module, not a return URL.
require_login(null, false);

$PAGE->set_context(context_system::instance());
$PAGE->set_url($returnurl);
$PAGE->set_title(get_string('pluginname', 'local_mcp'));
$PAGE->set_heading(get_string('pluginname', 'local_mcp'));

$PAGE->add_body_class("mcp-consent-page");

// Any authenticated, non-guest Moodle user may authorize an MCP client.
// Individual tools still enforce scopes and Moodle capabilities in their own contexts.
if (!isloggedin() || isguestuser() || !empty($USER->suspended)
        || !empty($USER->deleted) || empty($USER->confirmed)) {
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
    [$client, $scopes] = authorization_service::validate_request([
        'client_id' => $clientid,
        'redirect_uri' => $redirecturi,
        'response_type' => $responsetype,
        'scope' => $scopeparam,
        'state' => $state,
        'code_challenge' => $challenge,
        'code_challenge_method' => $method,
        'resource' => $resource,
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
    $code = authorization_service::issue_code(
        $client, $USER->id, $redirecturi, $scopes, $challenge, $resource);
    redirect(new moodle_url($redirecturi, ['code' => $code, 'state' => $state]));
}

$origin = '';
$parts = parse_url((string)($client->clienturi ?: $redirecturi));
if (!empty($parts['scheme']) && !empty($parts['host'])) {
    $origin = $parts['scheme'] . '://' . $parts['host'];
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_mcp/authorization_consent', [
    // Moodle Mustache escapes these values when rendering untrusted client metadata.
    'clientname' => $client->name,
    'origin' => $origin,
    'sitename' => $SITE->fullname,
    'sitedomain' => (string)parse_url($CFG->wwwroot, PHP_URL_HOST),
    'read' => in_array('mcp:read', $scopes, true),
    'write' => in_array('mcp:write', $scopes, true),
    'date' => userdate(time()),
    'action' => $returnurl->out(false),
    'sesskey' => sesskey(),
]);
echo $OUTPUT->footer();
