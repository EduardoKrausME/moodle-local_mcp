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
 * Test typography normalization of incoming WRITE arguments.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\protocol\write_text_normalizer;

/**
 * Tests for MCP WRITE argument typography normalization.
 *
 * @covers \local_mcp\protocol\write_text_normalizer
 */
final class write_text_normalizer_test extends advanced_testcase {
    /**
     * Normalize deeply nested text without changing types, keys or other punctuation.
     */
    public function test_normalizes_nested_strings_only(): void {
        $original = [
            'title' => 'Uma aula — prática',
            'html' => '<p>Primeiro — segundo</p>',
            'nested' => [
                ['text' => 'Um—dois—três', 'answers' => ['Sim — não', 'Fim']],
            ],
            'unchanged' => 'en dash – e hífen -',
            'count' => 12,
            'enabled' => false,
            'missing' => null,
        ];

        $normalized = write_text_normalizer::normalize($original);

        $this->assertSame('Uma aula - prática', $normalized['title']);
        $this->assertSame('<p>Primeiro - segundo</p>', $normalized['html']);
        $this->assertSame('Um-dois-três', $normalized['nested'][0]['text']);
        $this->assertSame(['Sim - não', 'Fim'], $normalized['nested'][0]['answers']);
        $this->assertSame('en dash – e hífen -', $normalized['unchanged']);
        $this->assertSame(12, $normalized['count']);
        $this->assertFalse($normalized['enabled']);
        $this->assertNull($normalized['missing']);
        $this->assertSame('Uma aula — prática', $original['title']);
        $this->assertSame($normalized, write_text_normalizer::normalize($normalized));
    }

    /**
     * Preserve JSON objects and arrays for schema validation, including empty containers.
     */
    public function test_preserves_json_containers_and_original_object(): void {
        $original = json_decode('{"title":"A\u2014B","nested":[{"text":"C\u2014D"}],"object":{},"list":[]}');
        $normalized = write_text_normalizer::normalize($original);

        $this->assertInstanceOf(\stdClass::class, $normalized);
        $this->assertSame('A-B', $normalized->title);
        $this->assertInstanceOf(\stdClass::class, $normalized->nested[0]);
        $this->assertSame('C-D', $normalized->nested[0]->text);
        $this->assertInstanceOf(\stdClass::class, $normalized->object);
        $this->assertSame([], $normalized->list);
        $this->assertSame('A—B', $original->title);
        $this->assertSame('C—D', $original->nested[0]->text);
    }
}
