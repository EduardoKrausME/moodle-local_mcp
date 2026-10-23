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
 * Gradebook configuration, grade history and safe visibility controls.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\grades;

use context_course;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\security\untrusted_content;

/**
 * Safely read gradebook configuration and recorded grade changes.
 */
final class gradebook_service {
    /**
     * Obtain the course context while checking a capability for the token owner.
     */
    private static function authorize(int $courseid, authenticated_identity $identity, string $capability): context_course {
        $context = context_course::instance($courseid, MUST_EXIST);
        if (!has_capability($capability, $context, $identity->userid)) {
            throw new api_exception("permission_denied", 403);
        }
        return $context;
    }

    /**
     * Grade item fingerprint to prevent overwriting concurrent edits.
     */
    private static function item_hash(\stdClass $item): string {
        return hash("sha256", json_encode([(int)$item->id, (int)$item->courseid,
            (int)$item->hidden, (int)$item->locked, (int)$item->timemodified,
            (int)$item->categoryid, (float)$item->grademax,
            (float)$item->gradepass, (string)$item->calculation]));
    }

    /**
     * Get gradebook items and categories without exposing individual grades.
     */
    public static function structure(array $args, authenticated_identity $identity): array {
        global $DB;
        $courseid = (int)$args["courseid"];
        self::authorize($courseid, $identity, "moodle/grade:manage");
        $limit = min(100, max(1, (int)($args["limit"] ?? 50)));
        $offset = max(0, (int)($args["offset"] ?? 0));
        $items = $DB->get_records("grade_items", ["courseid" => $courseid], "sortorder ASC, id ASC",
            "*", $offset, $limit);
        $rows = [];
        foreach ($items as $item) {
            $rows[] = ["itemid" => (int)$item->id, "name" => untrusted_content::text($item->itemname),
                "itemtype" => $item->itemtype, "itemmodule" => $item->itemmodule,
                "iteminstance" => (int)$item->iteminstance, "categoryid" => (int)$item->categoryid,
                "gradetype" => (int)$item->gradetype, "minimum" => (float)$item->grademin,
                "maximum" => (float)$item->grademax, "passing_grade" => (float)$item->gradepass,
                "hidden" => (bool)$item->hidden, "locked" => (bool)$item->locked,
                "needsupdate" => (bool)$item->needsupdate, "sortorder" => (int)$item->sortorder,
                "item_sha256" => self::item_hash($item)];
        }
        $cats = $DB->get_records("grade_categories", ["courseid" => $courseid], "depth ASC, id ASC",
            "id,parent,fullname,aggregation,depth");
        $categories = [];
        foreach ($cats as $cat) {
            $categories[] = ["categoryid" => (int)$cat->id, "parent" => (int)$cat->parent,
                "name" => untrusted_content::text($cat->fullname),
                "aggregation" => (int)$cat->aggregation, "depth" => (int)$cat->depth];
        }
        return ["courseid" => $courseid, "items" => $rows, "categories" => $categories,
            "total_items" => $DB->count_records("grade_items", ["courseid" => $courseid]),
            "offset" => $offset, "limit" => $limit];
    }

    /**
     * Historical grade versions visible only to full gradebook viewers.
     */
    public static function history(array $args, authenticated_identity $identity): array {
        global $DB;
        $courseid = (int)$args["courseid"];
        $context = self::authorize($courseid, $identity, "moodle/grade:viewall");
        $userid = (int)$args["userid"];
        if (!$DB->record_exists("user", ["id" => $userid, "deleted" => 0]) ||
                !is_enrolled($context, $userid, "", true)) {
            throw new api_exception("grade_user_not_enrolled", 404);
        }
        self::check_group_visibility($courseid, $userid, $identity, $context);
        $itemid = (int)($args["itemid"] ?? 0);
        if ($itemid && !$DB->record_exists("grade_items", ["id" => $itemid, "courseid" => $courseid])) {
            throw new api_exception("grade_item_not_in_course", 404);
        }
        $limit = min(50, max(1, (int)($args["limit"] ?? 25)));
        $offset = max(0, (int)($args["offset"] ?? 0));
        $params = ["courseid" => $courseid, "userid" => $userid];
        $where = "";
        if ($itemid) {
            $where = " AND gh.itemid = :itemid";
            $params["itemid"] = $itemid;
        }
        // Full gradebook access does not imply permission to view hidden grades.
        if (!has_capability("moodle/grade:viewhidden", $context, $identity->userid)) {
            $where .= " AND gi.hidden = 0 AND gh.hidden = 0";
        }
        $sql = "SELECT gh.id, gh.action, gh.itemid, gh.timemodified, gh.finalgrade,
                       gh.rawgrade, gh.overridden, gh.hidden, gh.locked
                  FROM {grade_grades_history} gh
                  JOIN {grade_items} gi ON gi.id = gh.itemid
                 WHERE gi.courseid = :courseid AND gh.userid = :userid" . $where . "
              ORDER BY gh.timemodified DESC, gh.id DESC";
        $history = $DB->get_records_sql($sql, $params, $offset, $limit);
        $rows = [];
        foreach ($history as $entry) {
            $rows[] = ["historyid" => (int)$entry->id, "itemid" => (int)$entry->itemid,
                "action" => (int)$entry->action, "time" => (int)$entry->timemodified,
                "finalgrade" => $entry->finalgrade === null ? null : (float)$entry->finalgrade,
                "rawgrade" => $entry->rawgrade === null ? null : (float)$entry->rawgrade,
                "overridden" => (bool)$entry->overridden, "hidden" => (bool)$entry->hidden,
                "locked" => (bool)$entry->locked];
        }
        return ["courseid" => $courseid, "userid" => $userid, "history" => $rows,
            "limit" => $limit, "offset" => $offset,
            "truncated" => count($rows) === $limit];
    }

    /**
     * A grade report cannot bypass separate groups.
     */
    private static function check_group_visibility(int $courseid, int $userid,
            authenticated_identity $identity, context_course $context): void {
        global $CFG;
        $course = get_course($courseid);
        if ((int)$course->groupmode !== SEPARATEGROUPS ||
                has_capability("moodle/site:accessallgroups", $context, $identity->userid)) {
            return;
        }
        require_once($CFG->libdir . "/grouplib.php");
        $groups = groups_get_all_groups($courseid, $identity->userid);
        foreach ($groups as $group) {
            if (groups_is_member((int)$group->id, $userid)) {
                return;
            }
        }
        throw new api_exception("grade_group_access_denied", 403);
    }

    /**
     * Validate a non-cascading change to activity grade visibility.
     */
    public static function visibility_preview(array $args, authenticated_identity $identity): array {
        global $CFG, $DB;
        $courseid = (int)$args["courseid"];
        $context = self::authorize($courseid, $identity, "moodle/grade:hide");
        if (!has_capability("moodle/grade:manage", $context, $identity->userid)) {
            throw new api_exception("permission_denied", 403);
        }
        $item = $DB->get_record("grade_items", ["id" => (int)$args["itemid"],
            "courseid" => $courseid], "*", MUST_EXIST);
        if ($item->itemtype !== "mod") {
            throw new api_exception("only_module_grade_visibility_supported", 400);
        }
        if (!hash_equals(self::item_hash($item), (string)$args["expected_item_sha256"])) {
            throw new api_exception("grade_item_changed", 409);
        }
        require_once($CFG->dirroot . "/course/lib.php");
        if (course_module_instance_pending_deletion($courseid, $item->itemmodule, $item->iteminstance)) {
            throw new api_exception("grade_item_pending_deletion", 409);
        }
        return ["courseid" => $courseid, "itemid" => (int)$item->id,
            "current_hidden" => (bool)$item->hidden, "requested_hidden" => (bool)$args["hidden"],
            "cascade" => false, "grade_values_changed" => false];
    }

    /**
     * Native grade_item visibility update without changing individual grades.
     */
    public static function set_visibility(array $args, authenticated_identity $identity): array {
        global $CFG;
        $preview = self::visibility_preview($args, $identity);
        require_once($CFG->libdir . "/gradelib.php");
        require_once($CFG->libdir . "/grade/grade_item.php");
        $item = \grade_item::fetch(["id" => $preview["itemid"],
            "courseid" => $preview["courseid"]]);
        if (!$item) {
            throw new api_exception("grade_item_not_found", 404);
        }
        if ($preview["current_hidden"] !== $preview["requested_hidden"]) {
            $item->set_hidden($preview["requested_hidden"] ? 1 : 0, false);
        }
        return ["changed" => $preview["current_hidden"] !== $preview["requested_hidden"],
            "courseid" => $preview["courseid"], "itemid" => $preview["itemid"],
            "hidden" => $preview["requested_hidden"]];
    }
}
