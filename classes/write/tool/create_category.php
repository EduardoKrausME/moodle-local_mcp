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
 * Create a Moodle course category using core APIs.
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
 * Create a top-level or nested course category.
 */
final class create_category extends base_tool {
    public function get_name(): string {
        return 'create_category';
    }

    public function get_title(): string {
        return 'Create course category';
    }

    public function get_description(): string {
        return 'Create a Moodle course category. Use parentid=0 for a top-level category '
            . 'such as Teste. Does not move courses or existing categories.';
    }

    public function get_required_capability(): string {
        return 'moodle/category:manage';
    }

    public function get_input_schema(): array {
        return $this->object_schema([
            'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
            'parentid' => ['type' => 'integer', 'minimum' => 0, 'description' => '0 for a top-level category.'],
        ], ['name', 'parentid']);
    }

    public function resolve_context(array $arguments): context {
        return context_system::instance();
    }

    public function supports_dry_run(): bool {
        return true;
    }

    /**
     * Validate permissions and prevent accidental duplicate categories.
     */
    private function validated_input(array $arguments, authenticated_identity $identity): array {
        global $DB;
        $name = trim(clean_param((string)$arguments['name'], PARAM_TEXT));
        $parentid = (int)$arguments['parentid'];
        if ($name === '' || $parentid < 0) {
            throw new api_exception('invalid_category', 400);
        }
        if ($parentid > 0) {
            $parent = core_course_category::get($parentid, MUST_EXIST, true);
            capability_guard::check('moodle/category:manage', context_coursecat::instance($parent->id),
                $identity->userid);
            if (!$parent->can_create_subcategory()) {
                throw new api_exception('permission_denied', 403);
            }
        } else if (!core_course_category::can_create_top_level_category()) {
            throw new api_exception('permission_denied', 403);
        }
        if ($DB->record_exists('course_categories', ['name' => $name, 'parent' => $parentid])) {
            throw new api_exception('category_already_exists', 409);
        }
        return [$name, $parentid];
    }

    public function preview(array $arguments, authenticated_identity $identity): array {
        [$name, $parentid] = $this->validated_input($arguments, $identity);
        return ['operation' => 'create_category', 'name' => $name, 'parentid' => $parentid,
            'changes_existing_categories' => false, 'changes_existing_courses' => false];
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        [$name, $parentid] = $this->validated_input($arguments, $identity);
        $category = core_course_category::create(['name' => $name, 'parent' => $parentid]);
        return ['id' => (int)$category->id, 'name' => $category->name,
            'parentid' => (int)$category->parent];
    }
}
