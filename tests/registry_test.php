<?php
namespace local_mcp;

defined('MOODLE_INTERNAL') || die;

final class registry_test extends \advanced_testcase {
    public function test_read_and_write_registries_are_structurally_separate(): void {
        $read = \local_mcp\read\registry::get_tools();
        $write = \local_mcp\write\registry::get_tools();

        $this->assertNotEmpty($read);
        $this->assertNotEmpty($write);
        $this->assertEmpty(array_intersect(array_keys($read), array_keys($write)));

        foreach ($read as $tool) {
            $this->assertInstanceOf(\local_mcp\read\tool_interface::class, $tool);
            $this->assertNotInstanceOf(\local_mcp\write\tool_interface::class, $tool);
        }
        foreach ($write as $tool) {
            $this->assertInstanceOf(\local_mcp\write\tool_interface::class, $tool);
            $this->assertNotInstanceOf(\local_mcp\read\tool_interface::class, $tool);
        }
    }

    public function test_expected_scopes_are_independent(): void {
        $read = new \local_mcp\security\authenticated_identity(2, 'test', ['mcp:read']);
        $write = new \local_mcp\security\authenticated_identity(2, 'test', ['mcp:write']);
        $this->assertTrue($read->has_scope('mcp:read'));
        $this->assertFalse($read->has_scope('mcp:write'));
        $this->assertTrue($write->has_scope('mcp:write'));
        $this->assertFalse($write->has_scope('mcp:read'));
    }
}
