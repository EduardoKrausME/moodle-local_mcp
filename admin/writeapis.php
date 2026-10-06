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
$rows = [];
foreach (registry::get_tools() as $tool) {
    $rows[] = ['name' => $tool->get_name(), 'description' => $tool->get_description(), 'capability' => $tool->get_required_capability(),
        'dryrun' => $tool->supports_dry_run() ? 'Yes' : 'No', 'confirmation' => $tool->requires_confirmation() ? 'Yes' : 'No', 'destructive' => $tool->is_destructive() ? 'Yes' : 'No'];
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_mcp/writeapis', ['rows' => $rows]);
echo $OUTPUT->footer();
