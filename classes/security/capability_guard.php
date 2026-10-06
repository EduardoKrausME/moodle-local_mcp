<?php
namespace local_mcp\security;

defined('MOODLE_INTERNAL') || die;

final class capability_guard {
    public static function check(string $capability, \context $context, int $userid): void {
        if (!has_capability($capability, $context, $userid)) {
            throw new \local_mcp\exception\api_exception('permission_denied', 403);
        }
    }
}
