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
 * Regression coverage for MCP tool argument validation.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\exception\api_exception;
use local_mcp\protocol\mcp_server;
use local_mcp\protocol\tool_input_validator;
use local_mcp\security\authenticated_identity;
use ReflectionMethod;

/**
 * Validate actual JSON types before Moodle context or write operations.
 *
 * @covers \local_mcp\protocol\tool_input_validator
 * @covers \local_mcp\protocol\mcp_server
 */
final class tool_input_validator_test extends advanced_testcase {
    /**
     * Assert that an argument value is rejected at the expected path.
     *
     * @param array $schema JSON Schema.
     * @param mixed $arguments Raw JSON arguments.
     * @param string $path Expected argument path.
     * @return void
     */
    private function assert_invalid(array $schema, mixed $arguments, string $path): void {
        try {
            tool_input_validator::validate($schema, $arguments);
            $this->fail("Expected invalid_params at " . $path);
        } catch (api_exception $e) {
            $this->assertSame("invalid_params", $e->machinecode);
            $this->assertSame(400, $e->httpstatus);
            $this->assertSame($path, $e->details["path"]);
        }
    }

    /**
     * Validate required properties, exact types, enums, limits and nested inputs.
     *
     * @return void
     */
    public function test_nested_schema_constraints(): void {
        $schema = [
            "type" => "object",
            "required" => ["userid", "courses"],
            "properties" => [
                "userid" => ["type" => "integer", "minimum" => 1],
                "courses" => [
                    "type" => "array",
                    "minItems" => 1,
                    "maxItems" => 2,
                    "items" => [
                        "type" => "object",
                        "required" => ["name", "status"],
                        "properties" => [
                            "name" => ["type" => "string", "minLength" => 1, "maxLength" => 5],
                            "status" => ["type" => "string", "enum" => ["ready", "draft"]],
                        ],
                        "additionalProperties" => false,
                    ],
                ],
            ],
            "additionalProperties" => false,
        ];
        $valid = json_decode('{"userid":7,"courses":[{"name":"Course","status":"ready"}]}');
        tool_input_validator::validate($schema, $valid);
        $this->assert_invalid($schema, json_decode('{"userid":"7","courses":[{"name":"Hi","status":"ready"}]}'),
            "arguments.userid");
        $this->assert_invalid($schema, json_decode('{"courses":[{"name":"Hi","status":"ready"}]}'),
            "arguments.userid");
        $this->assert_invalid($schema, json_decode('{"userid":0,"courses":[{"name":"Hi","status":"ready"}]}'),
            "arguments.userid");
        $this->assert_invalid($schema, json_decode('{"userid":7,"courses":[]}'), "arguments.courses");
        $this->assert_invalid($schema, json_decode('{"userid":7,"courses":[{"name":"Hi","status":"closed"}]}'),
            "arguments.courses[0].status");
        $this->assert_invalid($schema, json_decode('{"userid":7,"courses":[{"name":"","status":"ready"}]}'),
            "arguments.courses[0].name");
        $this->assert_invalid($schema, json_decode('{"userid":7,"courses":[{"name":"Too long","status":"ready"}]}'),
            "arguments.courses[0].name");
        $this->assert_invalid($schema, json_decode('{"userid":7,"courses":[{"name":"Hi","status":"ready","extra":true}]}'),
            "arguments.courses[0].extra");
        $this->assert_invalid($schema, json_decode('{"userid":7,"courses":[{"name":"Hi","status":"ready"}],"extra":true}'),
            "arguments.extra");
        $this->assert_invalid($schema, json_decode('{"userid":7,"courses":[{"name":"Hi","status":"ready"},{"name":"Hi","status":"draft"},{"name":"Hi","status":"ready"}]}'),
            "arguments.courses");
    }

    /**
     * Empty JSON objects are not JSON arrays, even though PHP's associative decoder loses this.
     *
     * @return void
     */
    public function test_preserves_empty_json_container_types(): void {
        $object = ["type" => "object", "properties" => [], "additionalProperties" => false];
        $array = ["type" => "array", "items" => ["type" => "integer"]];
        tool_input_validator::validate($object, json_decode("{}"));
        tool_input_validator::validate($array, json_decode("[]"));
        $this->assert_invalid($object, json_decode("[]"), "arguments");
        $this->assert_invalid($array, json_decode("{}"), "arguments");
        $this->assert_invalid($object, json_decode("null"), "arguments");
    }

    /**
     * Boolean and numeric values are never silently coerced.
     *
     * @return void
     */
    public function test_numeric_boolean_and_array_constraints(): void {
        $schema = [
            "type" => "object",
            "properties" => [
                "dry_run" => ["type" => "boolean"],
                "score" => ["type" => "number", "exclusiveMinimum" => 0, "maximum" => 10],
                "ids" => ["type" => "array", "uniqueItems" => true,
                    "items" => ["type" => "integer", "minimum" => 1]],
            ],
            "additionalProperties" => false,
        ];
        tool_input_validator::validate($schema, json_decode('{"dry_run":false,"score":0.25,"ids":[1,2]}'));
        $this->assert_invalid($schema, json_decode('{"dry_run":"false"}'), "arguments.dry_run");
        $this->assert_invalid($schema, json_decode('{"score":0}'), "arguments.score");
        $this->assert_invalid($schema, json_decode('{"score":11}'), "arguments.score");
        $this->assert_invalid($schema, json_decode('{"ids":[1,"2"]}'), "arguments.ids[1]");
        $this->assert_invalid($schema, json_decode('{"ids":[1,1]}'), "arguments.ids");
    }

    /**
     * Invalid get_user arguments fail before resolve_context casts the userid.
     *
     * @return void
     */
    public function test_read_tool_rejects_invalid_userid_before_context_resolution(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $server = new mcp_server("read");
        $method = new ReflectionMethod(mcp_server::class, "call_tool");
        $identity = new authenticated_identity((int)get_admin()->id, "test", ["mcp:read"]);
        try {
            $method->invoke($server, "get_user", ["userid" => "not-an-integer"],
                [], $identity, "test-access-token");
            $this->fail("get_user must reject a string userid before resolving context.");
        } catch (api_exception $e) {
            $this->assertSame("invalid_params", $e->machinecode);
            $this->assertSame("arguments.userid", $e->details["path"]);
        }
    }

    /**
     * Use the original decoded JSON for validation rather than its cast PHP value.
     *
     * @return void
     */
    public function test_read_tool_rejects_raw_json_array_and_string_id(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $server = new mcp_server("read");
        $method = new ReflectionMethod(mcp_server::class, "call_tool");
        $identity = new authenticated_identity((int)get_admin()->id, "test", ["mcp:read"]);
        foreach ([[], (object)["userid" => "1"]] as $raw) {
            try {
                $method->invoke($server, "get_user", ["userid" => 1], [],
                    $identity, "test-access-token", $raw);
                $this->fail("Original invalid JSON argument types must not pass validation.");
            } catch (api_exception $e) {
                $this->assertSame("invalid_params", $e->machinecode);
            }
        }
    }

    /**
     * A malformed WRITE request must not create a confirmation or category.
     *
     * @return void
     */
    public function test_write_rejects_invalid_arguments_before_preview(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $server = new mcp_server("write");
        $method = new ReflectionMethod(mcp_server::class, "call_tool");
        $identity = new authenticated_identity((int)get_admin()->id, "test", ["mcp:write"]);
        $name = "Schema Rejection Test";
        try {
            $method->invoke($server, "create_category",
                ["name" => $name, "parentid" => "0"], [], $identity, "test-access-token");
            $this->fail("A string parentid must not reach the write preview.");
        } catch (api_exception $e) {
            $this->assertSame("invalid_params", $e->machinecode);
            $this->assertSame("arguments.parentid", $e->details["path"]);
        }
        $this->assertFalse($DB->record_exists("course_categories", ["name" => $name]));
    }
}
