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
 * Create a confirmed Moodle badge with an imported PNG image.
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
use moodle_url;

/**
 * Creates an inactive native badge; criteria and activation are deliberate later steps.
 */
final class create_badge extends base_tool {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string {
        return "badges_create";
    }
    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string {
        return "Badges: create with PNG from URL or Base64";
    }
    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string {
        return "Create a native site/course badge with a transparent square PNG from image_url (public HTTPS) or image_base64 (raw Base64 or PNG data URI). One source only, maximum 256 KiB, 256-1024 square, real alpha transparency on edges by default. First use badges_image_guide for detailed art instructions. WRITE requires preview and explicit confirmation. Creates an INACTIVE badge: criteria/activation must be configured in Moodle after creation.";
    }
    /**
     * Method get_required_capability.
     *
     * @return string Return value.
     */
    public function get_required_capability(): string {
        return "moodle/badges:createbadge";
    }
    /**
     * Method get_input_schema.
     *
     * @return array Return value.
     */
    public function get_input_schema(): array {
        return $this->object_schema([
            "courseid" => ["type" => "integer", "minimum" => 0],
            "name" => ["type" => "string", "minLength" => 1, "maxLength" => 255],
            "description" => ["type" => "string", "minLength" => 1],
            "image_url" => ["type" => "string", "minLength" => 1],
            "image_base64" => ["type" => "string", "minLength" => 1],
            "require_transparency" => ["type" => "boolean"],
            "issuer_name" => ["type" => "string"],
            "issuer_contact" => ["type" => "string"],
            "image_author_name" => ["type" => "string"],
            "image_caption" => ["type" => "string"],
            "language" => ["type" => "string"],
            "badge_version" => ["type" => "string"],
        ], ["name", "description"]);
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
     * Method validate.
     *
     * @param array $arguments Parameter arguments.
     * @return void Return value.
     */
    private function validate(array $arguments): void {
        global $CFG;
        if (empty($CFG->enablebadges) ||
            (!empty($arguments["courseid"]) && empty($CFG->badges_allowcoursebadges))) {
            throw new api_exception("Moodle badges are disabled for this scope", 400);
        }
        if (trim($arguments["name"]) === "" || trim($arguments["description"]) === "" ||
            strlen($arguments["name"]) > 255) {
            throw new api_exception("Badge name and description are required", 400);
        }
        if (empty($arguments["image_url"]) === empty($arguments["image_base64"])) {
            throw new api_exception("Provide exactly one image_url or image_base64", 400);
        }
    }
    /**
     * Method preview.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        $this->validate($arguments);
        return [
            "action" => "create_inactive_badge", "courseid" => (int)($arguments["courseid"] ?? 0),
            "name" => $arguments["name"], "description" => $arguments["description"],
            "image_source" => !empty($arguments["image_url"]) ? "https_url" : "base64",
            "image" => image::preview_image($arguments),
            "url_download_after_confirmation" => !empty($arguments["image_url"]),
            "will_not_enable_or_award" => true,
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
        global $CFG, $DB;
        require_once($CFG->libdir . "/badgeslib.php");
        $this->validate($arguments);
        // Download and validate before any database mutation.
        $image = image::load($arguments);
        $courseid = (int)($arguments["courseid"] ?? 0);
        $issuerurl = parse_url($CFG->wwwroot);
        $issuerurl = $issuerurl["scheme"] . "://" . $issuerurl["host"];
        $data = (object)[
            "name" => clean_param($arguments["name"], PARAM_TEXT),
            "description" => clean_param($arguments["description"], PARAM_NOTAGS),
            "version" => clean_param($arguments["badge_version"] ?? "1.0", PARAM_TEXT),
            "language" => clean_param($arguments["language"] ?? current_language(), PARAM_LANG),
            "imageauthorname" => clean_param($arguments["image_author_name"] ?? "", PARAM_TEXT),
            "imageauthoremail" => "", "imageauthorurl" => "",
            "imagecaption" => clean_param($arguments["image_caption"] ?? "", PARAM_TEXT),
            "issuername" => clean_param($arguments["issuer_name"] ??
                (!empty($CFG->badges_defaultissuername) ? $CFG->badges_defaultissuername
                    : get_site()->fullname), PARAM_NOTAGS),
            "issuerurl" => $issuerurl,
            "issuercontact" => $arguments["issuer_contact"] ?? ($CFG->badges_defaultissuercontact ?? ""),
            "expiry" => 0,
        ];
        if ($data->issuercontact !== "" && !validate_email($data->issuercontact)) {
            throw new api_exception("Invalid issuer_contact email", 400);
        }
        $transaction = $DB->start_delegated_transaction();
        $badge = \badge::create_badge($data, $courseid ?: null);
        image::save($badge, $image["bytes"]);
        $transaction->allow_commit();
        return [
            "badgeid" => (int)$badge->id, "courseid" => $courseid, "status" => "inactive",
            "image_sha256" => $image["sha256"], "transparent_edges" => $image["transparent_edges"],
            "url" => (new moodle_url("/badges/overview.php", ["id" => $badge->id]))->out(false),
            "next_step" => "Configure award criteria, review and enable badge in Moodle. Nothing was awarded.",
        ];
    }
}
