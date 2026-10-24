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
 * Enable a configured native Moodle course badge after confirmation.
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

/**
 * Activate an unissued course badge only after checking its activity criteria.
 */
final class enable_badge extends base_tool {
    public function get_name(): string {
        return "badges_enable";
    }

    public function get_title(): string {
        return "Badges: enable automatic awarding";
    }

    public function get_description(): string {
        return "Enable an inactive course badge only after verifying it has ALL-of activity criteria and every required module still tracks completion. Enabling may cause awards through Moodle's regular events/cron. Requires explicit confirmation.";
    }

    public function get_required_capability(): string {
        return "moodle/badges:configurecriteria";
    }

    public function get_input_schema(): array {
        return $this->object_schema([
            "badgeid" => ["type" => "integer", "minimum" => 1],
            "expected_timemodified" => ["type" => "integer", "minimum" => 0],
        ], ["badgeid", "expected_timemodified"]);
    }

    public function resolve_context(array $arguments): context {
        global $DB;
        $row = $DB->get_record("badge", ["id" => (int)$arguments["badgeid"]], "courseid", MUST_EXIST);
        return $row->courseid ? context_course::instance((int)$row->courseid, MUST_EXIST)
            : context_system::instance();
    }

    private function checked(array $arguments): array {
        global $DB, $CFG;
        require_once($CFG->libdir . "/badgeslib.php");
        $badge = new \badge((int)$arguments["badgeid"]);
        if ((int)$badge->type !== BADGE_TYPE_COURSE ||
                empty($CFG->enablebadges) || empty($CFG->badges_allowcoursebadges) ||
                empty($CFG->enablecompletion)) {
            throw new api_exception("Course badge or completion unavailable", 409);
        }
        if ((int)$badge->timemodified !== (int)$arguments["expected_timemodified"]) {
            throw new api_exception("revision_conflict", 409);
        }
        if ((int)$badge->status !== BADGE_STATUS_INACTIVE ||
                $DB->record_exists("badge_issued", ["badgeid" => $badge->id])) {
            throw new api_exception("Badge is already active, locked, or issued", 409);
        }
        if (empty(get_course((int)$badge->courseid)->enablecompletion)) {
            throw new api_exception("Course completion tracking disabled", 409);
        }
        $rows = $DB->get_records("badge_criteria", ["badgeid" => $badge->id]);
        $criteria = [];
        foreach ($rows as $row) {
            $criteria[(int)$row->criteriatype] = $row;
        }
        if (count($criteria) !== 2 ||
                !isset($criteria[BADGE_CRITERIA_TYPE_OVERALL],
                    $criteria[BADGE_CRITERIA_TYPE_ACTIVITY]) ||
                (int)$criteria[BADGE_CRITERIA_TYPE_OVERALL]->method !== BADGE_CRITERIA_AGGREGATION_ALL ||
                (int)$criteria[BADGE_CRITERIA_TYPE_ACTIVITY]->method !== BADGE_CRITERIA_AGGREGATION_ALL) {
            throw new api_exception("Badge must have only ALL-of activity criteria", 409);
        }
        $params = $DB->get_records("badge_criteria_param",
            ["critid" => $criteria[BADGE_CRITERIA_TYPE_ACTIVITY]->id]);
        if (!$params) {
            throw new api_exception("No activity criteria configured", 409);
        }
        $ids = [];
        foreach ($params as $param) {
            if (!preg_match("/^module_([1-9][0-9]*)$/D", $param->name, $match) ||
                    (int)$param->value !== (int)$match[1]) {
                throw new api_exception("Unsupported badge activity criterion parameter", 409);
            }
            $cmid = (int)$param->value;
            $cm = $DB->get_record("course_modules", ["id" => $cmid, "course" => $badge->courseid],
                "id,completion,deletioninprogress");
            if (!$cm || (int)$cm->completion !== COMPLETION_TRACKING_AUTOMATIC ||
                    !empty($cm->deletioninprogress)) {
                throw new api_exception("Badge includes an unavailable or incomplete-tracking activity", 409);
            }
            $ids[] = $cmid;
        }
        if (count(array_unique($ids)) !== count($ids)) {
            throw new api_exception("Duplicate activity requirements", 409);
        }
        return [$badge, $ids];
    }

    public function preview(array $arguments, authenticated_identity $identity): array {
        [$badge, $ids] = $this->checked($arguments);
        return ["action" => "enable_badge", "badgeid" => (int)$badge->id,
            "courseid" => (int)$badge->courseid, "name" => $badge->name,
            "required_cmids" => $ids, "aggregation" => "ALL",
            "may_issue_to_previously_completed_students" => true,
            "requires_explicit_confirmation" => true];
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        [$badge, $ids] = $this->checked($arguments);
        $badge->set_status(BADGE_STATUS_ACTIVE);
        return ["badgeid" => (int)$badge->id, "enabled" => true,
            "status" => (int)$badge->status, "required_cmids" => $ids];
    }
}
