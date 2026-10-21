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
 * Privacy API regression tests for OAuth and MCP user records.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use context_course;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\types\external_location;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use local_mcp\privacy\provider;

/**
 * Verify all user-bound tables participate in Moodle Privacy API operations.
 *
 * @covers \local_mcp\privacy\provider
 */
final class privacy_provider_test extends provider_testcase {
    /** @var string[] */
    private const TABLES = [
        "local_mcp_connection",
        "local_mcp_audit",
        "local_mcp_auth_code",
        "local_mcp_access_token",
        "local_mcp_refresh_token",
        "local_mcp_confirm",
    ];

    /**
     * Metadata lists all six tables and explains outgoing MCP tool responses.
     */
    public function test_metadata_covers_all_user_tables_and_external_client(): void {
        global $DB;
        $metadata = provider::get_metadata(new collection("local_mcp"))->get_collection();
        $this->assertCount(7, $metadata);
        $byname = [];
        foreach ($metadata as $item) {
            $byname[$item->get_name()] = $item;
        }
        foreach (self::TABLES as $table) {
            $this->assertArrayHasKey($table, $byname);
            $this->assertEqualsCanonicalizing(
                array_keys($DB->get_columns($table)),
                array_keys($byname[$table]->get_privacy_fields())
            );
        }
        $this->assertInstanceOf(external_location::class, $byname["mcp_client"]);
        foreach (["userid", "userprofile", "courses", "activities", "grades", "otherdata"] as $field) {
            $this->assertArrayHasKey($field, $byname["mcp_client"]->get_privacy_fields());
        }
    }

    /**
     * Data detection and user lists work even if there is only one table with a row.
     */
    public function test_contexts_are_found_for_each_user_table(): void {
        global $DB;
        $this->resetAfterTest();
        foreach (self::TABLES as $table) {
            $user = $this->getDataGenerator()->create_user();
            $this->assertEmpty(provider::get_contexts_for_userid((int)$user->id)->get_contextids());
            $this->create_records((int)$user->id);
            foreach (self::TABLES as $othertable) {
                if ($othertable !== $table) {
                    $DB->delete_records($othertable, ["userid" => $user->id]);
                }
            }
            $this->assertContains(context_system::instance()->id,
                provider::get_contexts_for_userid((int)$user->id)->get_contextids());
            $userlist = new userlist(context_system::instance(), "local_mcp");
            provider::get_users_in_context($userlist);
            $this->assertContains((int)$user->id, $userlist->get_userids());
            $DB->delete_records($table, ["userid" => $user->id]);
            $this->assertEmpty(provider::get_contexts_for_userid((int)$user->id)->get_contextids());
        }
        $course = $this->getDataGenerator()->create_course();
        $otherlist = new userlist(context_course::instance($course->id), "local_mcp");
        provider::get_users_in_context($otherlist);
        $this->assertEmpty($otherlist->get_userids());
    }

    /**
     * User export includes OAuth lifecycle data but never exposes stored credential hashes.
     */
    public function test_export_includes_all_records_without_credential_material(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->create_records((int)$user->id);
        $context = context_system::instance();
        provider::export_user_data(new approved_contextlist($user, "local_mcp", [$context->id]));
        $data = writer::with_context($context)->get_data([]);
        $this->assertNotEmpty($data);
        foreach (["connections", "audit", "authorization_codes", "access_tokens",
            "refresh_tokens", "confirmations"] as $group) {
            $this->assertCount(1, $data->{$group});
            $this->assertEquals($user->id, $data->{$group}[0]->userid);
        }
        $this->assertSame("127.0.0.1", $data->access_tokens[0]->lastip);
        $this->assertFalse(property_exists($data->access_tokens[0], "tokenhash"));
        $this->assertFalse(property_exists($data->authorization_codes[0], "codehash"));
        $this->assertFalse(property_exists($data->confirmations[0], "accesshash"));
    }

    /**
     * Deletion clears a user's tokens and connections without affecting another user.
     */
    public function test_delete_for_user_respects_context_and_other_users(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->create_records((int)$user->id);
        $this->create_records((int)$other->id);
        $course = $this->getDataGenerator()->create_course();
        provider::delete_data_for_user(new approved_contextlist(
            $user, "local_mcp", [context_course::instance($course->id)->id]
        ));
        foreach (self::TABLES as $table) {
            $this->assertTrue($DB->record_exists($table, ["userid" => $user->id]));
        }
        provider::delete_data_for_user(new approved_contextlist($user, "local_mcp", [context_system::instance()->id]));
        foreach (self::TABLES as $table) {
            $this->assertFalse($DB->record_exists($table, ["userid" => $user->id]));
            $this->assertTrue($DB->record_exists($table, ["userid" => $other->id]));
        }
        $this->assertEmpty(provider::get_contexts_for_userid((int)$user->id)->get_contextids());
    }

    /**
     * Bulk removal and system-wide context deletion cover all user tables.
     */
    public function test_delete_for_users_and_system_context(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->create_records((int)$user->id);
        $this->create_records((int)$other->id);
        $course = $this->getDataGenerator()->create_course();
        provider::delete_data_for_users(new approved_userlist(
            context_course::instance($course->id), "local_mcp", [$user->id]
        ));
        $this->assertTrue($DB->record_exists("local_mcp_confirm", ["userid" => $user->id]));
        provider::delete_data_for_users(new approved_userlist(context_system::instance(), "local_mcp", [$user->id]));
        foreach (self::TABLES as $table) {
            $this->assertFalse($DB->record_exists($table, ["userid" => $user->id]));
            $this->assertTrue($DB->record_exists($table, ["userid" => $other->id]));
        }
        provider::delete_data_for_all_users_in_context(context_course::instance($course->id));
        $this->assertTrue($DB->record_exists("local_mcp_confirm", ["userid" => $other->id]));
        provider::delete_data_for_all_users_in_context(context_system::instance());
        foreach (self::TABLES as $table) {
            $this->assertEquals(0, $DB->count_records($table));
        }
    }

    /**
     * Populate all six personal-data tables with minimally valid records.
     *
     * @param int $userid Moodle user ID.
     * @return void
     */
    private function create_records(int $userid): void {
        global $DB;

        $now = time();
        $clientid = $DB->insert_record("local_mcp_oauth_client", (object)[
            "clientid" => "privacy-test-{$userid}",
            "name" => "Privacy test client",
            "redirecturis" => "[]",
            "enabled" => 1,
            "clienttype" => "public",
            "dynamic" => 0,
            "timecreated" => $now,
            "timemodified" => $now,
        ]);
        $connectionid = $DB->insert_record("local_mcp_connection", (object)[
            "userid" => $userid, "clientid" => $clientid, "scopes" => "mcp:read",
            "enabled" => 1, "timecreated" => $now, "lastip" => "127.0.0.1",
        ]);
        $DB->insert_record("local_mcp_audit", (object)[
            "userid" => $userid, "clientid" => $clientid, "connectionid" => $connectionid,
            "event" => "tool_called", "timecreated" => $now,
        ]);
        $DB->insert_record("local_mcp_auth_code", (object)[
            "prefix" => "auth-{$userid}", "codehash" => hash("sha256", "code-{$userid}"),
            "clientid" => $clientid, "userid" => $userid, "redirecturi" => "https://example.org/callback",
            "scopes" => "mcp:read", "codechallenge" => str_repeat("a", 43),
            "timecreated" => $now, "expires" => $now + 300,
        ]);
        $DB->insert_record("local_mcp_access_token", (object)[
            "prefix" => "access-{$userid}", "tokenhash" => hash("sha256", "access-{$userid}"),
            "userid" => $userid, "clientid" => $clientid, "connectionid" => $connectionid,
            "scopes" => "mcp:read", "family" => "family-{$userid}",
            "issuedat" => $now, "expiresat" => $now + 3600, "lastip" => "127.0.0.1",
        ]);
        $DB->insert_record("local_mcp_refresh_token", (object)[
            "prefix" => "refresh-{$userid}", "tokenhash" => hash("sha256", "refresh-{$userid}"),
            "userid" => $userid, "clientid" => $clientid, "connectionid" => $connectionid,
            "scopes" => "mcp:read", "family" => "family-{$userid}",
            "issuedat" => $now, "expiresat" => $now + 3600,
        ]);
        $DB->insert_record("local_mcp_confirm", (object)[
            "prefix" => "confirm-{$userid}", "tokenhash" => hash("sha256", "confirm-{$userid}"),
            "userid" => $userid, "clientid" => $clientid, "connectionid" => $connectionid,
            "accesshash" => hash("sha256", "access-{$userid}"),
            "tool" => "create_course", "argshash" => hash("sha256", "args-{$userid}"),
            "contextid" => context_system::instance()->id,
            "expires" => $now + 300, "timecreated" => $now,
        ]);
    }
}
