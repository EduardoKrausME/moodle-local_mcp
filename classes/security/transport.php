<?php
namespace local_mcp\security;

defined('MOODLE_INTERNAL') || die;

final class transport {
    public static function require_secure(): void {
        global $CFG;
        $parts = parse_url($CFG->wwwroot);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));
        $local = in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.test');
        if ($scheme !== 'https' && !$local) {
            throw new \local_mcp\exception\api_exception('https_required', 400);
        }
    }
}
