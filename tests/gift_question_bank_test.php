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
 * Import a complete GIFT question bank through Moodle's native question APIs.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use context_course;
use context_module;
use local_mcp\exception\api_exception;
use local_mcp\question\gift_import_service;
use local_mcp\security\authenticated_identity;
use local_mcp\write\registry;
use local_mcp\protocol\mcp_server;
use ReflectionMethod;

/**
 * Test native GIFT imports, preview safety, and MCP confirmation.
 *
 * @covers \local_mcp\question\gift_import_service
 * @covers \local_mcp\question\gift_format
 * @covers \local_mcp\write\tool\import_gift_question_bank
 */
final class gift_question_bank_test extends advanced_testcase {
    /**
     * @return array User and course context for an authorized import.
     */
    private function setup_import(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        global $CFG;

        $context = context_course::instance($course->id);
        if ((int)$CFG->version >= 2025041400) {
            // Moodle 5+ question importers only accept module-backed banks.
            $bank = $this->getDataGenerator()->create_module("qbank", ["course" => $course->id]);
            $context = context_module::instance((int)$bank->cmid);
        }
        $identity = new authenticated_identity((int)get_admin()->id, "test", ["mcp:write"]);
        return [$context, $identity];
    }

    /**
     * @return string A GIFT file containing different question types.
     */
    private function gift(): string {
        return "::Q1::What is 2 + 2? {=4 ~3 ~5}\n\n"
            . "::Q2::The Earth is round. {TRUE}\n\n"
            . "::Q3::What is the capital of France? {=Paris}\n";
    }

    /**
     * Preview must not create any question or category.
     */
    public function test_preview_is_read_only_and_import_creates_all_questions(): void {
        global $DB;

        [$ctx, $identity] = $this->setup_import();
        $args = ["contextid" => $ctx->id, "name" => "GIFT Test Category",
            "idnumber" => "GIFT-TEST-001", "gift" => $this->gift()];
        $tool = registry::get_tools()["import_gift_question_bank"];

        $preview = $tool->preview($args, $identity);
        $this->assertSame(3, $preview["question_count"]);
        $this->assertFalse($DB->record_exists("question_categories",
            ["name" => "GIFT Test Category", "contextid" => $ctx->id]));

        $result = $tool->execute($args, $identity);
        $this->assertSame(3, $result["question_count"]);
        $this->assertCount(3, array_unique($result["question_ids"]));
        $this->assertSame("GIFT-TEST-001", $DB->get_field("question_categories",
            "idnumber", ["id" => $result["categoryid"]]));
        $types = $DB->get_records_sql("SELECT q.id, q.qtype
            FROM {question} q
            JOIN {question_versions} v ON v.questionid = q.id
            JOIN {question_bank_entries} e ON e.id = v.questionbankentryid
            WHERE e.questioncategoryid = :categoryid", ["categoryid" => $result["categoryid"]]);
        $this->assertCount(3, $types);
        $this->assertEqualsCanonicalizing(["multichoice", "truefalse", "shortanswer"],
            array_values(array_map(static fn($q) => $q->qtype, $types)));

        // A retry with the same target must not silently duplicate the bank.
        $this->expectException(api_exception::class);
        gift_import_service::inspect($args, $identity);
    }

    /**
     * Reject category directives that could redirect questions outside the approved target.
     */
    public function test_embedded_category_directives_are_rejected_without_writes(): void {
        global $DB;

        [$ctx, $identity] = $this->setup_import();
        $args = ["contextid" => $ctx->id, "name" => "Do Not Create",
            "gift" => "\$CATEGORY: elsewhere\n\n::Q1::A? {=Yes ~No}\n"];
        $this->expectException(api_exception::class);
        try {
            gift_import_service::execute($args, $identity);
        } finally {
            $this->assertFalse($DB->record_exists("question_categories",
                ["name" => "Do Not Create", "contextid" => $ctx->id]));
        }
    }

    /**
     * One GIFT payload is approved once, then imported as a single batch.
     */
    public function test_mcp_requires_confirmation_for_entire_batch(): void {
        global $DB;

        [$ctx, $identity] = $this->setup_import();
        $args = ["contextid" => $ctx->id, "name" => "Approved Import",
            "gift" => $this->gift()];
        $server = new mcp_server("write");
        $method = new ReflectionMethod(mcp_server::class, "call_tool");
        $pending = $method->invoke($server, "import_gift_question_bank", $args,
            [], $identity, "test-access-token");
        $this->assertSame("confirmation_pending", $pending["status"]);
        $this->assertSame(3, $pending["preview"]["question_count"]);
        $this->assertFalse($DB->record_exists("question_categories",
            ["name" => "Approved Import", "contextid" => $ctx->id]));
        $confirmed = $method->invoke($server, "import_gift_question_bank",
            $args + ["confirmation_token" => $pending["confirmation_token"]],
            [], $identity, "test-access-token");
        $this->assertSame(3, $confirmed["question_count"]);
        $this->assertTrue($DB->record_exists("question_categories",
            ["id" => $confirmed["categoryid"]]));
    }

    /**
     * Verify input and registry schema before any import.
     */
    public function test_tool_schema_and_empty_gift(): void {
        [$ctx, $identity] = $this->setup_import();
        $tool = registry::get_tools()["import_gift_question_bank"];
        $this->assertSame("moodle/question:add", $tool->get_required_capability());
        $this->assertTrue($tool->supports_dry_run());
        $this->assertTrue($tool->requires_confirmation());
        $this->assertContains("gift", $tool->get_input_schema()["required"]);
        $this->expectException(api_exception::class);
        $tool->preview(["contextid" => $ctx->id, "name" => "Empty",
            "gift" => ""], $identity);
    }
}
