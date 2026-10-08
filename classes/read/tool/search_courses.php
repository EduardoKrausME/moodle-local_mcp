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
 * search_courses.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\read\tool;

use context;
use context_course;
use context_system;
use local_mcp\security\authenticated_identity;

/**
 * Search courses the connected Moodle user can administer.
 *
 * Unlike get_courses(), this search does not pre-filter hidden courses.
 */
final class search_courses extends base_tool {
    public function get_name(): string {
        return 'search_courses';
    }

    public function get_title(): string {
        return 'Search courses';
    }

    public function get_description(): string {
        return 'Search courses the user can manage, including invisible courses. '
            . 'Supports an exact shortname lookup to prevent duplicate course creation. '
            . 'Returns raw HTML summary and category metadata.';
    }

    public function get_required_capability(): string {
        return 'moodle/course:view';
    }

    public function get_input_schema(): array {
        return $this->object_schema([
            'query' => ['type' => 'string', 'description' => 'Substring of fullname or shortname.'],
            'shortname' => ['type' => 'string',
                'description' => 'Exact shortname filter, useful for detecting an existing course.'],
            'includehidden' => ['type' => 'boolean',
                'description' => 'Include hidden courses that the connected user can manage (default true).'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200],
            'offset' => ['type' => 'integer', 'minimum' => 0],
        ]);
    }

    public function resolve_context(array $arguments): context {
        return context_system::instance();
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        global $DB;

        $query = trim((string)($arguments['query'] ?? ''));
        $shortname = isset($arguments['shortname']) ? trim((string)$arguments['shortname']) : null;
        $includehidden = (bool)($arguments['includehidden'] ?? true);
        $limit = min(200, max(1, (int)($arguments['limit'] ?? 100)));
        $offset = max(0, (int)($arguments['offset'] ?? 0));

        $where = ['c.id <> :siteid'];
        $params = ['siteid' => SITEID];
        if ($shortname !== null) {
            $where[] = 'c.shortname = :shortname';
            $params['shortname'] = $shortname;
        }
        if ($query !== '') {
            $fullnamefilter = $DB->sql_like('c.fullname', ':fullnamematch', false);
            $shortnamefilter = $DB->sql_like('c.shortname', ':shortnamematch', false);
            $where[] = "({$fullnamefilter} OR {$shortnamefilter})";
            $pattern = '%' . $DB->sql_like_escape($query) . '%';
            $params['fullnamematch'] = $pattern;
            $params['shortnamematch'] = $pattern;
        }
        if (!$includehidden) {
            $where[] = 'c.visible = :visible';
            $params['visible'] = 1;
        }

        // Stream results; permissions are checked per course so hidden courses
        // do not disappear through get_courses()'s enrolment/category filters.
        $sql = "SELECT c.id, c.fullname, c.shortname, c.category, c.idnumber,
                       c.summary, c.summaryformat, c.visible
                  FROM {course} c
                 WHERE " . implode(' AND ', $where) .
                 " ORDER BY c.sortorder ASC, c.id ASC";
        $records = $DB->get_recordset_sql($sql, $params);
        $courses = [];
        $skipped = 0;
        $hasmore = false;
        try {
            foreach ($records as $course) {
                $context = context_course::instance((int)$course->id, IGNORE_MISSING);
                if (!$context || !has_capability('moodle/course:update', $context, $identity->userid)) {
                    continue;
                }
                if (!$includehidden && !(bool)$course->visible) {
                    continue;
                }
                if ($skipped++ < $offset) {
                    continue;
                }
                if (count($courses) >= $limit) {
                    $hasmore = true;
                    break;
                }
                $courses[] = [
                    'id' => (int)$course->id,
                    'fullname' => $course->fullname,
                    'shortname' => $course->shortname,
                    'idnumber' => $course->idnumber,
                    'categoryid' => (int)$course->category,
                    'summary' => $course->summary,
                    'summaryformat' => (int)$course->summaryformat,
                    'visible' => (bool)$course->visible,
                ];
            }
        } finally {
            $records->close();
        }
        return ['count' => count($courses), 'offset' => $offset,
            'hasmore' => $hasmore, 'courses' => $courses];
    }
}
