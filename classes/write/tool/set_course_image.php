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

namespace local_mcp\write\tool;

use context;
use context_course;
use local_mcp\course\image_service;
use local_mcp\security\authenticated_identity;
use local_mcp\security\capability_guard;

/** Replace the course image through Moodle's own File API. */
final class set_course_image extends base_tool {
    /** @return string Tool identifier. */
    public function get_name(): string {
        return 'set_course_image';
    }

    /** @return string Tool title. */
    public function get_title(): string {
        return 'Set course image';
    }

    /** @return string Tool description. */
    public function get_description(): string {
        return 'Replace the course cover image using a Base64 PNG/JPEG/WebP or a publicly downloadable HTTPS URL. '
            . 'Requires confirmation, preserves non-image overview files and supports a content-hash concurrency check.';
    }

    /** @return string Capability. */
    public function get_required_capability(): string {
        return 'moodle/course:update';
    }

    /** @return array Input schema. */
    public function get_input_schema(): array {
        return $this->object_schema([
            'courseid' => ['type' => 'integer', 'minimum' => 1],
            'image_base64' => ['type' => 'string', 'description' => 'Base64 PNG, JPEG or WebP (optionally a data URI), max 5 MiB decoded.'],
            'image_url' => ['type' => 'string', 'description' => 'Public HTTPS image download URL (never a private/internal URL).'],
            'expected_contenthash' => ['type' => 'string', 'description' => 'Optional current image hash from get_course_image to prevent overwriting a changed image.'],
        ], ['courseid']);
    }

    /**
     * @param array $arguments MCP input.
     * @return context Course context.
     */
    public function resolve_context(array $arguments): context {
        return context_course::instance((int)$arguments['courseid'], MUST_EXIST);
    }

    /**
     * Return a safe preview without copying Base64 into chat output or logs.
     *
     * @param array $arguments MCP input.
     * @param authenticated_identity $identity Authenticated identity.
     * @return array Safe preview.
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        $context = $this->resolve_context($arguments);
        capability_guard::check('moodle/course:changesummary', $context, $identity->userid);
        $images = image_service::read((int)$arguments['courseid'], false);
        return [
            'courseid' => (int)$arguments['courseid'],
            'current_image' => $images['image'],
            'new_image_source' => !empty($arguments['image_url']) ? 'https_url' : 'base64',
            'new_image_size_estimate' => isset($arguments['image_base64'])
                ? (int)(strlen((string)$arguments['image_base64']) * 3 / 4) : null,
            'replaces_image_count' => count($images['images']),
        ];
    }

    /**
     * @param array $arguments MCP input.
     * @param authenticated_identity $identity Authenticated identity.
     * @return array New image metadata.
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        $context = $this->resolve_context($arguments);
        capability_guard::check('moodle/course:changesummary', $context, $identity->userid);
        return image_service::replace((int)$arguments['courseid'], $arguments, $identity->userid);
    }
}
