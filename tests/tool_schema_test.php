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
 * MCP tool-schema validation and diagnostics regression tests.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\protocol\tool_schema;

defined('MOODLE_INTERNAL') || die();

/**
 * Verify invalid schemas are normalized or diagnosed before response delivery.
 */
final class tool_schema_test extends advanced_testcase {
    /**
     * @return void
     */
    public function test_empty_properties_are_normalized_and_reported(): void {
        $issues = [];
        $schema = tool_schema::normalize([
            'type' => 'object', 'properties' => [], 'additionalProperties' => false,
        ], $issues);
        $this->assertSame('{"type":"object","properties":{},"additionalProperties":false}',
            json_encode($schema));
        $this->assertSame([[
            'path' => 'inputSchema.properties',
            'expected' => 'object',
            'actual' => 'array',
            'repaired' => true,
        ]], $issues);
    }

    /**
     * @return void
     */
    public function test_nested_objects_keep_required_and_enum_arrays(): void {
        $issues = [];
        $schema = tool_schema::normalize([
            'type' => 'object',
            'properties' => [
                'config' => ['type' => 'object', 'properties' => [], 'required' => []],
                'choices' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => []]],
                'kind' => ['type' => 'string', 'enum' => ['a', 'b']],
            ],
        ], $issues);
        $json = json_decode(json_encode($schema));
        $this->assertIsObject($json->properties);
        $this->assertIsObject($json->properties->config->properties);
        $this->assertIsObject($json->properties->choices->items->properties);
        $this->assertSame([], $json->properties->config->required);
        $this->assertSame(['a', 'b'], $json->properties->kind->enum);
        $this->assertCount(2, $issues);
    }

    /**
     * @return void
     */
    public function test_bad_map_is_not_silently_accepted(): void {
        $issues = [];
        tool_schema::normalize(['type' => 'object', 'properties' => 'not_an_object'], $issues);
        $this->assertSame('inputSchema.properties', $issues[0]['path']);
        $this->assertSame('string', $issues[0]['actual']);
        $this->assertFalse($issues[0]['repaired']);
    }

    /**
     * @return void
     */
    public function test_schema_error_metadata_is_logged_without_secret_values(): void {
        $log = diagnostics::format('WARNING', 'tool_schema_invalid', [
            'tool' => 'learnlingo_get_homepage',
            'provider' => 'theme_learnlingo\\mcp\\get_homepage',
            'schema_path' => 'inputSchema.properties',
            'expected' => 'object',
            'actual' => 'array',
            'resolution' => 'normalized',
            'schema_contents' => 'sensitive',
        ]);
        $decoded = json_decode(substr($log, strlen('[local_mcp] ')), true);
        $this->assertSame('tool_schema_invalid', $decoded['event']);
        $this->assertSame('learnlingo_get_homepage', $decoded['tool']);
        $this->assertSame('inputSchema.properties', $decoded['schema_path']);
        $this->assertSame('normalized', $decoded['resolution']);
        $this->assertArrayNotHasKey('schema_contents', $decoded);
    }
}
