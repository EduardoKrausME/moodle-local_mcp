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

namespace local_mcp\config;

use local_mcp\exception\api_exception;

/**
 * Deliberately bounded access to non-secret Moodle core site settings.
 *
 * Do not expose the entire mdl_config table: it also contains authentication,
 * integrations and security settings which must not be writable through an AI tool.
 * Add further settings only after reviewing their validation and side effects.
 */
final class site_config {
    /** @var array Supported core keys and their allowed types and values. */
    private const RULES = [
        'debug' => ['type' => 'integer', 'values' => [0, 5, 15, 32767]],
        'debugdisplay' => ['type' => 'boolean'],
        'debugpageinfo' => ['type' => 'boolean'],
        'themedesignermode' => ['type' => 'boolean'],
        'cachejs' => ['type' => 'boolean'],
        'enabledashboard' => ['type' => 'boolean'],
        'enablemycourses' => ['type' => 'boolean'],
        'enablemyhome' => ['type' => 'boolean'],
        'defaulthomepage' => ['type' => 'integer', 'values' => [0, 1, 2, 3]],
        'allowguestmymoodle' => ['type' => 'boolean'],
    ];

    /**
     * Names suitable for MCP JSON Schema enum.
     *
     * @return string[]
     */
    public static function names(): array {
        return array_keys(self::RULES);
    }

    /**
     * Validate one known, non-sensitive Moodle core setting and normalize its value.
     *
     * @param string $name Core setting name.
     * @param string $value String representation, as transmitted over MCP.
     * @return string Canonical DB value.
     */
    public static function normalize(string $name, string $value): string {
        if (!array_key_exists($name, self::RULES)) {
            throw new api_exception('unsupported_config', 400,
                'This core configuration key is not approved for MCP access.');
        }
        $rule = self::RULES[$name];
        if ($rule['type'] === 'boolean') {
            if ($value === '1' || $value === 'true') {
                return '1';
            }
            if ($value === '0' || $value === 'false') {
                return '0';
            }
            throw new api_exception('invalid_config_value', 400, 'Boolean settings accept 0, 1, true or false.');
        }
        if (!preg_match('/^(0|[1-9][0-9]*)$/D', $value) || !in_array((int)$value, $rule['values'], true)) {
            throw new api_exception('invalid_config_value', 400, 'Unsupported value for this configuration key.');
        }
        return (string)(int)$value;
    }

    /**
     * Validate a batch completely before any setting is changed.
     *
     * @param array $settings List of {name, value}.
     * @return array<string,string> Canonical name => value map.
     */
    public static function validate_batch(array $settings): array {
        if (count($settings) < 1 || count($settings) > 20 || !array_is_list($settings)) {
            throw new api_exception('invalid_config_batch', 400, 'Provide between 1 and 20 settings.');
        }
        $normalized = [];
        foreach ($settings as $item) {
            if (!is_array($item) || !isset($item['name']) || !is_string($item['name'])
                || !isset($item['value']) || !is_string($item['value'])) {
                throw new api_exception('invalid_config_batch', 400, 'Each setting needs string name and value.');
            }
            $name = $item['name'];
            if (array_key_exists($name, $normalized)) {
                throw new api_exception('duplicate_config', 400, 'A setting can appear only once per batch.');
            }
            $normalized[$name] = self::normalize($name, $item['value']);
        }

        // Do not explicitly select a disabled destination as the new homepage.
        $dashboard = $normalized['enabledashboard'] ?? (string)(int)(bool)self::current('enabledashboard')['effective'];
        $courses = $normalized['enablemycourses'] ?? (string)(int)(bool)self::current('enablemycourses')['effective'];
        $sitehome = $normalized['enablemyhome'] ?? (string)(int)(bool)self::current('enablemyhome')['effective'];
        if (isset($normalized['defaulthomepage'])) {
            if (($normalized['defaulthomepage'] === '1' && $dashboard === '0')
                || ($normalized['defaulthomepage'] === '3' && $courses === '0')
                || ($normalized['defaulthomepage'] === '0' && $sitehome === '0')) {
                throw new api_exception('invalid_homepage', 400,
                    'The requested homepage is disabled. Enable it in the same batch.');
            }
        }
        return $normalized;
    }

    /**
     * Read both the database value and the effective Moodle configuration value.
     * Differences may indicate config.php overrides.
     *
     * @param string $name Approved core setting.
     * @return array The DB and current runtime values.
     */
    public static function current(string $name): array {
        global $CFG, $DB;
        if (!array_key_exists($name, self::RULES)) {
            throw new api_exception('unsupported_config', 400,
                'This core configuration key is not approved for MCP access.');
        }
        $row = $DB->get_record('config', ['name' => $name], 'id,value');
        return [
            'name' => $name,
            'stored' => $row ? (string)$row->value : null,
            'effective' => property_exists($CFG, $name) ? (string)$CFG->$name : null,
            'stored_in_database' => (bool)$row,
        ];
    }
}
