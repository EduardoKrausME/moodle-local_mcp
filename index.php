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
 * index.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url('/local/mcp/index.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_mcp'));
$PAGE->set_heading(get_string('pluginname', 'local_mcp'));

echo $OUTPUT->header();
echo html_writer::link(
    new moodle_url('/local/mcp/admin/connections.php', ['new' => 1]),
    get_string('newconnection', 'local_mcp'),
    ['class' => 'btn btn-primary']
);
echo $OUTPUT->footer();
