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
 * Test persistent single-step Moodle operations.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use context_course;
use local_mcp\exception\api_exception;
use local_mcp\protocol\discovery;
use local_mcp\protocol\mcp_server;
use local_mcp\security\authenticated_identity;
use local_mcp\workflow\sequence;
use ReflectionMethod;

/**
 * End-to-end state transitions without actually creating course content.
 *
 * @covers \local_mcp\workflow\sequence
 * @covers \local_mcp\write\tool\workflow_sequence
 */
final class workflow_sequence_test extends advanced_testcase {
    /**
     * Each completed WRITE must be explicitly advanced before the next one.
     *
     * @return void
     */
    public function test_workflow_progress_requires_one_completed_step(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $identity = new authenticated_identity(2, "test", ["mcp:write"]);
        $items = [
            ["title" => "First section", "tool" => "create_section"],
            ["title" => "Second section", "tool" => "create_section"],
        ];
        $step = sequence::start($identity, (int)$course->id, $items);
        $this->assertSame("ready", $step["state"]);
        $this->assertSame(1, $step["step"]);
        $this->assertNull($step["next"]);

        $context = context_course::instance($course->id);
        sequence::assert_current($step["id"], $identity, 1, "create_section", $context);
        sequence::claim($step["id"], $identity, 1, "create_section", $context);
        $saved = sequence::complete($step["id"], $identity, (int)$course->id, ["id" => 77]);
        $this->assertSame("done", $saved["state"]);
        $this->assertSame(2, $saved["next"]["step"]);
        $this->assertSame(77, $saved["completed"][0]["result_id"]);

        $next = sequence::next($step["id"], $identity, (int)$course->id);
        $this->assertSame(2, $next["step"]);
        $this->assertSame("ready", $next["state"]);
        $repeated = sequence::next($step["id"], $identity, (int)$course->id);
        $this->assertSame(2, $repeated["step"]);
        sequence::claim($step["id"], $identity, 2, "create_section", $context);
        $final = sequence::complete($step["id"], $identity, (int)$course->id, ["id" => 88]);
        $this->assertSame("done", $final["state"]);
        $this->assertNull($final["next"]);
    }

    /**
     * Connection identity and stage are checked server-side, not by the model.
     *
     * @return void
     */
    public function test_invalid_connection_and_early_step_are_rejected(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $identity = new authenticated_identity(2, "oauth", ["mcp:write"], 101, 102);
        $step = sequence::start($identity, (int)$course->id, [
            ["title" => "One", "tool" => "create_section"],
            ["title" => "Two", "tool" => "create_section"],
        ]);
        $other = new authenticated_identity(2, "oauth", ["mcp:write"], 101, 103);
        try {
            sequence::status($step["id"], $other, (int)$course->id);
            $this->fail("Other connections must not see workflow data.");
        } catch (api_exception $e) {
            $this->assertSame("workflow_not_found", $e->machinecode);
        }
        try {
            sequence::assert_current($step["id"], $identity, 2, "create_section",
                context_course::instance($course->id));
            $this->fail("A future step must not execute.");
        } catch (api_exception $e) {
            $this->assertSame("workflow_step_mismatch", $e->machinecode);
        }
    }

    /**
     * MCP Apps linkage must be discoverable without breaking text-only clients.
     *
     * @return void
     */
    public function test_mcp_tool_discovery_links_workflow_ui(): void {
        $this->resetAfterTest();
        $identity = new authenticated_identity(2, "test", ["mcp:write"]);
        $method = new ReflectionMethod(mcp_server::class, "tool_definitions");
        $tools = $method->invoke(new mcp_server("write"), $identity);
        $indexed = [];
        foreach ($tools as $tool) {
            $indexed[$tool["name"]] = $tool;
        }
        $this->assertArrayHasKey("workflow_sequence", $indexed);
        $this->assertSame("ui://local-mcp/workflow-next",
            $indexed["create_section"]["_meta"]["ui"]["resourceUri"]);
        $this->assertArrayHasKey("workflow_id", $indexed["create_section"]["inputSchema"]["properties"]);
        $this->assertArrayNotHasKey("workflow_step",
            $indexed["workflow_sequence"]["inputSchema"]["properties"]);
        $this->assertArrayHasKey("resources", discovery::capabilities());
    }
}
