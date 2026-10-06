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
 * token.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_DEBUG_DISPLAY', true);
require_once(__DIR__ . '/../../../config.php');\n\n\local_mcp\\security\\transport::require_secure();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new \local_mcp\exception\api_exception('invalid_request', 405);
    }
    \local_mcp\security\rate_limiter::check('oauth_token', getremoteaddr(),
        (int)get_config('local_mcp', 'rateoauth') ?: 30);

    $grant = required_param('grant_type', PARAM_ALPHANUMEXT);
    $clientid = required_param('client_id', PARAM_RAW_TRIMMED);
    if ($grant === 'authorization_code') {
        $result = \local_mcp\oauth\token_service::exchange_code(
            $clientid,
            required_param('code', PARAM_RAW_TRIMMED),
            required_param('redirect_uri', PARAM_URL),
            required_param('code_verifier', PARAM_RAW_TRIMMED)
        );
    } else if ($grant === 'refresh_token') {
        $result = \local_mcp\oauth\token_service::refresh(
            $clientid,
            required_param('refresh_token', PARAM_RAW_TRIMMED)
        );
    } else {
        throw new \local_mcp\exception\api_exception('unsupported_grant_type', 400);
    }
    \local_mcp\protocol\http::json($result);
} catch (\Throwable $e) {
    \local_mcp\protocol\http::error($e);
}
