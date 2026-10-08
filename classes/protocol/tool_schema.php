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
 * Diagnose and normalize JSON Schemas exposed by MCP tool providers.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\protocol;

use stdClass;

/**
 * Catch structural JSON Schema errors before the MCP client rejects tools/list.
 */
final class tool_schema {
    /** Name-to-schema mappings, which must always encode as JSON objects. */
    private const MAPS = ['properties', 'patternProperties', '$defs', 'definitions', 'dependentSchemas'];

    /** Single nested schemas, including boolean schemas. */
    private const SINGLES = ['items', 'additionalProperties', 'unevaluatedProperties',
        'propertyNames', 'contains', 'not', 'if', 'then', 'else', 'contentSchema'];

    /** Lists of nested schemas. */
    private const ARRAYS = ['allOf', 'anyOf', 'oneOf', 'prefixItems'];

    /**
     * @param array $schema Input schema.
     * @param array $issues Schema issues returned by reference.
     * @return array Validated and safely normalized schema.
     */
    public static function normalize(array $schema, array &$issues = []): array {
        if (($schema['type'] ?? null) !== 'object') {
            self::issue($issues, 'inputSchema.type', 'object', self::kind($schema['type'] ?? null), false);
            return $schema;
        }
        self::walk($schema, 'inputSchema', $issues);
        return $schema;
    }

    /**
     * @param array $schema JSON Schema fields.
     * @param string $path Location in the schema.
     * @param array $issues Output issues.
     * @return void
     */
    private static function walk(array &$schema, string $path, array &$issues): void {
        foreach ($schema as $keyword => $value) {
            $field = $path . '.' . $keyword;
            if (in_array($keyword, self::MAPS, true)) {
                if (is_array($value)) {
                    if ($value !== [] && array_is_list($value)) {
                        self::issue($issues, $field, 'object', 'array', false);
                        continue;
                    }
                    if ($value === []) {
                        self::issue($issues, $field, 'object', 'array', true);
                    }
                    $value = (object)$value;
                    $schema[$keyword] = $value;
                }
                if (!$value instanceof stdClass) {
                    self::issue($issues, $field, 'object', self::kind($value), false);
                    continue;
                }
                foreach ($value as $name => $child) {
                    $value->{$name} = self::child($child, $field . '.' . $name, $issues);
                }
            } else if (in_array($keyword, self::SINGLES, true)) {
                $schema[$keyword] = self::child($value, $field, $issues);
            } else if (in_array($keyword, self::ARRAYS, true)) {
                if (!is_array($value) || !array_is_list($value)) {
                    self::issue($issues, $field, 'array', self::kind($value), false);
                    continue;
                }
                foreach ($value as $index => $child) {
                    $value[$index] = self::child($child, $field . '.' . $index, $issues);
                }
                $schema[$keyword] = $value;
            } else if ($keyword === 'required') {
                if (!is_array($value) || !array_is_list($value)) {
                    self::issue($issues, $field, 'array', self::kind($value), false);
                } else {
                    foreach ($value as $entry) {
                        if (!is_string($entry)) {
                            self::issue($issues, $field, 'array of strings', 'array', false);
                            break;
                        }
                    }
                }
            } else if ($keyword === 'enum' && !is_array($value)) {
                self::issue($issues, $field, 'array', self::kind($value), false);
            }
        }
        if (($schema['type'] ?? null) === 'object' && !array_key_exists('properties', $schema)) {
            $schema['properties'] = (object)[];
        }
    }

    /**
     * @param mixed $value Nested JSON Schema.
     * @param string $path Schema location.
     * @param array $issues Output issues.
     * @return mixed Normalized schema.
     */
    private static function child($value, string $path, array &$issues) {
        if (is_bool($value)) {
            return $value;
        }
        if (is_array($value)) {
            if ($value !== [] && array_is_list($value)) {
                self::issue($issues, $path, 'object or boolean', 'array', false);
                return $value;
            }
            $value = (object)$value;
        }
        if (!$value instanceof stdClass) {
            self::issue($issues, $path, 'object or boolean', self::kind($value), false);
            return $value;
        }
        $fields = (array)$value;
        self::walk($fields, $path, $issues);
        return (object)$fields;
    }

    /**
     * @param array $issues Output issues.
     * @param string $path Schema location.
     * @param string $expected Expected type.
     * @param string $actual Actual type.
     * @param bool $repaired Safe automatic fix applied.
     * @return void
     */
    private static function issue(array &$issues, string $path, string $expected,
            string $actual, bool $repaired): void {
        $issues[] = ['path' => $path, 'expected' => $expected,
            'actual' => $actual, 'repaired' => $repaired];
    }

    /**
     * @param mixed $value Value to inspect.
     * @return string Data type, without any values.
     */
    private static function kind($value): string {
        if ($value instanceof stdClass) {
            return 'object';
        }
        if (is_array($value)) {
            return 'array';
        }
        return gettype($value);
    }
}
