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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.
//
// @package   local_mcp
// @copyright 2026 Eduardo Kraus
// @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_mcp\read\tool;

use context;
use context_system;
use core\check\manager;
use core\check\result;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use Throwable;

/**
 * Expose exactly the Moodle core Check API results used by admin reports.
 */
final class get_site_status extends base_tool {
    public function get_name(): string {
        return 'get_site_status';
    }

    public function get_title(): string {
        return 'Moodle performance and system status';
    }

    public function get_description(): string {
        return 'Run the same Moodle Check API checks as Reports > Performance overview, System status '
            . 'or Security overview (including third-party checks). Default: performance. '
            . 'Detailed checks may be expensive; use checkref to inspect a single check.';
    }

    public function get_required_capability(): string {
        return 'moodle/site:config';
    }

    public function get_input_schema(): array {
        return $this->object_schema([
            'type' => [
                'type' => 'string',
                'enum' => ['performance', 'status', 'security'],
                'description' => 'Moodle report type; defaults to performance.',
            ],
            'checkref' => [
                'type' => 'string',
                'description' => 'Optional exact check reference from a prior result.',
                'maxLength' => 150,
            ],
            'include_details' => [
                'type' => 'boolean',
                'description' => 'Get all detailed results and explanatory text; potentially slow. Defaults to false.',
            ],
        ]);
    }

    public function resolve_context(array $arguments): context {
        return context_system::instance();
    }

    /**
     * Convert formatted Moodle report text into safe, compact JSON text.
     *
     * @param string $html Original Moodle formatted text.
     * @return string Plain text.
     */
    private static function plain(string $html): string {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string)preg_replace('/\s+/u', ' ', $text));
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        global $CFG;
        if (!is_siteadmin($identity->userid)) {
            throw new api_exception('permission_denied', 403);
        }
        $type = $arguments['type'] ?? 'performance';
        if (!in_array($type, manager::TYPES, true)) {
            throw new api_exception('invalid_report_type', 400);
        }
        $ref = (string)($arguments['checkref'] ?? '');
        $details = !empty($arguments['include_details']);
        $started = microtime(true);
        $checks = manager::get_checks($type);
        $out = [];
        $counts = array_fill_keys([
            result::OK, result::INFO, result::NA, result::UNKNOWN,
            result::WARNING, result::ERROR, result::CRITICAL,
        ], 0);
        foreach ($checks as $check) {
            if ($ref !== '' && $check->get_ref() !== $ref) {
                continue;
            }
            try {
                $results = $details ? $check->get_results() : [$check->get_result()];
                foreach ($results as $r) {
                    $status = $r->get_status();
                    if (!array_key_exists($status, $counts)) {
                        $status = result::UNKNOWN;
                    }
                    $counts[$status]++;
                    $item = [
                        'ref' => $check->get_ref(),
                        'name' => self::plain($check->get_name()),
                        'status' => $status,
                        'summary' => self::plain($r->get_summary()),
                    ];
                    if ($details) {
                        $item['details'] = self::plain($r->get_details());
                    }
                    $out[] = $item;
                }
            } catch (Throwable $e) {
                // A failed plugin check must not hide all other checks or leak traces.
                $counts[result::UNKNOWN]++;
                $out[] = [
                    'ref' => $check->get_ref(),
                    'name' => $check->get_ref(),
                    'status' => result::UNKNOWN,
                    'summary' => 'The check failed. Inspect the Moodle server logs.',
                ];
                \local_mcp\diagnostics::exception($e, [
                    'side' => 'read',
                    'tool' => 'get_site_status',
                    'check' => $check->get_ref(),
                ], 'WARNING');
            }
        }
        if ($ref !== '' && !$out) {
            throw new api_exception('check_not_found', 404);
        }
        $overall = result::OK;
        foreach ([result::CRITICAL, result::ERROR, result::WARNING, result::UNKNOWN, result::INFO] as $severity) {
            if ($counts[$severity] > 0) {
                $overall = $severity;
                break;
            }
        }
        return [
            'type' => $type,
            'overall' => $overall,
            'counts' => $counts,
            'checks' => $out,
            'checked_at' => time(),
            'duration_ms' => (int)round((microtime(true) - $started) * 1000),
            'report_url' => $CFG->wwwroot . '/report/' . $type . '/index.php',
        ];
    }
}
