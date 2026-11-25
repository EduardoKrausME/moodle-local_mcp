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
        $this->assertTrue(activity_replacement::execute($args, $identity)["updated"]);
        $this->assertEquals($before, $DB->get_record("course_completion_crit_compl", ["id" => $historyid]));
        $this->assertSame((int)$target->cmid, (int)$DB->get_field("course_completion_criteria", "moduleinstance",
            ["id" => $criterionid]));
        $this->assertEquals($beforeactivity, $DB->get_record("course_modules_completion", ["id" => $activityhistoryid]));
        $this->assertFalse($DB->record_exists("course_modules_completion", ["coursemoduleid" => $target->cmid]));
    }
}
