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
 * Safe activity criterion replacement.
 * @package local_mcp
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\completion\activity_replacement;
use local_mcp\security\authenticated_identity;

/**
 * Course completion criterion history must survive activity replacement.
 * @covers \local_mcp\completion\activity_replacement
 */
final class activity_replacement_test extends advanced_testcase {
    /** A completed criterion keeps its ID and timestamp. */
    public function test_preserve_history(): void {
        global $CFG, $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->libdir . "/completionlib.php");
        $CFG->enablecompletion = 1;
        $course = $this->getDataGenerator()->create_course(["enablecompletion" => 1]);
        $source = $this->getDataGenerator()->create_module("page", ["course" => $course->id],
            ["completion" => 2, "completionview" => 1]);
        $target = $this->getDataGenerator()->create_module("page", ["course" => $course->id],
            ["completion" => 2, "completionview" => 1]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, "student");
        $criterionid = $DB->insert_record("course_completion_criteria", (object)["course" => $course->id,
            "criteriatype" => COMPLETION_CRITERIA_TYPE_ACTIVITY, "module" => "page", "moduleinstance" => $source->cmid]);
        $historyid = $DB->insert_record("course_completion_crit_compl", (object)["course" => $course->id,
            "userid" => $user->id, "criteriaid" => $criterionid, "timecompleted" => 12345]);
        $activityhistoryid = $DB->insert_record("course_modules_completion", (object)[
            "coursemoduleid" => $source->cmid, "userid" => $user->id,
            "completionstate" => COMPLETION_COMPLETE, "timemodified" => 12345]);
        $beforeactivity = $DB->get_record("course_modules_completion", ["id" => $activityhistoryid], "*", MUST_EXIST);
        $before = $DB->get_record("course_completion_crit_compl", ["id" => $historyid], "*", MUST_EXIST);
        $identity = new authenticated_identity($USER->id, "test", ["mcp:write"]);
        $args = ["courseid" => (int)$course->id, "sourcecmid" => (int)$source->cmid,
            "targetcmid" => (int)$target->cmid, "preservehistory" => true, "dependencies_reviewed" => true,
            "expected_criteria_sha256" => activity_replacement::fingerprint($course->id)];
        $plan = activity_replacement::preview($args, $identity);
        $this->assertSame((int)$criterionid, $plan["criteriaid"]);
        $this->assertSame(1, $plan["source_completed_students"]);
        $this->assertSame(1, $plan["completed_criterion_records"]);
        // A content change after approval must invalidate the migration plan.
        $DB->set_field("page", "content", "Revised material", ["id" => $target->id]);
        try {
            activity_replacement::preview($args + ["expected_replacement_sha256" => $plan["replacement_sha256"]], $identity);
            $this->fail("Stale content approval was accepted.");
        } catch (\local_mcp\exception\api_exception $e) {
            $this->assertSame("completion_criteria_changed", $e->machinecode);
        }
        // Pending activity evidence must never be lost when changing the criterion.
        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($other->id, $course->id, "student");
        $pending = $DB->insert_record("course_modules_completion", (object)[
            "coursemoduleid" => $source->cmid, "userid" => $other->id,
            "completionstate" => COMPLETION_COMPLETE, "timemodified" => 12346]);
        try {
            activity_replacement::preview($args, $identity);
            $this->fail("Pending academic evidence was accepted.");
        } catch (\local_mcp\exception\api_exception $e) {
            $this->assertSame("completion_history_pending", $e->machinecode);
        }
        $DB->insert_record("course_completion_crit_compl", (object)["course" => $course->id,
            "userid" => $other->id, "criteriaid" => $criterionid, "timecompleted" => 12346]);
        $this->assertTrue(activity_replacement::execute($args, $identity)["updated"]);
        $this->assertTrue($DB->record_exists("course_modules_completion", ["id" => $pending]));
        $this->assertEquals($before, $DB->get_record("course_completion_crit_compl", ["id" => $historyid]));
        $this->assertSame((int)$target->cmid, (int)$DB->get_field("course_completion_criteria", "moduleinstance",
            ["id" => $criterionid]));
        $this->assertEquals($beforeactivity, $DB->get_record("course_modules_completion", ["id" => $activityhistoryid]));
        $this->assertFalse($DB->record_exists("course_modules_completion", ["coursemoduleid" => $target->cmid]));
    }

    /** Reviewable organisation and access differences must not block replacement. */
    public function test_access_differences_are_warnings(): void {
        global $CFG, $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->libdir . "/completionlib.php");
        $CFG->enablecompletion = 1;
        $course = $this->getDataGenerator()->create_course(["enablecompletion" => 1, "numsections" => 2]);
        $source = $this->getDataGenerator()->create_module("page", ["course" => $course->id],
            ["completion" => 2, "completionview" => 1, "section" => 1]);
        $target = $this->getDataGenerator()->create_module("page", ["course" => $course->id],
            ["completion" => 2, "completionview" => 1, "section" => 2]);
        $sourcecm = $DB->get_record("course_modules", ["id" => $source->cmid], "*", MUST_EXIST);
        $targetcm = $DB->get_record("course_modules", ["id" => $target->cmid], "*", MUST_EXIST);
        $this->assertNotEquals($sourcecm->section, $targetcm->section);
        $DB->set_field("course_modules", "visible", 0, ["id" => $source->cmid]);
        $DB->set_field("course_modules", "visibleoncoursepage", 0, ["id" => $target->cmid]);
        $availability = json_encode(["op" => "&", "showc" => [true], "c" => [
            ["type" => "date", "d" => ">=", "t" => time() - 3600],
        ]]);
        $DB->set_field("course_modules", "availability", $availability, ["id" => $source->cmid]);
        $DB->set_field("course_sections", "availability", $availability, ["id" => $targetcm->section]);
        $criterionid = $DB->insert_record("course_completion_criteria", (object)["course" => $course->id,
            "criteriatype" => COMPLETION_CRITERIA_TYPE_ACTIVITY, "module" => "page", "moduleinstance" => $source->cmid]);
        $identity = new authenticated_identity($USER->id, "test", ["mcp:write"]);
        $args = ["courseid" => (int)$course->id, "sourcecmid" => (int)$source->cmid,
            "targetcmid" => (int)$target->cmid, "preservehistory" => true, "dependencies_reviewed" => true,
            "expected_criteria_sha256" => activity_replacement::fingerprint($course->id)];
        $plan = activity_replacement::preview($args, $identity);
        $this->assertSame((int)$criterionid, $plan["criteriaid"]);
        $this->assertSame([], $plan["dependencies"]);
        $this->assertEqualsCanonicalizing([
            "completion_source_hidden", "completion_target_stealth", "completion_sections_differ",
            "completion_source_restricted", "completion_target_restricted", "completion_availability_differs",
        ], array_column($plan["warnings"], "code"));
        $DB->set_field("course_sections", "availability", null, ["id" => $targetcm->section]);
        try {
            activity_replacement::preview($args + ["expected_replacement_sha256" => $plan["replacement_sha256"]], $identity);
            $this->fail("Stale section access approval was accepted.");
        } catch (\local_mcp\exception\api_exception $e) {
            $this->assertSame("completion_criteria_changed", $e->machinecode);
        }
        $DB->set_field("course_modules", "visible", 0, ["id" => $target->cmid]);
        try {
            activity_replacement::preview($args, $identity);
            $this->fail("A hidden destination was accepted.");
        } catch (\local_mcp\exception\api_exception $e) {
            $this->assertSame("completion_target_hidden", $e->machinecode);
        }
        $DB->set_field("course_modules", "visible", 1, ["id" => $target->cmid]);
        $DB->set_field("course_sections", "visible", 0, ["id" => $targetcm->section]);
        try {
            activity_replacement::preview($args, $identity);
            $this->fail("A hidden destination section was accepted.");
        } catch (\local_mcp\exception\api_exception $e) {
            $this->assertSame("completion_target_section_hidden", $e->machinecode);
        }
        $DB->set_field("course_sections", "visible", 1, ["id" => $targetcm->section]);
        $DB->set_field("course_modules", "availability", json_encode(["op" => "&", "showc" => [true], "c" => [
            ["type" => "completion", "cm" => (int)$target->cmid, "e" => 1],
        ]]), ["id" => $target->cmid]);
        try {
            activity_replacement::preview($args, $identity);
            $this->fail("A circular completion dependency was accepted.");
        } catch (\local_mcp\exception\api_exception $e) {
            $this->assertSame("completion_dependencies_present", $e->machinecode);
        }
        $DB->set_field("course_modules", "availability", null, ["id" => $target->cmid]);
        $this->assertTrue(activity_replacement::execute($args, $identity)["updated"]);
        $this->assertSame((int)$target->cmid, (int)$DB->get_field("course_completion_criteria", "moduleinstance",
            ["id" => $criterionid]));
    }
}
