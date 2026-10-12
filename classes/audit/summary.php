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
//
// @package   local_mcp
// @copyright 2026 Eduardo Kraus
// @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_mcp\audit;

/**
 * Build bounded, allowlisted summaries without storing the request payload.
 */
final class summary {
    /** @var string[] Numeric Moodle entity identifier keys. */
    private const IDS = [
        'id', 'courseid', 'categoryid', 'userid', 'sectionid', 'cmid',
        'contextid', 'questionid', 'quizid', 'groupid', 'discussionid',
        'postid', 'attemptid', 'submissionid', 'chapterid', 'entryid',
        'assignmentid', 'bankid',
    ];

    /**
     * Summarise only field names, object identifiers and result counts.
     *
     * @param string $tool Tool name
     * @param array $arguments Tool arguments (never stored)
     * @param array|null $result Completed result, if any (never stored)
     * @return array Safe audit metadata
     */
    public static function from_call(string $tool, array $arguments, ?array $result = null): array {
        unset($arguments['confirmation_token'], $arguments['dry_run']);
        $fields = [];
        foreach (array_keys($arguments) as $key) {
            if (is_string($key) && preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key)) {
                $fields[] = $key;
            }
        }
        $metadata = ['input_fields' => array_slice($fields, 0, 50)];
        $inputids = [];
        self::collect_ids($arguments, $inputids);
        if ($inputids) {
            $metadata['input_ids'] = $inputids;
        }
        if ($tool === 'set_site_config' && isset($arguments['settings']) && is_array($arguments['settings'])) {
            $names = [];
            foreach (array_slice($arguments['settings'], 0, 30) as $setting) {
                $name = is_array($setting) ? ($setting['name'] ?? null) : null;
                if (is_string($name) && preg_match('/^[a-z][a-z0-9_]{0,63}$/', $name)) {
                    $names[] = $name;
                }
            }
            if ($names) {
                $metadata['setting_names'] = array_values(array_unique($names));
            }
        }
        if ($result !== null) {
            $resultids = [];
            self::collect_ids($result, $resultids);
            if ($resultids) {
                $metadata['result_ids'] = $resultids;
            }
            $counts = [];
            foreach (['requested', 'created_count', 'skipped_count', 'updated_count', 'deleted_count'] as $key) {
                if (isset($result[$key]) && is_int($result[$key]) && $result[$key] >= 0) {
                    $counts[$key] = $result[$key];
                }
            }
            if ($counts) {
                $metadata['counts'] = $counts;
            }
        }
        return $metadata;
    }

    /**
     * Restrict metadata even when supplied by another plugin tool.
     *
     * @param array $metadata Candidate metadata
     * @return array Sanitised metadata
     */
    public static function sanitise(array $metadata): array {
        $safe = [];
        foreach (['request_id', 'error_code'] as $key) {
            if (isset($metadata[$key]) && is_string($metadata[$key]) &&
                    preg_match('/^[a-zA-Z0-9_:-]{1,64}$/', $metadata[$key])) {
                $safe[$key] = $metadata[$key];
            }
        }
        foreach (['input_fields', 'setting_names', 'names'] as $key) {
            if (!isset($metadata[$key]) || !is_array($metadata[$key])) {
                continue;
            }
            $values = [];
            foreach (array_slice($metadata[$key], 0, 50) as $value) {
                if (is_string($value) && preg_match('/^[a-z][a-z0-9_]{0,63}$/', $value)) {
                    $values[] = $value;
                }
            }
            if ($values) {
                $safe[$key] = array_values(array_unique($values));
            }
        }
        foreach (['input_ids', 'result_ids'] as $key) {
            if (!isset($metadata[$key]) || !is_array($metadata[$key])) {
                continue;
            }
            $ids = [];
            foreach ($metadata[$key] as $name => $values) {
                if (!in_array($name, self::IDS, true) || !is_array($values)) {
                    continue;
                }
                $valid = array_values(array_unique(array_filter(array_slice($values, 0, 30),
                    static fn($id): bool => is_int($id) && $id > 0)));
                if ($valid) {
                    $ids[$name] = $valid;
                }
            }
            if ($ids) {
                $safe[$key] = $ids;
            }
        }
        if (isset($metadata['counts']) && is_array($metadata['counts'])) {
            $counts = [];
            foreach (['requested', 'created_count', 'skipped_count', 'updated_count', 'deleted_count'] as $key) {
                $value = $metadata['counts'][$key] ?? null;
                if (is_int($value) && $value >= 0) {
                    $counts[$key] = $value;
                }
            }
            if ($counts) {
                $safe['counts'] = $counts;
            }
        }
        return $safe;
    }

    /**
     * Recursively find only numeric entity identifiers, with strict size limits.
     *
     * @param array $data Untrusted request or result
     * @param array $ids Accumulator
     * @param int $depth Recursion depth
     * @return void
     */
    private static function collect_ids(array $data, array &$ids, int $depth = 0): void {
        if ($depth > 4) {
            return;
        }
        foreach (array_slice($data, 0, 60, true) as $key => $value) {
            if (is_string($key) && in_array($key, self::IDS, true) &&
                    (is_int($value) || (is_string($value) && ctype_digit($value)))) {
                $id = (int)$value;
                if ($id > 0 && count($ids[$key] ?? []) < 30) {
                    $ids[$key][$id] = $id;
                }
            } else if (is_array($value)) {
                self::collect_ids($value, $ids, $depth + 1);
            }
        }
        if ($depth === 0) {
            foreach ($ids as $key => $values) {
                $ids[$key] = array_values($values);
            }
        }
    }
}
