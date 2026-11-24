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
 * Shared native course-module positioning and visibility operations.
 *
 * @package local_mcp
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\course;

use context_course;
use context_module;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;

/**
 * Resolve and verify course-module changes without writing course tables directly.
 */
final class activity_position_service {
    /**
     * Get an existing, manageable module (including hidden modules).
     *
     * @param int $cmid Module ID.
     * @param int $userid Connected user.
     * @param string $capability Required capability.
     * @return \cm_info
     */
    private static function module(int $cmid, int $userid, string $capability): \cm_info {
        global $DB;
        $record = $DB->get_record("course_modules", ["id" => $cmid], "id,course,deletioninprogress");
        if (!$record || !empty($record->deletioninprogress)) {
            throw new api_exception("activity_not_found", 404);
        }
        require_capability($capability, context_module::instance($cmid), $userid);
        return get_fast_modinfo((int)$record->course, $userid)->get_cm($cmid);
    }

    /**
     * Resolve a position in an existing section. Also used before creating a module.
     *
     * @param int $courseid Course ID.
     * @param array $arguments Position arguments.
     * @param authenticated_identity $identity Connected identity.
     * @param int $cmid Existing module being moved, or zero for creation.
     * @return array Position and ordered section sequence.
     */
    public static function position(int $courseid, array $arguments, authenticated_identity $identity,
            int $cmid = 0): array {
        global $DB;
        $before = (int)($arguments["beforecmid"] ?? 0);
        $after = (int)($arguments["aftercmid"] ?? 0);
        if (($before && $after) || ($cmid && ($before === $cmid || $after === $cmid)) ||
                (!$before && !$after && !isset($arguments["sectionnum"]))) {
            throw new api_exception("invalid_activity_position", 400, "", [],
                "Specify one anchor or an existing destination section; do not anchor to the moving activity.");
        }
        $anchorid = $before ?: $after;
        $anchor = null;
        if ($anchorid) {
            $anchor = self::module($anchorid, $identity->userid, "moodle/course:manageactivities");
            if ((int)$anchor->course !== $courseid ||
                    (isset($arguments["sectionnum"]) && (int)$anchor->sectionnum !== (int)$arguments["sectionnum"])) {
                throw new api_exception("activity_position_mismatch", 409);
            }
        }
        $sectionnum = $anchor ? (int)$anchor->sectionnum : (int)$arguments["sectionnum"];
        $section = $DB->get_record("course_sections", ["course" => $courseid, "section" => $sectionnum]);
        if (!$section) {
            throw new api_exception("section_not_found", 404);
        }
        $sequence = array_map("intval", array_filter(explode(",", (string)$section->sequence), "strlen"));
        $sequence = array_values(array_diff($sequence, [$cmid]));
        $index = count($sequence);
        if ($anchorid) {
            $index = array_search($anchorid, $sequence, true);
            if ($index === false) {
                throw new api_exception("activity_position_mismatch", 409);
            }
            $index += $after ? 1 : 0;
        }
        return [
            "courseid" => $courseid, "sectionid" => (int)$section->id, "sectionnum" => $sectionnum,
            "sectionvisible" => (bool)$section->visible, "anchorcmid" => $anchorid ?: null,
            "anchorname" => $anchor ? $anchor->name : null,
            "beforecmid" => $sequence[$index] ?? null, "aftercmid" => $sequence[$index - 1] ?? null,
            "index" => $index, "sequence" => $sequence,
        ];
    }

    /**
     * Preview a move and enforce module and cross-section permissions.
     *
     * @param array $arguments Tool input.
     * @param authenticated_identity $identity Connected identity.
     * @return array Move preview.
     */
    public static function move_preview(array $arguments, authenticated_identity $identity): array {
        $cm = self::module((int)$arguments["cmid"], $identity->userid, "moodle/course:manageactivities");
        $position = self::position((int)$cm->course, $arguments, $identity, (int)$cm->id);
        if ((int)$cm->section !== $position["sectionid"]) {
            require_capability("moodle/course:manageactivities", context_course::instance($cm->course), $identity->userid);
        }
        if (isset($arguments["expectedsectionnum"]) && (int)$arguments["expectedsectionnum"] !== (int)$cm->sectionnum) {
            throw new api_exception("course_module_changed", 409);
        }
        return $position + ["cmid" => (int)$cm->id, "name" => $cm->name,
            "previoussectionnum" => (int)$cm->sectionnum, "previousvisible" => (bool)$cm->visible];
    }

    /**
     * Move through Moodle core, verifying the resulting sequence before commit.
     *
     * @param array $arguments Tool input.
     * @param authenticated_identity $identity Connected identity.
     * @return array Verified placement.
     */
    public static function move(array $arguments, authenticated_identity $identity): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/course/lib.php");
        $transaction = $DB->start_delegated_transaction();
        $plan = self::move_preview($arguments, $identity);
        $cm = $DB->get_record("course_modules", ["id" => $plan["cmid"]], "*", MUST_EXIST);
        $section = $DB->get_record("course_sections", ["id" => $plan["sectionid"]], "*", MUST_EXIST);
        $expected = $plan["sequence"];
        array_splice($expected, $plan["index"], 0, [$plan["cmid"]]);
        $current = array_map("intval", array_filter(explode(",", (string)$section->sequence), "strlen"));
        $changed = (int)$cm->section !== $plan["sectionid"] || $expected !== $current;
        if ($changed) {
            $actionclass = \core_courseformat\local\cmactions::class;
            if (method_exists($actionclass, "move_before") && method_exists($actionclass, "move_end_section")) {
                $action = \core_courseformat\formatactions::cm($plan["courseid"]);
                $ok = $plan["beforecmid"]
                    ? $action->move_before($plan["cmid"], $plan["beforecmid"])
                    : $action->move_end_section($plan["cmid"], $plan["sectionid"]);
                if (!$ok) {
                    throw new api_exception("activity_move_failed", 409);
                }
            } else {
                $before = $plan["beforecmid"] ? (object)["id" => $plan["beforecmid"]] : null;
                moveto_module($cm, $section, $before);
            }
        }
        $actual = $DB->get_field("course_sections", "sequence", ["id" => $section->id], MUST_EXIST);
        $actual = array_map("intval", array_filter(explode(",", (string)$actual), "strlen"));
        $updated = $DB->get_record("course_modules", ["id" => $cm->id], "*", MUST_EXIST);
        if ($expected !== $actual || (int)$updated->section !== $plan["sectionid"]) {
            throw new api_exception("activity_move_failed", 409);
        }
        $transaction->allow_commit();
        unset($plan["sequence"]);
        return $plan + ["changed" => $changed, "visible" => (bool)$updated->visible];
    }

    /**
     * Preview native visibility; it does not publish authored content.
     *
     * @param array $arguments Tool input.
     * @param authenticated_identity $identity Connected identity.
     * @param bool $visible Desired visibility.
     * @return array Visibility preview.
     */
    public static function visibility_preview(array $arguments, authenticated_identity $identity, bool $visible): array {
        $cm = self::module((int)$arguments["cmid"], $identity->userid, "moodle/course:activityvisibility");
        if (isset($arguments["expectedvisible"]) && (bool)$arguments["expectedvisible"] !== (bool)$cm->visible) {
            throw new api_exception("course_module_changed", 409);
        }
        return ["cmid" => (int)$cm->id, "courseid" => (int)$cm->course, "name" => $cm->name,
            "sectionnum" => (int)$cm->sectionnum, "previousvisible" => (bool)$cm->visible,
            "previousvisibleoncoursepage" => (bool)$cm->visibleoncoursepage, "visible" => $visible,
            "visibleoncoursepage" => true, "sectionvisible" => (bool)$cm->get_section_info()->visible];
    }

    /**
     * Apply native visibility including Moodle gradebook and calendar side effects.
     *
     * @param array $arguments Tool input.
     * @param authenticated_identity $identity Connected identity.
     * @param bool $visible Desired visibility.
     * @return array Verified visibility flags.
     */
    public static function visibility(array $arguments, authenticated_identity $identity, bool $visible): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/course/lib.php");
        $transaction = $DB->start_delegated_transaction();
        $plan = self::visibility_preview($arguments, $identity, $visible);
        $changed = $plan["previousvisible"] !== $visible || !$plan["previousvisibleoncoursepage"];
        if ($changed && !set_coursemodule_visible($plan["cmid"], (int)$visible, 1)) {
            throw new api_exception("activity_visibility_failed", 409);
        }
        $cm = $DB->get_record("course_modules", ["id" => $plan["cmid"]], "*", MUST_EXIST);
        if ((bool)$cm->visible !== $visible || !(bool)$cm->visibleoncoursepage) {
            throw new api_exception("activity_visibility_failed", 409);
        }
        $transaction->allow_commit();
        return $plan + ["changed" => $changed];
    }
}
