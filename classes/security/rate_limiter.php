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

defined('MOODLE_INTERNAL') || die;

/**
 * Class rate_limiter.
 */
final class rate_limiter {
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
