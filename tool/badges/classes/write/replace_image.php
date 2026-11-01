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
 * Replace an inactive badge's PNG image after explicit confirmation.
 *
 * @package   mcptool_badges
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_badges\write;

use context;
use context_course;
use context_system;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\write\tool\base_tool;
use mcptool_badges\image;

/**
 * Only an inactive and unissued badge may have its image replaced.
 */
final class replace_image extends base_tool {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string {
        return "badges_replace_image";
    }
    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string {
        return "Badges: replace an inactive badge image";
    }
    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string {
        return "Replace a badge image using PNG from image_url or image_base64 (never both). Requires the badge to be inactive and have no awards; use badges_get for expected_timemodified. Transparent PNG edges required by default. WRITE preview/confirmation enforced.";
    }
    /**
     * Method get_required_capability.
     *
     * @return string Return value.
     */
    public function get_required_capability(): string {
        return "moodle/badges:configuredetails";
    }
    /**
     * Method get_input_schema.
     *
     * @return array Return value.
     */
    public function get_input_schema(): array {
        return $this->object_schema([
            "badgeid" => ["type" => "integer", "minimum" => 1],
            "expected_timemodified" => ["type" => "integer", "minimum" => 0],
            "image_url" => ["type" => "string", "minLength" => 1],
            "image_base64" => ["type" => "string", "minLength" => 1],
            "require_transparency" => ["type" => "boolean"],
        ], ["badgeid", "expected_timemodified"]);
    }
    /**
     * Method resolve_context.
     *
     * @param array $arguments Parameter arguments.
     * @return context Return value.
     */
    public function resolve_context(array $arguments): context {
        global $DB;
        $badge = $DB->get_record("badge", ["id" => (int)$arguments["badgeid"]], "id,courseid");
        if (!$badge) {
            throw new api_exception("badge_not_found", 404);
        }
        return $badge->courseid ? context_course::instance((int)$badge->courseid)
            : context_system::instance();
    }
    /**
     * Method checked_badge.
     *
     * @param array $arguments Parameter arguments.
     * @return \badge Return value.
     */
    private function checked_badge(array $arguments): \badge {
        global $CFG, $DB;
        require_once($CFG->libdir . "/badgeslib.php");
        $badge = new \badge((int)$arguments["badgeid"]);
        if ((int)$badge->timemodified !== (int)$arguments["expected_timemodified"]) {
            throw new api_exception("revision_conflict", 409);
        }
        if ((int)$badge->status !== BADGE_STATUS_INACTIVE ||
            $DB->record_exists("badge_issued", ["badgeid" => $badge->id])) {
            throw new api_exception("Only inactive badges without existing awards can change images", 409);
        }
        return $badge;
    }
    /**
     * Method preview.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        $badge = $this->checked_badge($arguments);
        if (empty($arguments["image_url"]) === empty($arguments["image_base64"])) {
            throw new api_exception("Provide exactly one image source", 400);
        }
        return [
            "action" => "replace_badge_image", "badgeid" => (int)$badge->id,
            "name" => $badge->name, "expected_timemodified" => (int)$badge->timemodified,
            "image_source" => !empty($arguments["image_url"]) ? "https_url" : "base64",
            "image" => image::preview_image($arguments),
            "url_download_after_confirmation" => !empty($arguments["image_url"]),
            "requires_explicit_confirmation" => true,
        ];
    }
    /**
     * Method execute.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        global $DB;
        $badge = $this->checked_badge($arguments);
        $img = image::load($arguments);
        $transaction = $DB->start_delegated_transaction();
        image::save($badge, $img["bytes"]);
        $badge->timemodified = time();
        $badge->usermodified = $identity->userid;
        $badge->save();
        $transaction->allow_commit();
        return [
            "badgeid" => (int)$badge->id, "updated" => true,
            "timemodified" => (int)$badge->timemodified,
            "image_sha256" => $img["sha256"], "transparent_edges" => $img["transparent_edges"],
        ];
    }
}
