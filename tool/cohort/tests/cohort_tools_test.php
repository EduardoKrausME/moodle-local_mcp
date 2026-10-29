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
 * Cohort tool and native Moodle API integration tests.
 *
 * @package   mcptool_cohort
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_cohort;

use advanced_testcase;
use context_system;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use mcptool_cohort\read\get_cohort;
use mcptool_cohort\read\get_cohort_members;
use mcptool_cohort\read\search_cohorts;
use mcptool_cohort\write\add_cohort_members;
use mcptool_cohort\write\create_cohort;
use mcptool_cohort\write\delete_cohort;
use mcptool_cohort\write\remove_cohort_members;
use mcptool_cohort\write\update_cohort;

/**
 * Verifies MCP read/write contracts and core cohort mutations.
 *
 * @covers \mcptool_cohort\provider
 * @covers \mcptool_cohort\service
 */
final class cohort_tools_test extends advanced_testcase {
    private function identity(): authenticated_identity {
        global $USER;
        return new authenticated_identity((int)$USER->id, "test", ["mcp:read", "mcp:write"]);
    }

    public function test_provider_registers_tools_and_confirms_writes(): void {
        $provider = new provider();
        $read = array_map(static fn($tool) => $tool->get_name(), $provider->get_read_tools());
        $write = array_map(static fn($tool) => $tool->get_name(), $provider->get_write_tools());
        $this->assertSame([
            "cohort_search_cohorts", "cohort_get_cohort", "cohort_get_cohort_members",
            "cohort_get_cohort_courses",
        ], $read);
        $this->assertSame([
            "cohort_create_cohort", "cohort_update_cohort", "cohort_delete_cohort",
            "cohort_add_cohort_members", "cohort_remove_cohort_members", "cohort_enrol_cohort",
        ], $write);
        foreach ($provider->get_write_tools() as $tool) {
            $this->assertTrue($tool->requires_confirmation());
            $this->assertTrue($tool->supports_dry_run());
        }
        $this->assertTrue((new delete_cohort())->is_destructive());
        $this->assertTrue((new remove_cohort_members())->is_destructive());
    }

    public function test_create_search_update_and_delete_using_native_api(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $identity = $this->identity();
        $contextid = context_system::instance()->id;
        $create = new create_cohort();
        $input = ["contextid" => $contextid, "name" => "Engineering team",
            "idnumber" => "engineering-2026", "description" => "Production team"];
        $before = $DB->count_records("cohort");
        $preview = $create->preview($input, $identity);
        $this->assertSame("create_cohort", $preview["action"]);
        $this->assertSame($before, $DB->count_records("cohort"));
        $saved = $create->execute($input, $identity);
        $this->assertSame($before + 1, $DB->count_records("cohort"));
        $this->assertSame("engineering-2026", $saved["idnumber"]);
        $id = $saved["cohortid"];

        $list = (new search_cohorts())->execute(["contextid" => $contextid,
            "search" => "engineering"], $identity);
        $this->assertSame(1, $list["total"]);
        $this->assertSame($id, $list["cohorts"][0]["cohortid"]);

        $update = new update_cohort();
        $arguments = ["cohortid" => $id, "expected_revision" => $saved["revision"],
            "name" => "Engineering operations", "visible" => false];
        $this->assertSame("update_cohort", $update->preview($arguments, $identity)["action"]);
        $updated = $update->execute($arguments, $identity);
        $this->assertSame("Engineering operations", $updated["name"]);
        $this->assertFalse($updated["visible"]);

        try {
            $update->execute($arguments, $identity);
            $this->fail("A stale cohort revision must be rejected.");
        } catch (api_exception $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }

        $delete = new delete_cohort();
        $deleteargs = ["cohortid" => $id, "expected_revision" => $updated["revision"]];
        $this->assertTrue($delete->preview($deleteargs, $identity)["requires_explicit_confirmation"]);
        $this->assertTrue($delete->execute($deleteargs, $identity)["deleted"]);
        $this->assertFalse($DB->record_exists("cohort", ["id" => $id]));
    }

    public function test_add_and_remove_members_check_whole_cohort_fingerprint(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $identity = $this->identity();
        $saved = (new create_cohort())->execute(["name" => "Operators"], $identity);
        $id = $saved["cohortid"];
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $read = new get_cohort_members();
        $initial = $read->execute(["cohortid" => $id], $identity);
        $this->assertSame(0, $initial["total"]);
        $args = ["cohortid" => $id, "userids" => [(int)$user1->id, (int)$user2->id],
            "expected_members_sha256" => $initial["members_sha256"]];
        $add = new add_cohort_members();
        $this->assertSame(2, $add->preview($args, $identity)["userids"] ? count($args["userids"]) : 0);
        $this->assertSame(2, $add->execute($args, $identity)["affected"]);
        $this->assertSame(2, $DB->count_records("cohort_members", ["cohortid" => $id]));

        try {
            $add->execute($args, $identity);
            $this->fail("A stale membership digest must not add duplicates.");
        } catch (api_exception $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }

        $current = $read->execute(["cohortid" => $id, "limit" => 1], $identity);
        $this->assertCount(1, $current["members"]);
        $this->assertSame(2, $current["total"]);
        $remove = new remove_cohort_members();
        $removeargs = ["cohortid" => $id, "userids" => [(int)$user1->id],
            "expected_members_sha256" => $current["members_sha256"]];
        $remove->execute($removeargs, $identity);
        $this->assertSame(1, $DB->count_records("cohort_members", ["cohortid" => $id]));
    }

    public function test_component_owned_cohort_is_read_only(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $identity = $this->identity();
        $saved = (new create_cohort())->execute(["name" => "Managed by integration"], $identity);
        $id = $saved["cohortid"];
        $DB->set_field("cohort", "component", "local_testintegration", ["id" => $id]);
        $current = (new get_cohort())->execute(["cohortid" => $id], $identity);
        try {
            (new update_cohort())->preview(["cohortid" => $id,
                "expected_revision" => $current["revision"], "name" => "Cannot edit"], $identity);
            $this->fail("Component-owned cohort must not be changed.");
        } catch (api_exception $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }
    }
}
