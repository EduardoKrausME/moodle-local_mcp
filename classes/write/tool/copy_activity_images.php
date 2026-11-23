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
 * Copy page images into an MCP activity without Base64 or an HTTP round-trip.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;

use context;
use context_module;
use local_mcp\course\activity_image_copier;
use local_mcp\security\authenticated_identity;

/**
 * Copy Page attachments and authorised referenced course images via Moodle File API.
 */
final class copy_activity_images extends base_tool {
    /**
     * @return string Tool identifier.
     */
    public function get_name(): string {
        return 'copy_activity_images';
    }

    /**
     * @return string Display title.
     */
    public function get_title(): string {
        return 'Copy images between Moodle activities';
    }

    /**
     * @return string Description.
     */
    public function get_description(): string {
        return 'Copy stored Page images and authorised same-course images referenced in Page HTML to mod_mcp/assets, '
            . 'entirely inside Moodle. Returns original and new pluginfile URLs for updating HTML. '
            . 'Never changes the original page, the destination HTML or publication state.';
    }

    /**
     * @return string Target-side permission checked by the central MCP dispatcher.
     */
    public function get_required_capability(): string {
        return 'mod/mcp:manage';
    }

    /**
     * @return array JSON input schema.
     */
    public function get_input_schema(): array {
        return $this->object_schema([
            'sourcecmid' => ['type' => 'integer', 'minimum' => 1,
                'description' => 'Course module ID of the source mod_page.'],
            'destinationcmid' => ['type' => 'integer', 'minimum' => 1,
                'description' => 'Course module ID of the destination mod_mcp.'],
            'source_filearea' => ['type' => 'string', 'enum' => ['all', 'content', 'intro'],
                'description' => 'Page file area. Default: all (content and intro).'],
            'source_filename' => ['type' => 'string', 'minLength' => 1,
                'description' => 'Optional exact filename to copy instead of all images.'],
            'expected_manifest_sha256' => ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$',
                'description' => 'Optional SHA-256 from the preview; abort if the source or destination changed.'],
        ], ['sourcecmid', 'destinationcmid']);
    }

    /**
     * @param array $arguments Validated inputs.
     * @return context Destination module context.
     */
    public function resolve_context(array $arguments): context {
        return context_module::instance((int)$arguments['destinationcmid'], MUST_EXIST);
    }

    /**
     * @return bool Preview is supported without any file writes.
     */
    public function supports_dry_run(): bool {
        return true;
    }

    /**
     * @param array $arguments Tool inputs.
     * @param authenticated_identity $identity Authenticated Moodle identity.
     * @return array Copy plan and content references.
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        return activity_image_copier::preview($arguments, (int)$identity->userid);
    }

    /**
     * @param array $arguments Tool inputs.
     * @param authenticated_identity $identity Authenticated Moodle identity.
     * @return array Copied or reused images and new URLs.
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        return activity_image_copier::copy($arguments, (int)$identity->userid);
    }
}
