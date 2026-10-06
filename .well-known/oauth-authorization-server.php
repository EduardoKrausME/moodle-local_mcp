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
 * oauth-authorization-server.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_mcp\protocol\http;

require_once(__DIR__ . '/../../../config.php');

$base = $CFG->wwwroot . '/local/mcp';
$data = [
    'issuer' => $base,
    'authorization_endpoint' => $base . '/oauth/authorize.php',
    'token_endpoint' => $base . '/oauth/token.php',
    'scopes_supported' => ['mcp:read', 'mcp:write'],
    'response_types_supported' => ['code'],
    'grant_types_supported' => ['authorization_code', 'refresh_token'],
    'code_challenge_methods_supported' => ['S256'],
    'token_endpoint_auth_methods_supported' => ['none'],
];
if (get_config('local_mcp', 'dynamicregistration')) {
    $data['registration_endpoint'] = $base . '/oauth/register.php';
}
http::json($data);
