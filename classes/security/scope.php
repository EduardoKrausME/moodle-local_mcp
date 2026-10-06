<?php
namespace local_mcp\security;

defined('MOODLE_INTERNAL') || die;

final class scope {
    public const READ = 'mcp:read';
    public const WRITE = 'mcp:write';

    public static function parse(string $value): array {
        $requested = preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $allowed = [self::READ, self::WRITE];
        return array_values(array_unique(array_intersect($requested, $allowed)));
    }

    public static function to_string(array $scopes): string {
        return implode(' ', array_values(array_unique($scopes)));
    }
}
