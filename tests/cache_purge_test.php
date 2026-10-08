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
Tests for the cache purging WRITE tools.

 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\exception\api_exception;
use local_mcp\protocol\tool_schema;
use local_mcp\security\authenticated_identity;
use local_mcp\write\registry;
use local_mcp\write\tool\purge_all_caches;
use local_mcp\write\tool\purge_css_cache;

defined('MOODLE_INTERNAL') || die();

/**
 * Validate permissions and previews without purging the PHPUnit environment.
 */
final class cache_purge_test extends advanced_testcase {
    /**
     * @return void
     */
    public function test_tools_are_registered_with_safe_schemas(): void {
        $tools = registry::get_tools();
        $this->assertInstanceOf(purge_css_cache::class, $tools['purge_css_cache']);
        $this->assertInstanceOf(purge_all_caches::class, $tools['purge_all_caches']);
        foreach ([$tools['purge_css_cache'], $tools['purge_all_caches']] as $tool) {
            $this->assertSame('moodle/site:config', $tool->get_required_capability());
            $this->assertTrue($tool->supports_dry_run());
            $this->assertTrue($tool->requires_confirmation());
            $this->assertSame([], $tool->get_input_schema()['required']);
            $schema = tool_schema::normalize($tool->get_input_schema());
            $this->assertSame('{}', json_encode($schema['properties']));
        }
        $this->assertFalse($tools['purge_css_cache']->is_destructive());
        $this->assertTrue($tools['purge_all_caches']->is_destructive());
    }

    /**
     * @return void
     */
    public function test_admin_can_preview_without_purging(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $identity = new authenticated_identity((int)get_admin()->id, 'test', ['mcp:write']);
        $css = (new purge_css_cache())->preview([], $identity);
        $all = (new purge_all_caches())->preview([], $identity);
        $this->assertSame('theme_css', $css['scope']);
        $this->assertSame('all', $all['scope']);
        $this->assertTrue($css['requires_confirmation']);
        $this->assertTrue($all['requires_confirmation']);
    }

    /**
     * @return void
     */
    public function test_non_admin_cannot_purge_caches(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $identity = new authenticated_identity((int)$user->id, 'test', ['mcp:write']);
        $this->expectException(api_exception::class);
        (new purge_all_caches())->preview([], $identity);
    }
}
