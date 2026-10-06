<?php
namespace local_mcp\security;

defined('MOODLE_INTERNAL') || die;

final class rate_limiter {
    public static function check(string $bucket, string $subject, int $limit): void {
        if ($limit <= 0) {
            return;
        }
        $cache = \cache::make('local_mcp', 'ratelimit');
        $window = (int) floor(time() / 60);
        $key = sha1($bucket . ':' . $subject . ':' . $window);
        $count = (int) ($cache->get($key) ?: 0);
        if ($count >= $limit) {
            throw new \local_mcp\exception\api_exception('rate_limit_exceeded', 429);
        }
        $cache->set($key, $count + 1);
    }
}
