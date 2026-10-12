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
 * Dashboard data for MCP administrators.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\dashboard;

use core_component;
use core_date;
use DateTimeImmutable;
use local_mcp\write\tool_interface;

/**
 * Builds the overview without exposing tool inputs or audit metadata.
 */
final class overview {
    /**
     * Group already-discovered READ or WRITE tools by their Moodle component.
     * A shared grouping makes each administration page represent the same provider.
     *
     * @param array $tools Tools from the matching registry.
     * @return array Groups keyed by component, with local_mcp first.
     */
    public static function group_tools(array $tools): array {
        $groups = [];
        foreach ($tools as $tool) {
            $component = explode('\\', get_class($tool), 2)[0];
            if (!core_component::get_component_directory($component)) {
                $component = 'local_mcp';
            }
            $groups[$component][] = $tool;
        }

        ksort($groups, SORT_NATURAL);
        if (isset($groups['local_mcp'])) {
            $native = $groups['local_mcp'];
            unset($groups['local_mcp']);
            $groups = ['local_mcp' => $native] + $groups;
        }

        $result = [];
        $strings = get_string_manager();
        foreach ($groups as $component => $componenttools) {
            $result[$component] = [
                'component' => $component,
                'pluginname' => $strings->string_exists('pluginname', $component)
                    ? get_string('pluginname', $component) : $component,
                'tools' => $componenttools,
            ];
        }
        return $result;
    }

    /**
     * Backwards-compatible entry point for existing WRITE API consumers.
     *
     * @param tool_interface[] $tools Tools from the WRITE registry.
     * @return array Groups keyed by component.
     */
    public static function group_write_tools(array $tools): array {
        return self::group_tools($tools);
    }

    /**
     * Counts audited MCP tool calls per user-local day, including failures,
     * previews and pending confirmations. Successful writes are counted separately.
     * The audit table has a timecreated index, so queries use bounded ranges.
     *
     * @param int $days Number of days, including today.
     * @param DateTimeImmutable|null $now Optional clock for tests.
     * @return array Chart series and daily totals.
     */
    public static function activity(int $days = 7, ?DateTimeImmutable $now = null): array {
        global $DB;

        $days = max(1, min(31, $days));
        $today = ($now ?? new DateTimeImmutable('now', core_date::get_user_timezone_object()))
            ->setTime(0, 0, 0);
        $data = [
            'labels' => [],
            'read' => [],
            'write' => [],
            'readtoday' => 0,
            'writetoday' => 0,
            'writecompletedtoday' => 0,
            'destructivetoday' => 0,
            'status' => [
                'ok' => 0,
                'error' => 0,
                'preview' => 0,
                'confirmation_pending' => 0,
            ],
            'total' => 0,
        ];

        for ($index = $days - 1; $index >= 0; $index--) {
            $day = $today->modify('-' . $index . ' days');
            $start = $day->getTimestamp();
            $end = $day->modify('+1 day')->getTimestamp();
            $sides = ['read' => 0, 'write' => 0];
            $sql = "SELECT side, COUNT(id) AS total
                      FROM {local_mcp_audit}
                     WHERE timecreated >= :starttime AND timecreated < :endtime
                       AND event IN (:called, :failed)
                       AND side IN (:readside, :writeside)
                  GROUP BY side";
            $records = $DB->get_records_sql($sql, [
                'starttime' => $start,
                'endtime' => $end,
                'called' => 'tool_called',
                'failed' => 'tool_failed',
                'readside' => 'read',
                'writeside' => 'write',
            ]);
            foreach ($records as $record) {
                $sides[$record->side] = (int)$record->total;
            }
            $data['labels'][] = userdate($start, '%d/%m');
            $data['read'][] = $sides['read'];
            $data['write'][] = $sides['write'];
            $data['total'] += $sides['read'] + $sides['write'];
            if ($index === 0) {
                $data['readtoday'] = $sides['read'];
                $data['writetoday'] = $sides['write'];
            }
        }

        $todaystart = $today->getTimestamp();
        $tomorrow = $today->modify('+1 day')->getTimestamp();
        $conditions = 'side = ? AND event = ? AND status = ? AND timecreated >= ? AND timecreated < ?';
        $params = ['write', 'tool_called', 'ok', $todaystart, $tomorrow];
        $data['writecompletedtoday'] = $DB->count_records_select('local_mcp_audit', $conditions, $params);
        $data['destructivetoday'] = $DB->count_records_select('local_mcp_audit',
            $conditions . ' AND destructive = ?', array_merge($params, [1]));

        $startday = $today->modify('-' . ($days - 1) . ' days')->getTimestamp();
        $statussql = "SELECT status, COUNT(id) AS total
                        FROM {local_mcp_audit}
                       WHERE timecreated >= :starttime AND timecreated < :endtime
                         AND event IN (:called, :failed)
                         AND side IN (:readside, :writeside)
                    GROUP BY status";
        $statusrecords = $DB->get_records_sql($statussql, [
            'starttime' => $startday,
            'endtime' => $tomorrow,
            'called' => 'tool_called',
            'failed' => 'tool_failed',
            'readside' => 'read',
            'writeside' => 'write',
        ]);
        foreach ($statusrecords as $record) {
            if (isset($data['status'][$record->status])) {
                $data['status'][$record->status] = (int)$record->total;
            }
        }

        return $data;
    }
}
