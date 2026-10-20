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
 * Moodle MCP connection eligibility and tool authorization tests.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use context_course;
use context_coursecat;
use context_system;
use local_mcp\exception\api_exception;
use local_mcp\security\capability_guard;
use local_mcp\security\user_access;

/**
 * Ensure regular users can connect without receiving administrator privileges.
 *
 * @covers \local_mcp\security\user_access
 * @covers \local_mcp\security\capability_guard
 */
final class connection_permissions_test extends advanced_testcase {
    /**
     * Regular Moodle accounts are eligible, but guest and inactive accounts are not.
     *
     * @return void
     */
    public function test_connection_requires_active_non_guest_user(): void {
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $this->assertTrue(user_access::is_eligible($student));
        $this->assertTrue(user_access::is_eligible(get_admin()));

        $guest = get_complete_user_data("username", "guest");
        $this->assertNotFalse($guest);
        $this->assertFalse(user_access::is_eligible($guest));

        $suspended = clone $student;
        $suspended->suspended = 1;
        $this->assertFalse(user_access::is_eligible($suspended));

        $deleted = clone $student;
        $deleted->deleted = 1;
        $this->assertFalse(user_access::is_eligible($deleted));

        $unconfirmed = clone $student;
        $unconfirmed->confirmed = 0;
        $this->assertFalse(user_access::is_eligible($unconfirmed));
    }

    /**
     * A connected student cannot execute site administration tools.
     *
     * @return void
     */
    public function test_student_cannot_access_site_configuration(): void {
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $this->expectException(api_exception::class);
        capability_guard::check("moodle/site:config", context_system::instance(), (int)$student->id);
    }

    /**
     * Category-level permissions apply in descendant courses, not unrelated courses.
     *
     * @return void
     */
    public function test_category_manager_can_manage_activities_only_in_own_courses(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $category = $this->getDataGenerator()->create_category();
        $managedcourse = $this->getDataGenerator()->create_course(["category" => $category->id]);
        $othercategory = $this->getDataGenerator()->create_category();
        $othercourse = $this->getDataGenerator()->create_course(["category" => $othercategory->id]);

        $roleid = (int)$DB->get_field("role", "id", ["shortname" => "manager"], MUST_EXIST);
        role_assign($roleid, (int)$user->id, context_coursecat::instance($category->id)->id);

        capability_guard::check(
            "moodle/course:manageactivities",
            context_course::instance($managedcourse->id),
            (int)$user->id
        );
        $this->expectException(api_exception::class);
        capability_guard::check(
            "moodle/course:manageactivities",
            context_course::instance($othercourse->id),
            (int)$user->id
        );
    }

    /**
     * Forum reads and creation are bound to separate module/course permissions.
     *
     * @return void
     */
    public function test_forum_read_and_creation_declare_distinct_capabilities(): void {
        $this->assertSame(
            "mod/forum:viewdiscussion",
            (new \mcptool_forum\read\get_posts())->get_required_capability()
        );
        $this->assertSame(
            "moodle/course:manageactivities",
            (new \mcptool_forum\write\create_activity())->get_required_capability()
        );
    }
}
