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
 * bulk_create_courses.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;

use context;
use context_coursecat;
use core_text;
use local_mcp\course\image_service;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\security\capability_guard;
use Throwable;

/**
 * Create several courses with a single preview and confirmation.
 *
 * Already existing courses are never modified, even when skipped.
 */
final class bulk_create_courses extends base_tool {
    /** Maximum number of courses in one MCP request. */
    private const MAX_COURSES = 30;

    public function get_name(): string {
        return 'bulk_create_courses';
    }

    public function get_title(): string {
        return 'Create courses in bulk';
    }

    public function get_description(): string {
        return 'Validate and preview up to 30 Moodle courses, detect duplicate shortname/idnumber, '
            . 'and create the nonexisting courses with one confirmation. '
            . 'HTML summaries are required; optional Base64/HTTPS covers are uploaded with each course. '
            . 'Existing courses are never edited. Use excluded_categoryids to forbid test category trees.';
    }

    public function get_required_capability(): string {
        return 'moodle/course:create';
    }

    public function get_input_schema(): array {
        $course = [
            'type' => 'object',
            'properties' => [
                'fullname' => ['type' => 'string', 'minLength' => 1],
                'shortname' => ['type' => 'string', 'minLength' => 1],
                'categoryid' => ['type' => 'integer', 'minimum' => 1],
                'idnumber' => ['type' => 'string'],
                'summary' => ['type' => 'string', 'minLength' => 1,
                    'description' => 'HTML description; required to prevent incomplete courses.'],
                'summaryformat' => ['type' => 'integer', 'enum' => [1],
                    'description' => '1 = HTML.'],
                'visible' => ['type' => 'boolean'],
                'image_base64' => ['type' => 'string',
                    'description' => 'Optional Base64 PNG/JPEG/WebP, up to 5 MiB decoded.'],
                'image_url' => ['type' => 'string',
                    'description' => 'Optional public HTTPS PNG/JPEG/WebP image URL.'],
            ],
            'required' => ['fullname', 'shortname', 'categoryid', 'summary'],
            'additionalProperties' => false,
        ];
        return $this->object_schema([
            'courses' => ['type' => 'array', 'minItems' => 1,
                'maxItems' => self::MAX_COURSES, 'items' => $course],
            'skip_existing' => ['type' => 'boolean',
                'description' => 'Skip existing courses matched by shortname or nonempty idnumber (default true).'],
            'excluded_categoryids' => ['type' => 'array',
                'description' => 'Category IDs which must not receive courses, including all of their descendants.',
                'items' => ['type' => 'integer', 'minimum' => 1]],
        ], ['courses']);
    }

    public function resolve_context(array $arguments): context {
        if (empty($arguments['courses']) || !is_array($arguments['courses'])
                || !isset($arguments['courses'][0]['categoryid'])) {
            throw new api_exception('invalid_courses', 400);
        }
        return context_coursecat::instance((int)$arguments['courses'][0]['categoryid'], MUST_EXIST);
    }

    public function supports_dry_run(): bool {
        return true;
    }

    /**
     * Check the entire batch before creating anything, including permissions
     * on each target category. Never return images or HTML bodies in previews.
     *
     * @param array $arguments MCP arguments.
     * @param authenticated_identity $identity Connected user.
     * @param bool $validateimages Whether to validate image payloads in advance.
     * @return array Normalised rows for internal use, including skipped matches.
     */
    private function inspect(array $arguments, authenticated_identity $identity, bool $validateimages): array {
        global $DB;

        $input = $arguments['courses'] ?? null;
        if (!is_array($input) || !array_is_list($input) || count($input) < 1
                || count($input) > self::MAX_COURSES) {
            throw new api_exception('invalid_courses', 400,
                'Provide between 1 and 30 courses in a list.');
        }
        $excluded = $arguments['excluded_categoryids'] ?? [];
        if (!is_array($excluded)) {
            throw new api_exception('invalid_categories', 400);
        }
        $excluded = array_map('intval', $excluded);
        $skip = (bool)($arguments['skip_existing'] ?? true);
        $shortnames = [];
        $idnumbers = [];
        $rows = [];

        foreach ($input as $index => $course) {
            if (!is_array($course) || !isset($course['fullname'], $course['shortname'],
                    $course['categoryid'], $course['summary'])) {
                throw new api_exception('invalid_course', 400,
                    'Every course requires fullname, shortname, categoryid and summary.');
            }
            $fullname = trim(clean_param((string)$course['fullname'], PARAM_TEXT));
            $shortname = trim(clean_param((string)$course['shortname'], PARAM_TEXT));
            $idnumber = trim(clean_param((string)($course['idnumber'] ?? ''), PARAM_TEXT));
            $summary = clean_param((string)$course['summary'], PARAM_CLEANHTML);
            $categoryid = (int)$course['categoryid'];
            if ($fullname === '' || $shortname === '' || trim(strip_tags($summary)) === ''
                    || $categoryid < 1 || (isset($course['summaryformat']) &&
                    (int)$course['summaryformat'] !== FORMAT_HTML)) {
                throw new api_exception('invalid_course', 400,
                    'Each course requires a valid name, HTML summary, category and summaryformat=1.');
            }
            if (core_text::strlen($fullname) > 254 || core_text::strlen($shortname) > 255
                    || core_text::strlen($idnumber) > 100) {
                throw new api_exception('invalid_course', 400, 'Course name or code is too long.');
            }

            $category = $DB->get_record('course_categories', ['id' => $categoryid],
                'id, name, path', MUST_EXIST);
            $ancestors = array_map('intval', explode('/', trim($category->path, '/')));
            if (array_intersect($excluded, $ancestors)) {
                throw new api_exception('excluded_category', 400,
                    'A course points inside an excluded category tree.');
            }

            capability_guard::check('moodle/course:create',
                context_coursecat::instance($categoryid, MUST_EXIST), $identity->userid);

            $shortkey = core_text::strtolower($shortname);
            if (isset($shortnames[$shortkey])) {
                throw new api_exception('duplicate_batch_shortname', 409,
                    'The same shortname appears more than once in this batch.');
            }
            $shortnames[$shortkey] = true;
            if ($idnumber !== '') {
                $idkey = core_text::strtolower($idnumber);
                if (isset($idnumbers[$idkey])) {
                    throw new api_exception('duplicate_batch_idnumber', 409,
                        'The same idnumber appears more than once in this batch.');
                }
                $idnumbers[$idkey] = true;
            }

            $byshort = $DB->get_record('course', ['shortname' => $shortname],
                'id, fullname, shortname, idnumber');
            $byid = $idnumber !== '' ? $DB->get_record('course', ['idnumber' => $idnumber],
                'id, fullname, shortname, idnumber') : false;
            if ($byshort && $byid && (int)$byshort->id !== (int)$byid->id) {
                throw new api_exception('course_duplicate_conflict', 409,
                    'Shortname and idnumber already belong to different Moodle courses.');
            }
            $existing = $byshort ?: $byid;
            if ($existing && !$skip) {
                throw new api_exception('course_already_exists', 409,
                    'At least one shortname or idnumber already exists.');
            }

            $imagebase64 = (string)($course['image_base64'] ?? '');
            $imageurl = (string)($course['image_url'] ?? '');
            if ($imagebase64 !== '' && $imageurl !== '') {
                throw new api_exception('image_source_required', 400,
                    'Provide at most one image source per course.');
            }
            $imageargs = [];
            if ($imagebase64 !== '') {
                $imageargs['image_base64'] = $imagebase64;
            } else if ($imageurl !== '') {
                $imageargs['image_url'] = $imageurl;
            }
            // Validate every new image before starting the DB transaction.
            if ($validateimages && !$existing && $imageargs) {
                image_service::decode($imageargs);
            }

            $rows[] = [
                'index' => $index,
                'fullname' => $fullname,
                'shortname' => $shortname,
                'idnumber' => $idnumber,
                'categoryid' => $categoryid,
                'summary' => $summary,
                'summaryformat' => FORMAT_HTML,
                'visible' => (bool)($course['visible'] ?? true),
                'imageargs' => $imageargs,
                'existing' => $existing ? [
                    'id' => (int)$existing->id,
                    'shortname' => $existing->shortname,
                    'idnumber' => $existing->idnumber,
                ] : null,
            ];
        }
        return $rows;
    }

    public function preview(array $arguments, authenticated_identity $identity): array {
        $rows = $this->inspect($arguments, $identity, false);
        $items = [];
        $tocreate = 0;
        foreach ($rows as $row) {
            $isnew = $row['existing'] === null;
            if ($isnew) {
                $tocreate++;
            }
            $items[] = [
                'index' => $row['index'],
                'shortname' => $row['shortname'],
                'idnumber' => $row['idnumber'],
                'fullname' => $row['fullname'],
                'categoryid' => $row['categoryid'],
                'visible' => $row['visible'],
                'summaryformat' => FORMAT_HTML,
                'summary_bytes' => strlen($row['summary']),
                'image_source' => isset($row['imageargs']['image_base64']) ? 'base64'
                    : (isset($row['imageargs']['image_url']) ? 'https_url' : null),
                'action' => $isnew ? 'create' : 'skip_existing',
                'existing_course' => $row['existing'],
            ];
        }
        return [
            'requested' => count($rows),
            'to_create' => $tocreate,
            'already_existing' => count($rows) - $tocreate,
            'courses' => $items,
            'requires_confirmation' => true,
        ];
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        global $DB;

        // Repeat all validation after approval; categories or duplicate matches
        // could have changed since the preview.
        $rows = $this->inspect($arguments, $identity, true);
        $created = [];
        $skipped = [];
        $creator = new create_course();
        $transaction = $DB->start_delegated_transaction();
        try {
            foreach ($rows as $row) {
                if ($row['existing']) {
                    $skipped[] = [
                        'shortname' => $row['shortname'],
                        'idnumber' => $row['idnumber'],
                        'existing_course' => $row['existing'],
                    ];
                    continue;
                }
                $data = [
                    'fullname' => $row['fullname'],
                    'shortname' => $row['shortname'],
                    'idnumber' => $row['idnumber'],
                    'categoryid' => $row['categoryid'],
                    'summary' => $row['summary'],
                    'summaryformat' => FORMAT_HTML,
                    'visible' => $row['visible'],
                ] + $row['imageargs'];
                $result = $creator->execute($data, $identity);
                $created[] = [
                    'id' => (int)$result['id'],
                    'fullname' => $result['fullname'],
                    'shortname' => $result['shortname'],
                    'idnumber' => $result['idnumber'],
                    'categoryid' => $result['categoryid'],
                    'visible' => $result['visible'],
                    'has_image' => $result['image'] !== null,
                ];
            }
            $transaction->allow_commit();
        } catch (Throwable $e) {
            $transaction->rollback($e);
        }
        return [
            'requested' => count($rows),
            'created_count' => count($created),
            'skipped_count' => count($skipped),
            'created' => $created,
            'skipped' => $skipped,
        ];
    }
}
