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
 * readapis.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_mcp\read\registry;

require_once(__DIR__ . '/../../../config.php');
require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);
$PAGE->set_url('/local/mcp/admin/readapis.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('readapis', 'local_mcp'));
$PAGE->set_heading(get_string('readapis', 'local_mcp'));
// The READ registry includes providers discovered from installed plugins such as Kopere BI.
$groups = \local_mcp\dashboard\overview::group_tools(registry::get_tools());
$tables = [];
foreach ($groups as $group) {
    $rows = [];
    foreach ($group['tools'] as $tool) {
        $rows[] = [
            'name' => $tool->get_name(),
            'description' => $tool->get_description(),
            'capability' => $tool->get_required_capability(),
        ];
    }
    $tables[] = [
        'pluginname' => $group['pluginname'],
        'component' => $group['component'],
        'countlabel' => get_string('readapiscount', 'local_mcp', count($rows)),
        'rows' => $rows,
    ];
}
echo $OUTPUT->header();
echo html_writer::link(new moodle_url('/local/mcp/admin/dashboard.php'),
    get_string('backtodashboard', 'local_mcp'), ['class' => 'btn btn-secondary mb-3']);
echo $OUTPUT->render_from_template('local_mcp/readapis', [
    'tables' => $tables,
    'empty' => get_string('readapisempty', 'local_mcp'),
]);
echo $OUTPUT->footer();
