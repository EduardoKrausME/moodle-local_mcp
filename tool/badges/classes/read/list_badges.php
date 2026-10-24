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
 * List site-level or course-level Moodle badges.
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
 * List only badges in the scope for which the user has permission.
 */
final class list_badges extends base_tool {
    public function get_name(): string {
        return "badges_list";
    }
    public function get_title(): string {
        return "Badges: list";
    }
    public function get_description(): string {
        return "List existing Moodle site badges (courseid=0) or badges for a specified course before creating duplicates.";
    }
    public function get_required_capability(): string {
        return "moodle/badges:viewbadges";
    }
    public function get_input_schema(): array {
        return $this->object_schema(["courseid" => ["type" => "integer", "minimum" => 0]]);
    }
    public function resolve_context(array $arguments): context {
        return !empty($arguments["courseid"])
            ? context_course::instance((int)$arguments["courseid"], MUST_EXIST)
            : context_system::instance();
    }
    public function execute(array $arguments, authenticated_identity $identity): array {
        global $DB, $CFG;
        require_once($CFG->libdir . "/badgeslib.php");
        if (empty($CFG->enablebadges)) {
            throw new api_exception("Moodle badges are disabled", 400);
        }
        $courseid = (int)($arguments["courseid"] ?? 0);
        if ($courseid && empty($CFG->badges_allowcoursebadges)) {
            throw new api_exception("Course badges are disabled", 400);
        }
        $rows = $DB->get_records("badge", ["type" => $courseid ? BADGE_TYPE_COURSE : BADGE_TYPE_SITE,
            "courseid" => $courseid ?: null], "id DESC", "id,name,description,type,courseid,status,timemodified", 0, 250);
        $badges = [];
        foreach ($rows as $row) {
            $badges[] = [
                "badgeid" => (int)$row->id, "name" => $row->name,
                "description" => $row->description, "status" => (int)$row->status,
                "timemodified" => (int)$row->timemodified,
                "url" => (new moodle_url("/badges/overview.php", ["id" => $row->id]))->out(false),
            ];
        }
        return ["courseid" => $courseid, "badges" => $badges, "limit" => 250];
    }
}
