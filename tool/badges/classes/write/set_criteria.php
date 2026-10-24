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
 * Set native activity completion criteria for an inactive course badge.
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
 * Configure all-of activity criteria using Moodle's native award criteria API.
 */
final class set_criteria extends base_tool {
    public function get_name(): string {
        return "badges_set_criteria";
    }

    public function get_title(): string {
        return "Badges: require completion of every selected activity";
    }

    public function get_description(): string {
        return "Set ALL-of native activity-completion criteria on a course badge that is inactive and has no awards or criteria. Activity completion must already be automatic. Does not enable or issue the badge. Requires preview and confirmation.";
    }

    public function get_required_capability(): string {
        return "moodle/badges:configurecriteria";
    }

    public function get_input_schema(): array {
        return $this->object_schema([
            "badgeid" => ["type" => "integer", "minimum" => 1],
            "expected_timemodified" => ["type" => "integer", "minimum" => 0],
            "cmids" => ["type" => "array", "minItems" => 1, "maxItems" => 100,
                "uniqueItems" => true, "items" => ["type" => "integer", "minimum" => 1]],
        ], ["badgeid", "expected_timemodified", "cmids"]);
    }

    public function resolve_context(array $arguments): context {
        global $DB;
        $badge = $DB->get_record("badge", ["id" => (int)$arguments["badgeid"]], "courseid", MUST_EXIST);
        return $badge->courseid
            ? context_course::instance((int)$badge->courseid, MUST_EXIST) : context_system::instance();
    }

    private function checked(array $arguments): array {
        global $CFG, $DB;
        require_once($CFG->libdir . "/badgeslib.php");
        $badge = new \badge((int)$arguments["badgeid"]);
        if ((int)$badge->type !== BADGE_TYPE_COURSE || empty($badge->courseid)) {
            throw new api_exception("Only course badges accept activity completion criteria", 400);
        }
        if (empty($CFG->enablebadges) || empty($CFG->badges_allowcoursebadges) ||
                empty($CFG->enablecompletion)) {
            throw new api_exception("Badges or completion are disabled at site level", 409);
        }
        if ((int)$badge->timemodified !== (int)$arguments["expected_timemodified"]) {
            throw new api_exception("revision_conflict", 409);
        }
        if ((int)$badge->status !== BADGE_STATUS_INACTIVE ||
                $DB->record_exists("badge_issued", ["badgeid" => $badge->id]) ||
                $DB->record_exists("badge_criteria", ["badgeid" => $badge->id])) {
            throw new api_exception("Badge must be inactive, unissued and without existing criteria", 409);
        }
        $course = get_course((int)$badge->courseid);
        if (empty($course->enablecompletion)) {
            throw new api_exception("Course completion tracking is disabled", 409);
        }
        $cmids = $arguments["cmids"] ?? [];
        if (!is_array($cmids) || !$cmids || count($cmids) > 100 ||
                count(array_unique($cmids)) !== count($cmids)) {
            throw new api_exception("Supply 1 to 100 distinct course module IDs", 400);
        }
        $activities = [];
        foreach ($cmids as $cmid) {
            if (!is_int($cmid) || $cmid < 1) {
                throw new api_exception("Invalid activity ID", 400);
            }
            $cm = $DB->get_record("course_modules", ["id" => $cmid, "course" => $course->id],
                "id,course,completion,completionview,deletioninprogress", MUST_EXIST);
            if ((int)$cm->completion !== COMPLETION_TRACKING_AUTOMATIC ||
                    !empty($cm->deletioninprogress)) {
                throw new api_exception("Every selected activity must have automatic completion", 409);
            }
            $activities[] = ["cmid" => (int)$cm->id,
                "requires_view" => (bool)$cm->completionview];
        }
        return [$badge, $activities];
    }

    public function preview(array $arguments, authenticated_identity $identity): array {
        [$badge, $activities] = $this->checked($arguments);
        return ["action" => "set_badge_activity_criteria",
            "badgeid" => (int)$badge->id, "courseid" => (int)$badge->courseid,
            "expected_timemodified" => (int)$badge->timemodified,
            "aggregation" => "ALL", "activities" => $activities,
            "will_enable_or_award" => false, "requires_explicit_confirmation" => true];
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        global $DB, $PAGE, $CFG;
        [$badge, $activities] = $this->checked($arguments);
        require_once($CFG->dirroot . "/badges/criteria/award_criteria.php");
        $PAGE->set_context($badge->get_context());
        $transaction = $DB->start_delegated_transaction();
        $overall = \award_criteria::build(["criteriatype" => BADGE_CRITERIA_TYPE_OVERALL,
            "badgeid" => (int)$badge->id]);
        $overall->save(["agg" => BADGE_CRITERIA_AGGREGATION_ALL]);
        $activity = \award_criteria::build(["criteriatype" => BADGE_CRITERIA_TYPE_ACTIVITY,
            "badgeid" => (int)$badge->id]);
        $params = ["agg" => BADGE_CRITERIA_AGGREGATION_ALL];
        foreach ($activities as $item) {
            $params["module_" . $item["cmid"]] = $item["cmid"];
        }
        $activity->save($params);
        $badge->save();
        $transaction->allow_commit();
        return ["badgeid" => (int)$badge->id, "courseid" => (int)$badge->courseid,
            "cmids" => array_column($activities, "cmid"), "aggregation" => "ALL",
            "updated" => true, "enabled" => false,
            "next_step" => "Inspect criteria and explicitly confirm badges_enable."];
    }
}
