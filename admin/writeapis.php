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
// The registry already includes WRITE tools discovered from other installed plugins.
// Group by the component namespace of each tool so that every plugin has its own table.
$toolgroups = [];
$yes = get_string('yes');
$no = get_string('no');
foreach (registry::get_tools() as $tool) {
    $component = explode('\\', get_class($tool), 2)[0];
    if (!core_component::get_component_directory($component)) {
        $component = 'local_mcp';
    }
    $toolgroups[$component][] = [
        'name' => $tool->get_name(),
        'description' => $tool->get_description(),
        'capability' => $tool->get_required_capability(),
        'dryrun' => $tool->supports_dry_run() ? $yes : $no,
        'confirmation' => $tool->requires_confirmation() ? $yes : $no,
        'destructive' => $tool->is_destructive() ? $yes : $no,
    ];
}

ksort($toolgroups, SORT_NATURAL);
// Show Moodle MCP's own tools first, followed by installed plugins alphabetically.
if (isset($toolgroups['local_mcp'])) {
    $localtools = $toolgroups['local_mcp'];
    unset($toolgroups['local_mcp']);
    $toolgroups = ['local_mcp' => $localtools] + $toolgroups;
}

$tables = [];
$stringmanager = get_string_manager();
foreach ($toolgroups as $component => $rows) {
    $pluginname = $stringmanager->string_exists('pluginname', $component)
        ? get_string('pluginname', $component) : $component;
    $tables[] = [
        'pluginname' => $pluginname,
        'component' => $component,
        'countlabel' => get_string('writeapiscount', 'local_mcp', count($rows)),
        'rows' => $rows,
    ];
}

echo $OUTPUT->header();
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
]);
echo $OUTPUT->footer();
