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
 * MCP course image tool.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\read\tool;

use context;
use local_mcp\course\image_service;
use local_mcp\security\authenticated_identity;

/**
 * Read and optionally visualize the course's current image.
 */
final class get_course_image extends base_tool {
    /**
     * get_name
     *
     * @return string
     */
    public function get_name(): string {
        return 'get_course_image';
    }

    /**
     * get_title
     *
     * @return string
     */
    public function get_title(): string {
        return 'Get course image';
    }

    /**
     * get_description
     *
     * @return string
     */
    public function get_description(): string {
        return 'Inspect the current Moodle course cover image, its URL, size, dimensions and hash. '
            . 'Returns image content for visual analysis when the image is small enough.';
    }

    /**
     * get_required_capability
     *
     * @return string
     */
    public function get_required_capability(): string {
        return 'moodle/course:view';
    }

    /**
     * get_input_schema
     *
     * @return array
     */
    public function get_input_schema(): array {
        return $this->object_schema([
            'courseid' => ['type' => 'integer', 'minimum' => 1],
            'include_image' => ['type' => 'boolean', 'description' => 'Include the current image for visual inspection (default true).'],
        ], ['courseid']);
    }

    /**
     * resolve_context
     *
     * @param array $arguments
     * @return context
     */
    public function resolve_context(array $arguments): context {
        return $this->course_context((int)$arguments['courseid']);
    }

    /**
     * execute
     *
     * @param array $arguments
     * @param authenticated_identity $identity
     * @return array
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        return image_service::read((int)$arguments['courseid'], (bool)($arguments['include_image'] ?? true));
    }
}
