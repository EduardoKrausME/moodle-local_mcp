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
 * Update a course category using Moodle's category API.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;

use context;
use context_coursecat;
use context_system;
use core_course_category;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\security\capability_guard;

/**
 * Rename a category or move it without flattening its descendants.
 */
final class update_category extends base_tool {
    public function get_name(): string {
        return 'update_category';
    }

    public function get_title(): string {
        return 'Update course category';
    }

    public function get_description(): string {
        return 'Rename or move an existing course category under another parent. '
            . 'Moving an existing category preserves its subcategory tree and the courses in it. '
            . 'Supports optional category visibility changes.';
    }

    public function get_required_capability(): string {
        return 'moodle/category:manage';
    }

    public function get_input_schema(): array {
        return $this->object_schema([
            'categoryid' => ['type' => 'integer', 'minimum' => 1],
            'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
            'parentid' => ['type' => 'integer', 'minimum' => 0,
                'description' => 'New parent category ID, 0 for top-level.'],
            'visible' => ['type' => 'boolean'],
        ], ['categoryid']);
    }

    public function resolve_context(array $arguments): context {
        return context_coursecat::instance((int)$arguments['categoryid'], MUST_EXIST);
    }

    public function supports_dry_run(): bool {
        return true;
    }

    /**
     * Verify both old/new parent permissions and prevent hierarchy cycles.
     *
     * @param array $arguments Arguments to change.
     * @param authenticated_identity $identity Requesting user.
     * @return array Category, sanitized changes.
     */
    private function validate_update(array $arguments, authenticated_identity $identity): array {
        $categoryid = (int)$arguments['categoryid'];
        $category = core_course_category::get($categoryid, MUST_EXIST, true);
        $updates = [];

        if (array_key_exists('name', $arguments)) {
            $name = trim(clean_param((string)$arguments['name'], PARAM_TEXT));
            if ($name === '') {
                throw new api_exception('invalid_category_name', 400);
            }
            $updates['name'] = $name;
        }
        if (array_key_exists('visible', $arguments)) {
            $updates['visible'] = (int)(bool)$arguments['visible'];
        }
        if (array_key_exists('parentid', $arguments)) {
            $newparentid = (int)$arguments['parentid'];
            if ($newparentid < 0) {
                throw new api_exception('invalid_category_parent', 400);
            }
            if ($newparentid !== (int)$category->parent) {
                if ($newparentid === $categoryid) {
                    throw new api_exception('category_cycle', 400);
                }
                $parent = core_course_category::get($newparentid, MUST_EXIST, true);
                if ($newparentid > 0 &&
                        (str_starts_with($parent->path . '/', $category->path . '/'))) {
                    throw new api_exception('category_cycle', 400);
                }
                $oldparentctx = $category->parent > 0
                    ? context_coursecat::instance((int)$category->parent) : context_system::instance();
                $newparentctx = $newparentid > 0
                    ? context_coursecat::instance($newparentid) : context_system::instance();
                capability_guard::check('moodle/category:manage', $oldparentctx, $identity->userid);
                capability_guard::check('moodle/category:manage', $newparentctx, $identity->userid);
                $updates['parent'] = $newparentid;
            }
        }
        if (!$updates) {
            throw new api_exception('no_changes', 400);
        }
        return [$category, $updates];
    }

    public function preview(array $arguments, authenticated_identity $identity): array {
        [$category, $updates] = $this->validate_update($arguments, $identity);
        return [
            'id' => (int)$category->id,
            'name_before' => $category->name,
            'parentid_before' => (int)$category->parent,
            'visible_before' => (bool)$category->visible,
            'changes' => $updates,
            'course_categories_within_moved_subtree_remain_nested' => true,
            'courses_remain_in_their_categories' => true,
        ];
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        [$category, $updates] = $this->validate_update($arguments, $identity);
        $category->update($updates);
        $updated = core_course_category::get((int)$category->id, MUST_EXIST, true);
        return [
            'id' => (int)$updated->id,
            'name' => $updated->name,
            'parentid' => (int)$updated->parent,
            'path' => $updated->path,
            'visible' => (bool)$updated->visible,
        ];
    }
}
