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
 * Read tool documenting the badge image design requirements.
 *
 * @package   mcptool_badges
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_badges\read;

use context;
use context_course;
use context_system;
use local_mcp\read\tool\base_tool;
use local_mcp\security\authenticated_identity;

/**
 * Give an agent actionable guidance before it generates a badge picture.
 */
final class image_guide extends base_tool {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string {
        return "badges_image_guide";
    }
    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string {
        return "Badges: transparent image creation guide";
    }
    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string {
        return "Detailed design instructions for a Moodle badge, including a real transparent PNG background, URL/Base64 delivery, validation and manual activation.";
    }
    /**
     * Method get_required_capability.
     *
     * @return string Return value.
     */
    public function get_required_capability(): string {
        return "moodle/badges:viewbadges";
    }
    /**
     * Method get_input_schema.
     *
     * @return array Return value.
     */
    public function get_input_schema(): array {
        return $this->object_schema(["courseid" => ["type" => "integer", "minimum" => 0]]);
    }
    /**
     * Method resolve_context.
     *
     * @param array $arguments Parameter arguments.
     * @return context Return value.
     */
    public function resolve_context(array $arguments): context {
        return !empty($arguments["courseid"])
            ? context_course::instance((int)$arguments["courseid"], MUST_EXIST)
            : context_system::instance();
    }
    /**
     * Method execute.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        return [
            "canvas" => "Square 512x512 PNG recommended (256-1024 pixels accepted).",
            "background" => "Fully transparent outside the emblem. Export PNG with real alpha transparency (RGBA); white, checkerboard patterns, JPEG and SVG are not transparent PNGs.",
            "composition" => "Single centered symbol with a 10-15% clear margin. Use bold silhouettes, legible contrast, restrained palette and no tiny text. Make it recognizable at 64x64.",
            "accessibility" => "Avoid relying solely on colour; describe achievement in name and description, not only in the artwork. Do not include personal data.",
            "size" => "Optimise PNG to 256 KiB or less. Retain alpha when compressing. Check the four corners on both light and dark previews.",
            "image_sources" => [
                "image_url: a direct public HTTPS URL to a PNG file, no login, cookies or redirects.",
                "image_base64: raw Base64 PNG bytes or data:image/png;base64,...; never supply both sources.",
            ],
            "example_prompt" => "Create a 512 x 512 pixel square Moodle achievement badge: one central stylised graduation cap and laurel wreath, crisp shapes, balanced gold/navy palette, no text, no shadow plate, 12% outer breathing room, transparent RGBA background, no white pixels outside the artwork. Deliver an optimised transparent PNG.",
            "creation_flow" => "Call badges_list to check for duplicates, generate or supply PNG, call badges_create and review its preview, explicitly confirm the same arguments and token. The new badge remains inactive. Configure criteria and then enable it in Moodle; badge creation alone does not award it.",
            "image_replacement" => "For an inactive badge with no awards, call badges_get to obtain timemodified, then badges_replace_image with expected_timemodified and confirmation.",
        ];
    }
}
