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
 * Role assignment authorization regression tests for MCP enrolment.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use context_course;
use context_system;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\write\tool\enrol_user;

/**
 * Verify preview and execution enforce Moodle's role-assignment restrictions.
 *
 * @covers \local_mcp\write\tool\enrol_user
 */
final class enrol_user_security_test extends advanced_testcase {
    /**
     * An administrator can preview a student enrolment, including the role.
     *
     * @return void
     */
    public function test_admin_can_preview_assignable_role(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $studentrole = (int)$DB->get_field("role", "id", ["shortname" => "student"], MUST_EXIST);
        $identity = new authenticated_identity((int)get_admin()->id, "test", ["mcp:write"]);
        $tool = new enrol_user();

        $preview = $tool->preview([
            "courseid" => (int)$course->id,
            "userid" => (int)$student->id,
            "roleid" => $studentrole,
        ], $identity);
        $this->assertSame($studentrole, $preview["roleid"]);
        $this->assertFalse($preview["already_enrolled"]);
    }

    /**
     * The manual enrolment capability must not imply permission to assign roles.
     *
     * @return void
     */
    public function test_enroller_without_role_assignment_cannot_preview_or_execute(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        $context = context_course::instance($course->id);
        $roleid = create_role("MCP enrol only", "mcp_enrol_only", "Can enrol but not assign roles.");
        assign_capability("enrol/manual:enrol", CAP_ALLOW, $roleid, context_system::instance()->id, true);
        role_assign($roleid, $user->id, $context->id);
        $this->setUser($user);
        $this->assertTrue(has_capability("enrol/manual:enrol", $context, $user->id));
        $this->assertFalse(has_capability("moodle/role:assign", $context, $user->id));
        $identity = new authenticated_identity((int)$user->id, "test", ["mcp:write"]);
        $args = ["courseid" => (int)$course->id, "userid" => (int)$student->id, "roleid" => $roleid];
        $tool = new enrol_user();

        foreach (["preview", "execute"] as $method) {
            try {
                $tool->$method($args, $identity);
                $this->fail("The MCP tool must refuse a role assignment without permission.");
            } catch (api_exception $e) {
                $this->assertSame(403, $e->httpstatus);
            }
        }
    }

    /**
     * Assignment of a role outside the user's allowed role list is forbidden.
     *
     * @return void
     */
    public function test_unassignable_role_is_rejected(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        $context = context_course::instance($course->id);
        $roleid = create_role("MCP role manager", "mcp_role_manager", "Can enrol and assign selected roles.");
        $forbiddenrole = create_role("MCP forbidden target", "mcp_forbidden_target", "Not assignable.");
        assign_capability("enrol/manual:enrol", CAP_ALLOW, $roleid, context_system::instance()->id, true);
        assign_capability("moodle/role:assign", CAP_ALLOW, $roleid, context_system::instance()->id, true);
        role_assign($roleid, $user->id, $context->id);
        $this->setUser($user);
        $this->assertTrue(has_capability("moodle/role:assign", $context, $user->id));
        $this->assertArrayNotHasKey($forbiddenrole, get_assignable_roles($context, ROLENAME_ALIAS, false, $user->id));
        $tool = new enrol_user();
        $identity = new authenticated_identity((int)$user->id, "test", ["mcp:write"]);
        $this->expectException(api_exception::class);
        $tool->preview([
            "courseid" => (int)$course->id,
            "userid" => (int)$student->id,
            "roleid" => $forbiddenrole,
        ], $identity);
    }
}
