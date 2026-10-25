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
 * Read one native Moodle badge with its image and state.
 *
 * @package   mcptool_badges
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_badges\read;

use context;
use context_course;
use context_system;
use local_mcp\exception\api_exception;
use local_mcp\read\tool\base_tool;
use local_mcp\security\authenticated_identity;
use moodle_url;

/**
 * Return a single badge's descriptive metadata and current image location.
 */
final class get_badge extends base_tool {
    public function get_name(): string {
        return "badges_get";
    }
    public function get_title(): string {
        return "Badges: details";
    }
    public function get_description(): string {
        return "Read badge metadata, image URL, status, award count and last modification timestamp.";
    }
    public function get_required_capability(): string {
        return "moodle/badges:viewbadges";
    }
    public function get_input_schema(): array {
        return $this->object_schema(["badgeid" => ["type" => "integer", "minimum" => 1]], ["badgeid"]);
    }
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
     * @param int $badgeid Badge ID.
     * @return array Summary of stored native badge criteria.
     */
    private static function criteria_summary(int $badgeid): array {
        global $DB;
        $rows = $DB->get_records("badge_criteria", ["badgeid" => $badgeid]);
        $result = [];
        foreach ($rows as $row) {
            $params = $DB->get_records("badge_criteria_param", ["critid" => $row->id]);
            $result[] = ["type" => (int)$row->criteriatype, "method" => (int)$row->method,
                "required_cmids" => array_values(array_map("intval", array_column(array_filter(
                    array_values($params), static fn($p): bool => preg_match("/^module_[0-9]+$/D", $p->name) === 1),
                    "value")))];
        }
        return $result;
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        global $DB, $CFG;
        require_once($CFG->libdir . "/badgeslib.php");
        $badge = new \badge((int)$arguments["badgeid"]);
        $context = $badge->get_context();
        $file = get_file_storage()->get_file($context->id, "badges", "badgeimage", $badge->id, "/", "f3.png");
        return [
            "badgeid" => (int)$badge->id, "courseid" => (int)$badge->courseid,
            "name" => $badge->name, "description" => $badge->description,
            "status" => (int)$badge->status, "timemodified" => (int)$badge->timemodified,
            "issuername" => $badge->issuername, "issuerurl" => $badge->issuerurl,
            "awards" => (int)$DB->count_records("badge_issued", ["badgeid" => $badge->id]),
            "criteria" => self::criteria_summary((int)$badge->id),
            "image_exists" => (bool)$file,
            "image_url" => $file ? \moodle_url::make_pluginfile_url(
                $context->id, "badges", "badgeimage", $badge->id, "/", "f3.png")->out(false) : null,
            "url" => (new moodle_url("/badges/overview.php", ["id" => $badge->id]))->out(false),
        ];
    }
}
