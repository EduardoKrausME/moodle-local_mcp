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
 * OAuth-only bearer extraction tests.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\exception\api_exception;
use local_mcp\protocol\http;

defined('MOODLE_INTERNAL') || die;

/**
 * Verify that OAuth access tokens must be sent in request headers.
 */
final class oauth_bearer_only_test extends advanced_testcase {
    /**
     * @return void
     */
    public function test_bearer_accepts_authorization_header(): void {
        $oldget = $_GET;
        $oldheader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        try {
            $_GET = [];
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer mcp_at_example';
            $this->assertSame(['Bearer mcp_at_example', 'mcp_at_example'], http::bearer());
        } finally {
            $_GET = $oldget;
            if ($oldheader === null) {
                unset($_SERVER['HTTP_AUTHORIZATION']);
            } else {
                $_SERVER['HTTP_AUTHORIZATION'] = $oldheader;
            }
        }
    }

    /**
     * @dataProvider legacy_query_parameters
     * @param string $key Disallowed URL parameter.
     * @return void
     */
    public function test_bearer_rejects_query_credentials(string $key): void {
        $oldget = $_GET;
        $oldheader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        try {
            $_GET = [$key => 'mcp_example'];
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer mcp_at_example';
            $this->expectException(api_exception::class);
            http::bearer();
        } finally {
            $_GET = $oldget;
            if ($oldheader === null) {
                unset($_SERVER['HTTP_AUTHORIZATION']);
            } else {
                $_SERVER['HTTP_AUTHORIZATION'] = $oldheader;
            }
        }
    }

    /**
     * @return array
     */
    public static function legacy_query_parameters(): array {
        return [['token'], ['toke']];
    }
}
