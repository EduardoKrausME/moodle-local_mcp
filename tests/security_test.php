<?php
namespace local_mcp;

defined('MOODLE_INTERNAL') || die;

final class security_test extends \advanced_testcase {
    public function test_pkce_s256_matches_rfc7636_shape(): void {
        $verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
        $challenge = \local_mcp\security\secret::base64url(hash('sha256', $verifier, true));
        $this->assertSame('E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM', $challenge);
    }

    public function test_argument_hash_is_order_independent_and_change_sensitive(): void {
        $a = ['userid' => 7, 'courseid' => 3, 'nested' => ['b' => 2, 'a' => 1]];
        $b = ['nested' => ['a' => 1, 'b' => 2], 'courseid' => 3, 'userid' => 7];
        $c = ['nested' => ['a' => 1, 'b' => 2], 'courseid' => 4, 'userid' => 7];

        $this->assertSame(
            \local_mcp\security\confirmation_service::args_hash($a),
            \local_mcp\security\confirmation_service::args_hash($b)
        );
        $this->assertNotSame(
            \local_mcp\security\confirmation_service::args_hash($a),
            \local_mcp\security\confirmation_service::args_hash($c)
        );
    }

    public function test_non_admin_does_not_have_site_config(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->assertFalse(has_capability('moodle/site:config', \context_system::instance(), $user->id));
    }

    public function test_admin_has_site_config(): void {
        $this->resetAfterTest();
        $admin = get_admin();
        $this->assertTrue(has_capability('moodle/site:config', \context_system::instance(), $admin->id));
    }
}
