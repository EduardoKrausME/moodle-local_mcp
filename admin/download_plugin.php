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
//

/**
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_mcp\plugin\package_builder;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/filelib.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('moodle/site:config', $context);
require_capability('local/mcp:manage', $context);

if (stripos($CFG->wwwroot, 'https://') !== 0) {
    throw new moodle_exception('chatgptsetuphttpsdisabled', 'local_mcp');
}

$serverurl = (new moodle_url('/local/mcp/server.php'))->out(false);
$files = package_builder::get_files($serverurl, $SITE->shortname);

$directory = make_temp_directory('local_mcp_plugin');
$temporaryfile = tempnam($directory, 'mcp_');
if ($temporaryfile === false) {
    throw new \RuntimeException('Unable to prepare the MCP plugin download.');
}

$zip = new ZipArchive();
if ($zip->open($temporaryfile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    @unlink($temporaryfile);
    throw new \RuntimeException('Unable to create the MCP plugin ZIP.');
}

try {
    foreach ($files as $path => $content) {
        if (!$zip->addFromString($path, $content)) {
            throw new \RuntimeException('Unable to add a plugin manifest to the ZIP.');
        }
    }
} catch (\Throwable $exception) {
    $zip->close();
    @unlink($temporaryfile);
    throw $exception;
}
if (!$zip->close()) {
    @unlink($temporaryfile);
    throw new \RuntimeException('Unable to finish the MCP plugin ZIP.');
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="moodle-mcp-plugin.zip"');
header('Content-Length: ' . filesize($temporaryfile));
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

readfile($temporaryfile);
@unlink($temporaryfile);
exit;
