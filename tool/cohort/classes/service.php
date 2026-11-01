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
 * Native cohort operations and access checks.
 *
 * @package   mcptool_cohort
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_cohort;

use context;
use context_course;
use context_system;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use moodle_url;

/**
 * Keeps cohort operations, privacy checks, and concurrency guards in one place.
 */
final class service {
    /**
     * Method load_api.
     *
     * @return void Return value.
     */
    private static function load_api(): void {
        global $CFG;
        require_once($CFG->dirroot . "/cohort/lib.php");
    }

    /**
     * Cohorts can only belong to the system or a course category.
     */
    public static function scope(int $contextid): context {
        $context = $contextid ? context::instance_by_id($contextid, MUST_EXIST) : context_system::instance();
        if (!in_array($context->contextlevel, [CONTEXT_SYSTEM, CONTEXT_COURSECAT], true)) {
            throw new api_exception("Cohort context must be system or course category", 400);
        }
        return $context;
    }

    /**
     * Resolve a cohort's own context rather than the context of its consumers.
     */
    public static function cohort_context(int $cohortid): context {
        global $DB;
        $contextid = $DB->get_field("cohort", "contextid", ["id" => $cohortid]);
        if (!$contextid) {
            throw new api_exception("cohort_not_found", 404);
        }
        return self::scope((int)$contextid);
    }

    /**
     * Method cohort.
     *
     * @param int $cohortid Parameter cohortid.
     * @param string $capability Parameter capability.
     * @return \stdClass Return value.
     */
    private static function cohort(int $cohortid, string $capability = "moodle/cohort:view"): \stdClass {
        global $DB;
        $cohort = $DB->get_record("cohort", ["id" => $cohortid]);
        if (!$cohort) {
            throw new api_exception("cohort_not_found", 404);
        }
        require_capability($capability, self::scope((int)$cohort->contextid));
        return $cohort;
    }

    /**
     * Method editable.
     *
     * @param \stdClass $cohort Parameter cohort.
     * @return void Return value.
     */
    private static function editable(\stdClass $cohort): void {
        if ($cohort->component !== "") {
            throw new api_exception("Component-owned cohorts cannot be modified here", 409);
        }
    }

    /**
     * Method clean_name.
     *
     * @param string $name Parameter name.
     * @return string Return value.
     */
    private static function clean_name(string $name): string {
        $name = trim(clean_param($name, PARAM_TEXT));
        if ($name === "") {
            throw new api_exception("Cohort name cannot be empty", 400);
        }
        return $name;
    }

    /**
     * Method unique_idnumber.
     *
     * @param string $idnumber Parameter idnumber.
     * @param int $exclude Parameter exclude.
     * @return void Return value.
     */
    private static function unique_idnumber(string $idnumber, int $exclude = 0): void {
        global $DB;
        if ($idnumber !== "") {
            $existing = $DB->get_field("cohort", "id", ["idnumber" => $idnumber]);
            if ($existing && (int)$existing !== $exclude) {
                throw new api_exception("cohort_idnumber_already_exists", 409);
            }
        }
    }

    /**
     * Method revision.
     *
     * @param \stdClass $cohort Parameter cohort.
     * @return string Return value.
     */
    private static function revision(\stdClass $cohort): string {
        return hash("sha256", implode("|", [
            $cohort->id, $cohort->contextid, $cohort->component, $cohort->name,
            $cohort->idnumber ?? "", $cohort->description, $cohort->descriptionformat,
            $cohort->visible, $cohort->timemodified,
        ]));
    }

    /**
     * Method expected_revision.
     *
     * @param \stdClass $cohort Parameter cohort.
     * @param array $arguments Parameter arguments.
     * @return void Return value.
     */
    private static function expected_revision(\stdClass $cohort, array $arguments): void {
        if (!hash_equals(self::revision($cohort), $arguments["expected_revision"])) {
            throw new api_exception("revision_conflict", 409);
        }
    }

    /**
     * Stream every ID for a cohort-wide membership fingerprint, not just the current page.
     */
    private static function members_revision(int $cohortid): string {
        global $DB;
        $hash = hash_init("sha256");
        hash_update($hash, "cohort:" . $cohortid . ":");
        $records = $DB->get_recordset_sql(
            "SELECT id, userid FROM {cohort_members} WHERE cohortid = :cohortid ORDER BY userid ASC",
            ["cohortid" => $cohortid]);
        try {
            foreach ($records as $member) {
                hash_update($hash, (string)$member->userid . ",");
            }
        } finally {
            $records->close();
        }
        return hash_final($hash);
    }

    /**
     * Method expected_members.
     *
     * @param \stdClass $cohort Parameter cohort.
     * @param array $arguments Parameter arguments.
     * @return void Return value.
     */
    private static function expected_members(\stdClass $cohort, array $arguments): void {
        if (!hash_equals(self::members_revision((int)$cohort->id), $arguments["expected_members_sha256"])) {
            throw new api_exception("cohort_membership_changed", 409);
        }
    }

    /**
     * Method linked_count.
     *
     * @param int $cohortid Parameter cohortid.
     * @return int Return value.
     */
    private static function linked_count(int $cohortid): int {
        global $DB;
        return $DB->count_records("enrol", ["enrol" => "cohort", "customint1" => $cohortid]);
    }

    /**
     * Method overview.
     *
     * @param \stdClass $cohort Parameter cohort.
     * @param bool $withdigest Parameter withdigest.
     * @return array Return value.
     */
    private static function overview(\stdClass $cohort, bool $withdigest = true): array {
        global $DB;
        $result = [
            "cohortid" => (int)$cohort->id,
            "name" => $cohort->name,
            "idnumber" => $cohort->idnumber ?? "",
            "contextid" => (int)$cohort->contextid,
            "component" => $cohort->component,
            "visible" => (bool)$cohort->visible,
            "description" => $cohort->description,
            "descriptionformat" => (int)$cohort->descriptionformat,
            "timemodified" => (int)$cohort->timemodified,
            "membercount" => $DB->count_records("cohort_members", ["cohortid" => $cohort->id]),
            "linkedcourses" => self::linked_count((int)$cohort->id),
            "revision" => self::revision($cohort),
        ];
        if ($withdigest) {
            $result["members_sha256"] = self::members_revision((int)$cohort->id);
        }
        return $result;
    }

    /**
     * Method search.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public static function search(array $arguments, authenticated_identity $identity): array {
        global $DB;
        $context = self::scope((int)($arguments["contextid"] ?? 0));
        require_capability("moodle/cohort:view", $context);
        $search = trim($arguments["search"] ?? "");
        $limit = min(50, (int)($arguments["limit"] ?? 25));
        $offset = (int)($arguments["offset"] ?? 0);
        $params = ["contextid" => $context->id];
        $where = "contextid = :contextid";
        if ($search !== "") {
            $params["searchname"] = "%" . $DB->sql_like_escape($search) . "%";
            $params["searchid"] = $params["searchname"];
            $where .= " AND (" . $DB->sql_like("name", ":searchname", false) .
                " OR " . $DB->sql_like("idnumber", ":searchid", false) . ")";
        }
        $total = $DB->count_records_sql("SELECT COUNT(1) FROM {cohort} WHERE " . $where, $params);
        $rows = $DB->get_records_sql(
            "SELECT * FROM {cohort} WHERE " . $where . " ORDER BY name ASC, id ASC",
            $params, $offset, $limit);
        return [
            "contextid" => $context->id, "total" => (int)$total,
            "offset" => $offset, "limit" => $limit,
            "cohorts" => array_values(array_map(
                static fn($cohort): array => self::overview($cohort, false), $rows)),
        ];
    }

    /**
     * Method get.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public static function get(array $arguments, authenticated_identity $identity): array {
        return self::overview(self::cohort((int)$arguments["cohortid"]));
    }

    /**
     * Method members.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public static function members(array $arguments, authenticated_identity $identity): array {
        global $DB;
        $cohort = self::cohort((int)$arguments["cohortid"], "moodle/cohort:assign");
        $limit = min(50, (int)($arguments["limit"] ?? 25));
        $offset = (int)($arguments["offset"] ?? 0);
        $sql = "SELECT u.id, u.username, u.firstname, u.lastname
                  FROM {cohort_members} cm
                  JOIN {user} u ON u.id = cm.userid
                 WHERE cm.cohortid = :cohortid AND u.deleted = 0
              ORDER BY u.id ASC";
        $users = $DB->get_records_sql($sql, ["cohortid" => $cohort->id], $offset, $limit);
        return [
            "cohortid" => (int)$cohort->id,
            "total" => $DB->count_records_sql(
                "SELECT COUNT(1) FROM {cohort_members} cm JOIN {user} u ON u.id = cm.userid
                  WHERE cm.cohortid = :cohortid AND u.deleted = 0", ["cohortid" => $cohort->id]),
            "limit" => $limit, "offset" => $offset, "members_sha256" => self::members_revision((int)$cohort->id),
            "members" => array_values(array_map(static function($user): array {
                return ["userid" => (int)$user->id, "username" => $user->username,
                    "firstname" => $user->firstname, "lastname" => $user->lastname];
            }, $users)),
        ];
    }

    /**
     * Method courses.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public static function courses(array $arguments, authenticated_identity $identity): array {
        global $DB;
        $cohort = self::cohort((int)$arguments["cohortid"]);
        $rows = $DB->get_records_sql(
            "SELECT e.id, e.courseid, e.roleid, e.status, c.fullname, c.shortname
               FROM {enrol} e
               JOIN {course} c ON c.id = e.courseid
              WHERE e.enrol = :method AND e.customint1 = :cohortid
           ORDER BY c.id ASC",
            ["method" => "cohort", "cohortid" => $cohort->id]);
        $courses = [];
        foreach ($rows as $row) {
            $context = context_course::instance((int)$row->courseid, IGNORE_MISSING);
            if (!$context || !has_capability("moodle/course:enrolconfig", $context)) {
                continue;
            }
            $courses[] = [
                "courseid" => (int)$row->courseid, "fullname" => $row->fullname,
                "shortname" => $row->shortname, "enrolid" => (int)$row->id,
                "roleid" => (int)$row->roleid, "enabled" => !(bool)$row->status,
            ];
        }
        return ["cohortid" => (int)$cohort->id, "courses" => $courses, "total_visible" => count($courses)];
    }

    /**
     * Method new_data.
     *
     * @param array $arguments Parameter arguments.
     * @return \stdClass Return value.
     */
    private static function new_data(array $arguments): \stdClass {
        $context = self::scope((int)($arguments["contextid"] ?? 0));
        require_capability("moodle/cohort:manage", $context);
        $name = self::clean_name($arguments["name"]);
        $idnumber = trim($arguments["idnumber"] ?? "");
        self::unique_idnumber($idnumber);
        return (object)[
            "contextid" => $context->id, "name" => $name, "idnumber" => $idnumber,
            "description" => clean_param($arguments["description"] ?? "", PARAM_TEXT),
            "descriptionformat" => FORMAT_PLAIN,
            "visible" => !empty($arguments["visible"]) || !array_key_exists("visible", $arguments) ? 1 : 0,
            "component" => "",
        ];
    }

    /**
     * Method create_preview.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public static function create_preview(array $arguments, authenticated_identity $identity): array {
        $data = self::new_data($arguments);
        return ["action" => "create_cohort", "contextid" => (int)$data->contextid,
            "name" => $data->name, "idnumber" => $data->idnumber,
            "visible" => (bool)$data->visible, "requires_explicit_confirmation" => true];
    }

    /**
     * Method create.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public static function create(array $arguments, authenticated_identity $identity): array {
        self::load_api();
        $data = self::new_data($arguments);
        $cohortid = cohort_add_cohort($data);
        return self::overview(self::cohort($cohortid, "moodle/cohort:manage"));
    }

    /**
     * Method updated_data.
     *
     * @param array $arguments Parameter arguments.
     * @return \stdClass Return value.
     */
    private static function updated_data(array $arguments): \stdClass {
        $cohort = self::cohort((int)$arguments["cohortid"], "moodle/cohort:manage");
        self::editable($cohort);
        self::expected_revision($cohort, $arguments);
        if (array_key_exists("name", $arguments)) {
            $cohort->name = self::clean_name($arguments["name"]);
        }
        if (array_key_exists("idnumber", $arguments)) {
            $cohort->idnumber = trim($arguments["idnumber"]);
            self::unique_idnumber($cohort->idnumber, (int)$cohort->id);
        }
        if (array_key_exists("description", $arguments)) {
            $cohort->description = clean_param($arguments["description"], PARAM_TEXT);
            $cohort->descriptionformat = FORMAT_PLAIN;
        }
        if (array_key_exists("visible", $arguments)) {
            $cohort->visible = (int)$arguments["visible"];
        }
        return $cohort;
    }

    /**
     * Method update_preview.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public static function update_preview(array $arguments, authenticated_identity $identity): array {
        $data = self::updated_data($arguments);
        return ["action" => "update_cohort", "cohortid" => (int)$data->id,
            "name" => $data->name, "idnumber" => $data->idnumber,
            "visible" => (bool)$data->visible, "requires_explicit_confirmation" => true];
    }

    /**
     * Method update.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public static function update(array $arguments, authenticated_identity $identity): array {
        self::load_api();
        $data = self::updated_data($arguments);
        cohort_update_cohort($data);
        return self::overview(self::cohort((int)$data->id, "moodle/cohort:manage"));
    }

    /**
     * Method deletable.
     *
     * @param array $arguments Parameter arguments.
     * @return \stdClass Return value.
     */
    private static function deletable(array $arguments): \stdClass {
        $cohort = self::cohort((int)$arguments["cohortid"], "moodle/cohort:manage");
        self::editable($cohort);
        self::expected_revision($cohort, $arguments);
        self::expected_members($cohort, $arguments);
        if (self::linked_count((int)$cohort->id) !== 0) {
            throw new api_exception("Unlink all cohort enrolment instances before deletion", 409);
        }
        return $cohort;
    }

    /**
     * Method delete_preview.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public static function delete_preview(array $arguments, authenticated_identity $identity): array {
        $cohort = self::deletable($arguments);
        global $DB;
        return ["action" => "delete_cohort", "cohortid" => (int)$cohort->id,
            "name" => $cohort->name,
            "members_to_remove" => $DB->count_records("cohort_members", ["cohortid" => $cohort->id]),
            "requires_explicit_confirmation" => true];
    }

    /**
     * Method delete.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public static function delete(array $arguments, authenticated_identity $identity): array {
        self::load_api();
        $cohort = self::deletable($arguments);
        cohort_delete_cohort($cohort);
        return ["cohortid" => (int)$cohort->id, "deleted" => true];
    }

    /**
     * Method membership_target.
     *
     * @param array $arguments Parameter arguments.
     * @param bool $remove Parameter remove.
     * @return \stdClass Return value.
     */
    private static function membership_target(array $arguments, bool $remove): \stdClass {
        global $DB;
        $cohort = self::cohort((int)$arguments["cohortid"], "moodle/cohort:assign");
        self::editable($cohort);
        self::expected_members($cohort, $arguments);
        $ids = $arguments["userids"];
        if (count($ids) !== count(array_unique($ids))) {
            throw new api_exception("Duplicate user IDs", 400);
        }
        foreach ($ids as $userid) {
            $user = $DB->get_record("user", ["id" => (int)$userid], "id,deleted");
            if (!$user || (!$remove && ($user->deleted || isguestuser((int)$userid)))) {
                throw new api_exception("Invalid or deleted user: " . $userid, 400);
            }
            $exists = $DB->record_exists("cohort_members",
                ["cohortid" => $cohort->id, "userid" => (int)$userid]);
            if ($remove !== $exists) {
                throw new api_exception("Cohort membership has already changed for user " . $userid, 409);
            }
        }
        return $cohort;
    }

    /**
     * Method members_preview.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @param bool $remove Parameter remove.
     * @return array Return value.
     */
    public static function members_preview(array $arguments, authenticated_identity $identity, bool $remove): array {
        $cohort = self::membership_target($arguments, $remove);
        return ["action" => $remove ? "remove_cohort_members" : "add_cohort_members",
            "cohortid" => (int)$cohort->id, "userids" => $arguments["userids"],
            "linkedcourses_may_sync" => self::linked_count((int)$cohort->id),
            "requires_explicit_confirmation" => true];
    }

    /**
     * Method change_members.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @param bool $remove Parameter remove.
     * @return array Return value.
     */
    public static function change_members(array $arguments, authenticated_identity $identity, bool $remove): array {
        global $DB;
        self::load_api();
        $cohort = self::membership_target($arguments, $remove);
        $transaction = $DB->start_delegated_transaction();
        foreach ($arguments["userids"] as $userid) {
            if ($remove) {
                cohort_remove_member((int)$cohort->id, (int)$userid);
            } else {
                cohort_add_member((int)$cohort->id, (int)$userid);
            }
        }
        $transaction->allow_commit();
        return ["cohortid" => (int)$cohort->id, "affected" => count($arguments["userids"]),
            "members_sha256" => self::members_revision((int)$cohort->id)];
    }

    /**
     * Method enrol_target.
     *
     * @param array $arguments Parameter arguments.
     * @return array Return value.
     */
    private static function enrol_target(array $arguments): array {
        global $DB, $CFG;
        self::load_api();
        require_once($CFG->libdir . "/enrollib.php");
        $course = $DB->get_record("course", ["id" => (int)$arguments["courseid"]], "*", MUST_EXIST);
        $coursecontext = context_course::instance((int)$course->id);
        require_capability("moodle/course:enrolconfig", $coursecontext);
        require_capability("enrol/cohort:config", $coursecontext);
        require_capability("moodle/role:assign", $coursecontext);
        $cohort = self::cohort((int)$arguments["cohortid"]);
        if (!cohort_can_view_cohort($cohort, $coursecontext)) {
            throw new api_exception("Cohort is not available in this course context", 403);
        }
        if (!enrol_is_enabled("cohort")) {
            throw new api_exception("Cohort enrolment method is disabled", 409);
        }
        if ($DB->record_exists("enrol",
                ["enrol" => "cohort", "courseid" => $course->id, "customint1" => $cohort->id])) {
            throw new api_exception("Cohort already linked to this course", 409);
        }
        $roles = get_assignable_roles($coursecontext);
        if (!isset($roles[(int)$arguments["roleid"]])) {
            throw new api_exception("Role cannot be assigned in this course", 403);
        }
        $plugin = enrol_get_plugin("cohort");
        if (!$plugin || !$plugin->can_add_instance((int)$course->id)) {
            throw new api_exception("Cohort enrolment cannot be configured in this course", 403);
        }
        return [$course, $cohort, $plugin];
    }

    /**
     * Method enrol_preview.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public static function enrol_preview(array $arguments, authenticated_identity $identity): array {
        [$course, $cohort] = self::enrol_target($arguments);
        return ["action" => "link_cohort_to_course", "cohortid" => (int)$cohort->id,
            "cohortname" => $cohort->name, "courseid" => (int)$course->id,
            "roleid" => (int)$arguments["roleid"], "will_synchronise_members" => true,
            "requires_explicit_confirmation" => true];
    }

    /**
     * Method enrol.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public static function enrol(array $arguments, authenticated_identity $identity): array {
        [$course, $cohort, $plugin] = self::enrol_target($arguments);
        $id = $plugin->add_instance($course, [
            "customint1" => (int)$cohort->id, "customint2" => 0,
            "roleid" => (int)$arguments["roleid"], "status" => ENROL_INSTANCE_ENABLED,
        ]);
        if (!$id) {
            throw new api_exception("Unable to create cohort enrolment instance", 409);
        }
        return ["cohortid" => (int)$cohort->id, "courseid" => (int)$course->id,
            "enrolid" => (int)$id, "synchronised" => true];
    }
}
