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
use context_coursecat;
use context_system;
use local_mcp\security\authenticated_identity;

/**
 * Return the category tree to authorised Moodle category managers.
 */
final class list_categories extends base_tool {
    public function get_name(): string {
        return 'list_categories';
    }

    public function get_title(): string {
        return 'List course categories';
    }

    public function get_description(): string {
        return 'List authorised course categories, optionally including hidden categories '
            . 'and their direct course counts. Returns id, name, parent, visible and coursecount.';
    }

    public function get_required_capability(): string {
        return 'moodle/category:manage';
    }

    public function get_input_schema(): array {
        return $this->object_schema([
            'includehidden' => [
                'type' => 'boolean',
                'description' => 'Include hidden categories (default false); requires permission to manage them.',
            ],
            'includecoursecount' => [
                'type' => 'boolean',
                'description' => 'Include the direct number of courses in each category (default false).',
            ],
        ]);
    }

    public function resolve_context(array $arguments): context {
        return context_system::instance();
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        global $DB;

        $includehidden = (bool)($arguments['includehidden'] ?? false);
        $includecoursecount = (bool)($arguments['includecoursecount'] ?? false);

        $counts = [];
        if ($includecoursecount) {
            $sql = "SELECT category, COUNT(id) AS total
                      FROM {course}
                     WHERE id <> :siteid
                  GROUP BY category";
            foreach ($DB->get_records_sql($sql, ['siteid' => SITEID]) as $count) {
                $counts[(int)$count->category] = (int)$count->total;
            }
        }

        $categories = $DB->get_records('course_categories', [], 'depth ASC, sortorder ASC',
            'id, name, parent, depth, path, visible, idnumber');
        $out = [];
        foreach ($categories as $category) {
            $context = context_coursecat::instance((int)$category->id, IGNORE_MISSING);
            if (!$context || !has_capability('moodle/category:manage', $context, $identity->userid)) {
                continue;
            }
            if (!$includehidden && !(bool)$category->visible) {
                continue;
            }
            $out[] = [
                'id' => (int)$category->id,
                'name' => $category->name,
                'parent' => (int)$category->parent,
                'parentid' => (int)$category->parent,
                'visible' => (bool)$category->visible,
                'coursecount' => $includecoursecount
                    ? ($counts[(int)$category->id] ?? 0) : null,
                'depth' => (int)$category->depth,
                'path' => $category->path,
                'idnumber' => $category->idnumber,
            ];
        }
        return ['count' => count($out), 'categories' => $out];
    }
}
