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
 * writeapis.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_mcp\write\registry;

require_once(__DIR__ . '/../../../config.php');
require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);
$PAGE->set_url('/local/mcp/admin/writeapis.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('writeapis', 'local_mcp'));
$PAGE->set_heading(get_string('writeapis', 'local_mcp'));
// Reuse the same component grouping as the dashboard's WRITE API summary.
$groups = \local_mcp\dashboard\overview::group_write_tools(registry::get_tools());
$yes = get_string('yes');
$no = get_string('no');
$tables = [];
foreach ($groups as $group) {
    $rows = [];
    foreach ($group['tools'] as $tool) {
        $rows[] = [
            'name' => $tool->get_name(),
            'description' => $tool->get_description(),
            'capability' => $tool->get_required_capability(),
            'dryrun' => $tool->supports_dry_run() ? $yes : $no,
            'confirmation' => $tool->requires_confirmation() ? $yes : $no,
            'destructive' => $tool->is_destructive() ? $yes : $no,
        ];
    }
    $tables[] = [
        'pluginname' => $group['pluginname'],
        'component' => $group['component'],
        'countlabel' => get_string('writeapiscount', 'local_mcp', count($rows)),
        'rows' => $rows,
    ];
}

// Show a compact reference to plugins that provide only READ tools. They cannot
// appear in the WRITE registry, but administrators should still see the integration.
$readonlyplugins = [];
$readgroups = \local_mcp\dashboard\overview::group_tools(\local_mcp\read\registry::get_tools());
foreach ($readgroups as $component => $group) {
    if (isset($groups[$component])) {
        continue;
    }
    $rows = [];
    foreach (array_slice($group['tools'], 0, 5) as $tool) {
        $rows[] = [
            'name' => $tool->get_name(),
            'description' => $tool->get_description(),
            'capability' => $tool->get_required_capability(),
        ];
    }
    $readurl = new moodle_url('/local/mcp/admin/readapis.php');
    $readurl->set_anchor('local-mcp-' . $component);
    $readonlyplugins[] = [
        'pluginname' => $group['pluginname'],
        'component' => $component,
        'countlabel' => get_string('readapiscount', 'local_mcp', count($group['tools'])),
        'rows' => $rows,
        'more' => count($group['tools']) > count($rows)
            ? get_string('dashboardmoretools', 'local_mcp', count($group['tools']) - count($rows)) : '',
        'detailsurl' => $readurl->out(false),
    ];
}

echo $OUTPUT->header();
echo html_writer::link(new moodle_url('/local/mcp/admin/dashboard.php'),
    get_string('backtodashboard', 'local_mcp'), ['class' => 'btn btn-secondary mb-3']);
echo $OUTPUT->render_from_template('local_mcp/writeapis', [
    'tables' => $tables,
    'columns' => [
        'name' => get_string('writeapiscolname', 'local_mcp'),
        'description' => get_string('writeapiscoldescription', 'local_mcp'),
        'capability' => get_string('writeapiscolcapability', 'local_mcp'),
        'dryrun' => get_string('writeapiscoldryrun', 'local_mcp'),
        'confirmation' => get_string('writeapiscolconfirmation', 'local_mcp'),
        'destructive' => get_string('writeapiscoldestructive', 'local_mcp'),
    ],
    'empty' => get_string('writeapisempty', 'local_mcp'),
    'readonlyplugins' => $readonlyplugins,
    'hasreadonlyplugins' => !empty($readonlyplugins),
]);
echo $OUTPUT->footer();
