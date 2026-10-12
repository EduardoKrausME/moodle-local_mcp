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

$PAGE->set_url('/local/mcp/admin/connections.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('connections', 'local_mcp'));
$PAGE->set_heading(get_string('connections', 'local_mcp'));
$PAGE->requires->css(new moodle_url('/local/mcp/styles/connections.css'));

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
echo $OUTPUT->render_from_template('local_mcp/connections', $data);
echo $OUTPUT->footer();
