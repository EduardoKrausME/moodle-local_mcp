<?php
namespace local_mcp\security;

defined('MOODLE_INTERNAL') || die;

final class secret {
    public static function generate(string $prefix = 'mcp_', int $bytes = 32): string {
        return $prefix . self::base64url(random_bytes($bytes));
    }

    public static function hash(string $value): string {
        return hash('sha256', $value);
    }

    public static function prefix(string $value): string {
        return substr($value, 0, 16);
    }

    public static function equals_hash(string $value, string $hash): bool {
        return hash_equals($hash, self::hash($value));
    }

    public static function base64url(string $value): string {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
