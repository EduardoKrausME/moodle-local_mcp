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
 * Course group and membership management for MCP.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\course;

use context_course;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\security\untrusted_content;

/**
 * Access-controlled core Moodle group operations.
 */
final class group_service {
    /**
     * @return \context_course Group-management context.
     */
    public static function course(int $courseid, authenticated_identity $identity): context_course {
        global $DB, $CFG;
        $DB->get_record("course", ["id" => $courseid], "id", MUST_EXIST);
        $context = context_course::instance($courseid);
        if (!has_capability("moodle/course:managegroups", $context, $identity->userid)) {
            throw new api_exception("permission_denied", 403);
        }
        require_once($CFG->dirroot . "/group/lib.php");
        require_once($CFG->libdir . "/grouplib.php");
        return $context;
    }

    /**
     * @return \stdClass A group in the specified course.
     */
    public static function group(int $courseid, int $groupid, authenticated_identity $identity): \stdClass {
        global $DB;
        self::course($courseid, $identity);
        return $DB->get_record("groups", ["id" => $groupid, "courseid" => $courseid], "*", MUST_EXIST);
    }

    /**
     * @return array Limited list of groups, including a total count.
     */
    public static function list_groups(array $a, authenticated_identity $identity): array {
        global $DB;
        $courseid = (int)$a["courseid"];
        self::course($courseid, $identity);
        $limit = min(50, max(1, (int)($a["limit"] ?? 25)));
        $offset = max(0, (int)($a["offset"] ?? 0));
        $records = $DB->get_records("groups", ["courseid" => $courseid], "id ASC",
            "id,name,idnumber,timemodified", $offset, $limit);
        $rows = [];
        foreach ($records as $g) {
            $rows[] = ["id" => (int)$g->id, "name" => untrusted_content::text($g->name),
                "idnumber" => $g->idnumber, "timemodified" => (int)$g->timemodified,
                "membercount" => $DB->count_records("groups_members", ["groupid" => $g->id])];
        }
        return ["courseid" => $courseid, "total" => $DB->count_records("groups", ["courseid" => $courseid]),
            "groups" => $rows, "offset" => $offset, "limit" => $limit];
    }

    /**
     * Hash all current memberships (not merely the current page).
     */
    private static function membership_hash(int $groupid): string {
        global $DB;
        $members = $DB->get_records("groups_members", ["groupid" => $groupid], "userid ASC", "id,userid,component,itemid");
        $parts = [];
        foreach ($members as $member) {
            $parts[] = [(int)$member->userid, (string)$member->component, (int)$member->itemid];
        }
        return hash("sha256", json_encode($parts));
    }

    /**
     * @return array Paginated participant IDs and display names, without emails.
     */
    public static function members(array $a, authenticated_identity $identity): array {
        global $DB;
        $group = self::group((int)$a["courseid"], (int)$a["groupid"], $identity);
        $limit = min(50, max(1, (int)($a["limit"] ?? 25)));
        $offset = max(0, (int)($a["offset"] ?? 0));
        $sql = "SELECT u.*, gm.component AS membershipcomponent
                  FROM {groups_members} gm
                  JOIN {user} u ON u.id = gm.userid
                 WHERE gm.groupid = :groupid AND u.deleted = 0
              ORDER BY u.id ASC";
        $records = $DB->get_records_sql($sql, ["groupid" => $group->id], $offset, $limit);
        $members = [];
        foreach ($records as $user) {
            $members[] = ["userid" => (int)$user->id,
                "fullname" => untrusted_content::text(fullname($user)),
                "externally_managed" => !empty($user->membershipcomponent)];
        }
        return ["courseid" => (int)$group->courseid, "groupid" => (int)$group->id,
            "members" => $members, "total" => $DB->count_records("groups_members", ["groupid" => $group->id]),
            "members_sha256" => self::membership_hash($group->id),
            "offset" => $offset, "limit" => $limit];
    }

    /**
     * @return array Validate a proposed group without mutating database.
     */
    public static function create_preview(array $a, authenticated_identity $identity): array {
        $courseid = (int)$a["courseid"];
        self::course($courseid, $identity);
        $name = trim(clean_param((string)$a["name"], PARAM_TEXT));
        if ($name === "" || mb_strlen($name) > 255 || groups_get_group_by_name($courseid, $name)) {
            throw new api_exception("group_name_invalid_or_taken", 409);
        }
        return ["courseid" => $courseid, "name" => $name, "will_create" => true];
    }

    /**
     * @return array Create a Moodle group with core events/cache handling.
     */
    public static function create(array $a, authenticated_identity $identity): array {
        $preview = self::create_preview($a, $identity);
        $id = groups_create_group((object)["courseid" => $preview["courseid"],
            "name" => $preview["name"], "description" => "", "descriptionformat" => FORMAT_HTML]);
        return ["created" => true, "courseid" => $preview["courseid"], "groupid" => (int)$id,
            "name" => $preview["name"]];
    }

    /**
     * @return array Validate every membership and ownership before the operation.
     */
    public static function change_preview(array $a, authenticated_identity $identity, bool $remove): array {
        global $DB;
        $group = self::group((int)$a["courseid"], (int)$a["groupid"], $identity);
        $users = array_map("intval", $a["userids"]);
        if (!$users || count($users) > 50 || count(array_unique($users)) !== count($users)) {
            throw new api_exception("invalid_group_user_batch", 400);
        }
        $expected = (string)$a["expected_members_sha256"];
        if (!hash_equals(self::membership_hash($group->id), $expected)) {
            throw new api_exception("group_membership_changed", 409);
        }
        $context = context_course::instance((int)$group->courseid);
        $planned = [];
        foreach ($users as $userid) {
            // Addition requires active enrolment. Removal must still work after
            // enrolment ends or the user record is soft-deleted.
            if (!$remove && (!$DB->record_exists("user", ["id" => $userid, "deleted" => 0]) ||
                    !is_enrolled($context, $userid, "", true))) {
                throw new api_exception("member_not_actively_enrolled", 409);
            }
            $exists = groups_is_member($group->id, $userid);
            if ($remove && $exists && !groups_remove_member_allowed($group->id, $userid)) {
                throw new api_exception("externally_managed_membership", 409);
            }
            if ($remove ? $exists : !$exists) {
                $planned[] = $userid;
            }
        }
        return ["courseid" => (int)$group->courseid, "groupid" => (int)$group->id,
            "operation" => $remove ? "remove" : "add", "userids" => $planned,
            "already_satisfied" => count($users) - count($planned),
            "members_sha256" => $expected];
    }

    /**
     * @return array Commit authorized changes and preserve Moodle event processing.
     */
    public static function change(array $a, authenticated_identity $identity, bool $remove): array {
        global $DB;
        $preview = self::change_preview($a, $identity, $remove);
        $transaction = $DB->start_delegated_transaction();
        foreach ($preview["userids"] as $userid) {
            $result = $remove
                ? groups_remove_member($preview["groupid"], $userid)
                : groups_add_member($preview["groupid"], $userid);
            if (!$result) {
                throw new api_exception("group_member_update_failed", 409);
            }
        }
        $transaction->allow_commit();
        return ["changed" => count($preview["userids"]), "courseid" => $preview["courseid"],
            "groupid" => $preview["groupid"], "operation" => $preview["operation"],
            "members_sha256" => self::membership_hash($preview["groupid"])];
    }
}
