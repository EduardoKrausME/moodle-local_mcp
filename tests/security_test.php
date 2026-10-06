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
 * security_test.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use context_system;
use local_mcp\security\confirmation_service;
use local_mcp\security\secret;

defined('MOODLE_INTERNAL') || die;

/**
 * Class security_test.
 */
final class security_test extends advanced_testcase {
    /**
     * Method test_pkce_s256_matches_rfc7636_shape.
     *
     * @return void Return value.
     */
    public function test_pkce_s256_matches_rfc7636_shape(): void {
        $verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
        $challenge = secret::base64url(hash('sha256', $verifier, true));
        $this->assertSame('E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM', $challenge);
    }

    /**
     * Method test_argument_hash_is_order_independent_and_change_sensitive.
     *
     * @return void Return value.
     */
    public function test_argument_hash_is_order_independent_and_change_sensitive(): void {
        $a = ['userid' => 7, 'courseid' => 3, 'nested' => ['b' => 2, 'a' => 1]];
        $b = ['nested' => ['a' => 1, 'b' => 2], 'courseid' => 3, 'userid' => 7];
        $c = ['nested' => ['a' => 1, 'b' => 2], 'courseid' => 4, 'userid' => 7];

        $this->assertSame(
            confirmation_service::args_hash($a),
            confirmation_service::args_hash($b)
        );
        $this->assertNotSame(
            confirmation_service::args_hash($a),
            confirmation_service::args_hash($c)
        );
    }

    /**
     * Method test_non_admin_does_not_have_site_config.
     *
     * @return void Return value.
     */
    public function test_non_admin_does_not_have_site_config(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->assertFalse(has_capability('moodle/site:config', context_system::instance(), $user->id));
    }

    /**
     * Method test_admin_has_site_config.
     *
     * @return void Return value.
     */
    public function test_admin_has_site_config(): void {
        $this->resetAfterTest();
        $admin = get_admin();
        $this->assertTrue(has_capability('moodle/site:config', context_system::instance(), $admin->id));
    }
}
