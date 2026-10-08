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
 * rate_limiter.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\security;

use cache;
use local_mcp\exception\api_exception;

/**
 * Class rate_limiter.
 */
final class rate_limiter {
    /** Per-minute defaults; callers and scopes have independent quotas. */
    public const DEFAULT_READ_PER_MINUTE = 1200;
    public const DEFAULT_WRITE_PER_MINUTE = 600;
    public const DEFAULT_OAUTH_PER_MINUTE = 120;

    /**
     * Method check.
     *
     * @param string $bucket Parameter bucket.
     * @param string $subject Parameter subject.
     * @param int $limit Parameter limit.
     * @return void Return value.
     */
    public static function check(string $bucket, string $subject, int $limit): void {
        if ($limit <= 0) {
            return;
        }
        $cache = cache::make('local_mcp', 'ratelimit');
        $window = (int)floor(time() / 60);
        $key = sha1($bucket . ':' . $subject . ':' . $window);
        // MUC get/set is not atomic across simultaneous PHP workers.
        $factory = \core\lock\lock_config::get_lock_factory('local_mcp');
        $lock = $factory->get_lock('ratelimit_' . $key, 10);
        if (!$lock) {
            throw new api_exception('rate_limit_busy', 503,
                'The rate limiter could not obtain its lock. Please retry.');
        }
        try {
            $count = (int)($cache->get($key) ?: 0);
            if ($count >= $limit) {
                throw new api_exception('rate_limit_exceeded', 429);
            }
            $cache->set($key, $count + 1);
        } finally {
            $lock->release();
        }
    }
}
