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
 * logger.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\audit;

defined('MOODLE_INTERNAL') || die;

/**
 * Class logger.
 */
final class logger {
    /**
     * Method record.
     *
     * @param string $event Parameter event.
     * @param ?\local_mcp\security\authenticated_identity $identity Parameter identity.
     * @param ?string $tool Parameter tool.
     * @param ?string $side Parameter side.
     * @param ?\context $context Parameter context.
     * @param array $metadata Parameter metadata.
     * @param bool $destructive Parameter destructive.
     * @param bool $dryrun Parameter dryrun.
     * @param bool $confirmation Parameter confirmation.
     * @param ?string $status Parameter status.
     * @param ?int $durationms Parameter durationms.
     * @return void Return value.
     */
    public static function record(string $event, ?\local_mcp\security\authenticated_identity $identity = null,
            ?string $tool = null, ?string $side = null, ?\context $context = null, array $metadata = [],
            bool $destructive = false, bool $dryrun = false, bool $confirmation = false,
            ?string $status = null, ?int $durationms = null): void {
        global $DB;
        $safe = [];
        foreach ($metadata as $key => $value) {
            if (preg_match('/token|code|secret|prompt|response/i', (string)$key)) {
                continue;
            }
            $safe[$key] = $value;
        }
        $DB->insert_record('local_mcp_audit', (object)[
            'connectionid' => $identity?->connectionid,
            'clientid' => $identity?->clientid,
            'userid' => $identity?->userid,
            'event' => clean_param($event, PARAM_ALPHANUMEXT),
            'tool' => $tool ? clean_param($tool, PARAM_ALPHANUMEXT) : null,
            'side' => $side,
            'destructive' => (int)$destructive,
            'contextid' => $context?->id,
            'ip' => getremoteaddr(),
            'durationms' => $durationms,
            'status' => $status,
            'dryrun' => (int)$dryrun,
            'confirmation' => (int)$confirmation,
            'metadata' => $safe ? json_encode($safe, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            'timecreated' => time(),
        ]);
    }
}
