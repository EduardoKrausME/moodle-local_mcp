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
 * dashboard.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_mcp\dashboard\overview;
use local_mcp\write\registry;

require_once(__DIR__ . '/../../../config.php');
require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);
$PAGE->set_url('/local/mcp/admin/dashboard.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('dashboard', 'local_mcp'));
$PAGE->set_heading(get_string('pluginname', 'local_mcp'));

global $DB;
$activity = overview::activity();
$groups = overview::group_write_tools(registry::get_tools());
$plugins = [];
$pluginlabels = [];
$plugincounts = [];
$totaltools = 0;
foreach ($groups as $group) {
    $tools = $group['tools'];
    $toolcount = count($tools);
    $totaltools += $toolcount;
    $dryrun = 0;
    $confirmation = 0;
    $destructive = 0;
    $examples = [];
    foreach ($tools as $tool) {
        $dryrun += (int)$tool->supports_dry_run();
        $confirmation += (int)$tool->requires_confirmation();
        $destructive += (int)$tool->is_destructive();
        if (count($examples) < 3) {
            $examples[] = $tool->get_name();
        }
    }

    $url = new moodle_url('/local/mcp/admin/writeapis.php');
    $url->set_anchor('local-mcp-' . $group['component']);
    $plugins[] = [
        'pluginname' => $group['pluginname'],
        'component' => $group['component'],
        'tools' => $toolcount,
        'dryrun' => $dryrun,
        'confirmation' => $confirmation,
        'destructive' => $destructive,
        'examples' => implode(', ', $examples),
        'more' => $toolcount > count($examples)
            ? get_string('dashboardmoretools', 'local_mcp', $toolcount - count($examples)) : '',
        'detailsurl' => $url->out(false),
    ];
    $pluginlabels[] = $group['component'];
    $plugincounts[] = $toolcount;
}

$data = [
    'connections' => $DB->count_records('local_mcp_connection', ['enabled' => 1]),
    'clients' => $DB->count_records('local_mcp_oauth_client', ['enabled' => 1]),
    'readtoday' => $activity['readtoday'],
    'writetoday' => $activity['writetoday'],
    'writecompletedtoday' => $activity['writecompletedtoday'],
    'destructivetoday' => $activity['destructivetoday'],
    'totaltools' => $totaltools,
    'plugins' => $plugins,
    'hasplugins' => !empty($plugins),
    'hasactivity' => $activity['total'] > 0,
    'writeapisurl' => (new moodle_url('/local/mcp/admin/writeapis.php'))->out(false),
    'readapisurl' => (new moodle_url('/local/mcp/admin/readapis.php'))->out(false),
    'auditurl' => (new moodle_url('/local/mcp/admin/audit.php'))->out(false),
];

echo $OUTPUT->header();
// Use Moodle's own accessible chart renderer; no CDN, inline scripts or JS dependency.
if ($data['hasactivity']) {
    $timeline = new \core\chart_line();
    $timeline->set_title(get_string('dashboardactivitychart', 'local_mcp'));
    $timeline->set_labels($activity['labels']);
    $timeline->add_series(new \core\chart_series('READ', $activity['read']));
    $timeline->add_series(new \core\chart_series('WRITE', $activity['write']));
    $data['activitychart'] = $OUTPUT->render($timeline);

    $statuses = [
        'ok' => get_string('dashboardstatusok', 'local_mcp'),
        'error' => get_string('dashboardstatuserror', 'local_mcp'),
        'preview' => get_string('dashboardstatuspreview', 'local_mcp'),
        'confirmation_pending' => get_string('dashboardstatuspending', 'local_mcp'),
    ];
    $statuslabels = [];
    $statusvalues = [];
    foreach ($statuses as $status => $label) {
        if ($activity['status'][$status] > 0) {
            $statuslabels[] = $label;
            $statusvalues[] = $activity['status'][$status];
        }
    }
    if ($statusvalues) {
        $statuschart = new \core\chart_pie();
        $statuschart->set_title(get_string('dashboardstatuschart', 'local_mcp'));
        $statuschart->set_doughnut(true);
        $statuschart->set_labels($statuslabels);
        $statuschart->add_series(new \core\chart_series(
            get_string('dashboardcalls', 'local_mcp'), $statusvalues));
        $data['statuschart'] = $OUTPUT->render($statuschart);
    }
}
if ($data['hasplugins']) {
    $pluginchart = new \core\chart_bar();
    $pluginchart->set_title(get_string('dashboardpluginschart', 'local_mcp'));
    $pluginchart->set_labels($pluginlabels);
    $pluginchart->set_horizontal(true);
    $pluginchart->add_series(new \core\chart_series(
        get_string('dashboardtools', 'local_mcp'), $plugincounts));
    $data['pluginchart'] = $OUTPUT->render($pluginchart);
}
echo $OUTPUT->render_from_template('local_mcp/dashboard', $data);
echo $OUTPUT->footer();
