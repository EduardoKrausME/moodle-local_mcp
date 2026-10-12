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
 * OAuth-authenticated multipart HTTPS upload outside the MCP JSON-RPC body.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\session\manager;
use local_mcp\audit\logger;
use local_mcp\course\staged_upload;
use local_mcp\exception\api_exception;
use local_mcp\oauth\resource;
use local_mcp\protocol\http;
use local_mcp\security\rate_limiter;
use local_mcp\security\scope;
use local_mcp\security\token_resolver;
use local_mcp\security\transport;

define('NO_DEBUG_DISPLAY', true);
define('NO_MOODLE_COOKIES', true);
require_once(__DIR__ . '/../../config.php');

try {
    transport::require_secure();
    [$header] = http::bearer();
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        throw new api_exception('method_not_allowed', 405);
    }

    // Upload is an auxiliary endpoint for either the combined or WRITE MCP resource.
    $identity = token_resolver::from_bearer($header, [
        resource::endpoint('server'),
        resource::endpoint('write'),
    ]);
    if (!$identity->has_scope(scope::WRITE)) {
        throw new api_exception('insufficient_scope', 403);
    }
    global $DB;
    $user = $DB->get_record('user', [
        'id' => $identity->userid, 'deleted' => 0, 'suspended' => 0,
    ]);
    if (!$user || empty($user->confirmed) || isguestuser($user)) {
        throw new api_exception('invalid_user', 401);
    }
    manager::set_user($user);
    rate_limiter::check('mcp_upload', 'connection:' . $identity->connectionid, 30);

    // Reject oversized bodies before attempting to process multipart form values.
    $length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($length > staged_upload::MAX_BYTES + 65536) {
        throw new api_exception('upload_too_large', 413);
    }
    if (!str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'multipart/form-data')) {
        throw new api_exception('invalid_upload', 415, 'Use multipart/form-data with a file field named file.');
    }
    $upload = $_FILES['file'] ?? null;
    if (!is_array($upload) || is_array($upload['name'] ?? null)
            || (int)($upload['error'] ?? -1) !== UPLOAD_ERR_OK
            || empty($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
        throw new api_exception('invalid_upload', 400, 'A valid uploaded file is required (check PHP upload limits).');
    }

    $receipt = staged_upload::store($upload['tmp_name'], (string)$upload['name'], $identity);
    logger::record('file_uploaded', $identity, 'upload_file', 'write', null,
        ['input_fields' => ['filename'], 'counts' => ['requested' => 1]],
        false, false, false, 'ok');
    http::json($receipt, 201);
} catch (Throwable $e) {
    http::error($e, 'write');
}
