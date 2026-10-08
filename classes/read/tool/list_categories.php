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
 * MCP tool to inspect the course category hierarchy.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\read\tool;

use context;
use context_system;
use local_mcp\security\authenticated_identity;

/**
 * List course categories with exact IDs and parent IDs for safe reorganization.
 */
final class list_categories extends base_tool {
    public function get_name(): string {
        return 'list_categories';
    }

    public function get_title(): string {
        return 'List course categories';
    }

    public function get_description(): string {
        return 'List all Moodle course categories, including hidden ones, their IDs and parent '
            . 'IDs. Use the parent links to preserve the existing subcategory hierarchy.';
    }

    public function get_required_capability(): string {
        return 'moodle/category:manage';
    }

    public function get_input_schema(): array {
        return $this->object_schema([]);
    }

    public function resolve_context(array $arguments): context {
        return context_system::instance();
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        global $DB;
        $categories = $DB->get_records('course_categories', [], 'depth ASC, sortorder ASC',
            'id, name, parent, depth, path, visible, idnumber');
        $out = [];
        foreach ($categories as $category) {
            $out[] = [
                'id' => (int)$category->id,
                'name' => $category->name,
                'parentid' => (int)$category->parent,
                'depth' => (int)$category->depth,
                'path' => $category->path,
                'visible' => (bool)$category->visible,
                'idnumber' => $category->idnumber,
            ];
        }
        return ['count' => count($out), 'categories' => $out];
    }
}
