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
 * Rate limiter tests.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\exception\api_exception;
use local_mcp\security\rate_limiter;

/**
 * Test independent counters and requests sharing one token.
 *
 * @covers \local_mcp\security\rate_limiter
 */
final class rate_limiter_test extends advanced_testcase {
    /**
     * test_defaults_are_high
     *
     * @return void
     */
    public function test_defaults_are_high(): void {
        $this->assertSame(1200, rate_limiter::DEFAULT_READ_PER_MINUTE);
        $this->assertSame(600, rate_limiter::DEFAULT_WRITE_PER_MINUTE);
        $this->assertSame(120, rate_limiter::DEFAULT_OAUTH_PER_MINUTE);
    }

    /**
     * test_two_calls_with_same_token
     *
     * @return void
     * @throws api_exception
     */
    public function test_two_calls_with_same_token(): void {
        $this->resetAfterTest();
        $subject = 'qa-' . random_string(16);
        rate_limiter::check('mcp_read', $subject, 2);
        rate_limiter::check('mcp_read', $subject, 2);
        $this->expectException(api_exception::class);
        rate_limiter::check('mcp_read', $subject, 2);
    }

    /**
     * A zero or negative limit must not disable the limiter.
     *
     * @return void
     */
    public function test_nonpositive_limits_are_clamped(): void {
        $this->resetAfterTest();
        foreach ([0, -5] as $limit) {
            $subject = "invalid-limit-" . random_string(16);
            rate_limiter::check("mcp_read", $subject, $limit);
            try {
                rate_limiter::check("mcp_read", $subject, $limit);
                $this->fail("The second request should be rate limited.");
            } catch (api_exception $e) {
                $this->assertSame("rate_limit_exceeded", $e->machinecode);
            }
        }
    }

    /**
     * test_connection_and_scope_isolation
     *
     * @return void
     * @throws api_exception
     */
    public function test_connection_and_scope_isolation(): void {
        $this->resetAfterTest();
        $one = 'first-' . random_string(16);
        $two = 'second-' . random_string(16);
        rate_limiter::check('mcp_write', $one, 1);
        rate_limiter::check('mcp_write', $two, 1);
        rate_limiter::check('mcp_read', $one, 1);
        $this->expectException(api_exception::class);
        rate_limiter::check('mcp_write', $one, 1);
    }
}
