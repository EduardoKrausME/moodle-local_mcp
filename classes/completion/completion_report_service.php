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
 * Course and activity completion reporting service.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\completion;

use completion_info;
use context_course;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\security\untrusted_content;

/**
 * Completion criteria, individual completion and paginated enrolled-user progress.
 */
final class completion_report_service {
    /**
     * Read access is allowed for course viewers; other-user data needs gradebook rights.
     */
    private static function authorize(int $courseid, authenticated_identity $identity,
            int $userid = 0): context_course {
        $context = context_course::instance($courseid, MUST_EXIST);
        $crossuser = $userid !== 0 && $userid !== $identity->userid;
        $capability = $crossuser ? "moodle/grade:viewall" : "moodle/course:view";
        if (!has_capability($capability, $context, $identity->userid)) {
            throw new api_exception("permission_denied", 403);
        }
        if ($userid) {
            global $DB;
            if (!$DB->record_exists("user", ["id" => $userid, "deleted" => 0]) ||
                    !is_enrolled($context, $userid, "", true)) {
                throw new api_exception("completion_user_not_enrolled", 404);
            }
            if ($crossuser) {
                self::check_user_group($courseid, $userid, $identity, $context);
            }
        }
        return $context;
    }

    /**
     * For course-level separate groups, inspect only classmates in shared groups.
     */
    private static function check_user_group(int $courseid, int $userid,
            authenticated_identity $identity, context_course $context): void {
        global $CFG;
        if ((int)get_course($courseid)->groupmode !== SEPARATEGROUPS ||
                has_capability("moodle/site:accessallgroups", $context, $identity->userid)) {
            return;
        }
        require_once($CFG->libdir . "/grouplib.php");
        foreach (groups_get_all_groups($courseid, $identity->userid) as $group) {
            if (groups_is_member((int)$group->id, $userid)) {
                return;
            }
        }
        throw new api_exception("completion_group_access_denied", 403);
    }

    /**
     * Course completion configuration via native completion_info.
     */
    public static function criteria(array $args, authenticated_identity $identity): array {
        global $CFG;
        require_once($CFG->libdir . "/completionlib.php");
        $courseid = (int)$args["courseid"];
        self::authorize($courseid, $identity);
        $course = get_course($courseid);
        $completion = new completion_info($course);
        $rows = [];
        foreach ($completion->get_criteria() as $criterion) {
            $rows[] = ["criteriaid" => (int)$criterion->id,
                "type" => (int)$criterion->criteriatype,
                "moduleinstance" => (int)$criterion->moduleinstance,
                "courseinstance" => (int)$criterion->courseinstance,
                "gradepass" => (float)$criterion->gradepass,
                "enrolperiod" => (int)$criterion->enrolperiod,
                "timeend" => (int)$criterion->timeend,
                "role" => (int)$criterion->role];
        }
        return ["courseid" => $courseid,
            "enabled" => !empty($CFG->enablecompletion) && !empty($course->enablecompletion),
            "has_criteria" => $completion->has_criteria(), "criteria" => $rows,
            "overall_aggregation" => (int)$completion->get_aggregation_method()];
    }

    /**
     * Return one enrollee's course criteria, activity states and timestamps.
     */
    public static function user_status(array $args, authenticated_identity $identity): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/lib/completionlib.php");
        $courseid = (int)$args["courseid"];
        $userid = (int)$args["userid"];
        self::authorize($courseid, $identity, $userid);
        $course = get_course($courseid);
        $completion = new completion_info($course);
        $coursecompleted = $DB->get_record("course_completions",
            ["course" => $courseid, "userid" => $userid], "*");
        $criteria = $DB->get_records("course_completion_crit_compl",
            ["course" => $courseid, "userid" => $userid], "criteriaid ASC");
        $passed = [];
        foreach ($criteria as $item) {
            $passed[(int)$item->criteriaid] = (int)$item->timecompleted;
        }
        $rows = [];
        foreach ($completion->get_criteria() as $criterion) {
            $date = $passed[(int)$criterion->id] ?? 0;
            $rows[] = ["criteriaid" => (int)$criterion->id,
                "type" => (int)$criterion->criteriatype, "complete" => $date > 0,
                "timecompleted" => $date ?: null];
        }
        $activities = self::activity_states($course, $userid);
        $completed = count(array_filter($activities, static function($r) {
            return $r["complete"];
        }));
        return ["courseid" => $courseid, "userid" => $userid,
            "course_complete" => $completion->has_criteria() && $completion->is_course_complete($userid),
            "course_timecompleted" => $coursecompleted && $coursecompleted->timecompleted
                ? (int)$coursecompleted->timecompleted : null,
            "criteria" => $rows,
            "activities" => $activities, "activities_total" => count($activities),
            "activities_completed" => $completed,
            "activities_percent" => count($activities)
                ? round(100 * $completed / count($activities), 2) : 0];
    }

    /**
     * User-visible tracked activity data including real completionstate and timestamps.
     *
     * @return array Per-activity snapshots; no other student's data.
     */
    private static function activity_states(\stdClass $course, int $userid): array {
        $completion = new completion_info($course);
        $modinfo = get_fast_modinfo($course, $userid);
        $rows = [];
        foreach ($modinfo->cms as $cm) {
            if (!$cm->uservisible || !$completion->is_enabled($cm)) {
                continue;
            }
            $data = $completion->get_data($cm, false, $userid);
            $state = (int)$data->completionstate;
            $rows[] = ["cmid" => (int)$cm->id,
                "name" => untrusted_content::text($cm->name),
                "state" => $state, "complete" => in_array($state, [
                    COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS, COMPLETION_COMPLETE_FAIL,
                ], true),
                "timeupdated" => !empty($data->timemodified) ? (int)$data->timemodified : null];
        }
        return $rows;
    }

    /**
     * Paged class progress. Separate-group teachers must choose one of their groups.
     */
    public static function report(array $args, authenticated_identity $identity): array {
        global $CFG, $DB;
        require_once($CFG->libdir . "/completionlib.php");
        require_once($CFG->libdir . "/grouplib.php");
        $courseid = (int)$args["courseid"];
        $context = context_course::instance($courseid, MUST_EXIST);
        if (!has_capability("moodle/grade:viewall", $context, $identity->userid)) {
            throw new api_exception("permission_denied", 403);
        }
        $course = get_course($courseid);
        $groupid = (int)($args["groupid"] ?? 0);
        $allgroups = has_capability("moodle/site:accessallgroups", $context, $identity->userid);
        if ((int)$course->groupmode === SEPARATEGROUPS && !$allgroups && !$groupid) {
            throw new api_exception("group_selection_required", 403);
        }
        if ($groupid) {
            if (!$DB->record_exists("groups", ["id" => $groupid, "courseid" => $courseid])) {
                throw new api_exception("group_not_in_course", 404);
            }
            if (!$allgroups && !groups_is_member($groupid, $identity->userid)) {
                throw new api_exception("group_access_denied", 403);
            }
        }
        $limit = min(25, max(1, (int)($args["limit"] ?? 15)));
        $offset = max(0, (int)($args["offset"] ?? 0));
        $users = get_enrolled_users($context, "", $groupid, "u.id",
            "u.id ASC", $offset, $limit, true);
        $rows = [];
        foreach ($users as $user) {
            $states = self::activity_states($course, (int)$user->id);
            $total = count($states);
            $complete = count(array_filter($states, static function($r) {
                return $r["complete"];
            }));
            $rows[] = ["userid" => (int)$user->id, "tracked" => $total,
                "completed" => $complete, "percent" => $total
                    ? round(100 * $complete / $total, 2) : 0,
                "course_timecompleted" => null];
        }
        $coursecompletions = [];
        if ($rows) {
            [$insql, $params] = $DB->get_in_or_equal(array_column($rows, "userid"),
                SQL_PARAMS_NAMED, "uid");
            $params["course"] = $courseid;
            $records = $DB->get_records_select("course_completions",
                "course = :course AND userid " . $insql, $params);
            foreach ($records as $record) {
                $coursecompletions[(int)$record->userid] = $record->timecompleted
                    ? (int)$record->timecompleted : null;
            }
        }
        foreach ($rows as &$row) {
            $row["course_timecompleted"] = $coursecompletions[$row["userid"]] ?? null;
        }
        unset($row);
        return ["courseid" => $courseid, "groupid" => $groupid,
            "participants" => $rows, "limit" => $limit, "offset" => $offset,
            "has_more" => count($rows) === $limit];
    }
}
