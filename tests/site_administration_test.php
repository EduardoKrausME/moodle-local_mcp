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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.
//
// @package   local_mcp
// @copyright 2026 Eduardo Kraus
// @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_mcp;

use advanced_testcase;
use local_mcp\config\site_config;
use local_mcp\exception\api_exception;
use local_mcp\read\tool\get_site_config;
use local_mcp\read\tool\get_site_status;
use local_mcp\security\authenticated_identity;
use local_mcp\write\tool\set_site_config;

defined('MOODLE_INTERNAL') || die();

/**
 * Site configuration and Check API integration.
 */
final class site_administration_test extends advanced_testcase {
    public function test_tools_are_published_in_separate_registries(): void {
        $this->assertArrayHasKey('get_site_config', read\registry::get_tools());
        $this->assertArrayHasKey('get_site_status', read\registry::get_tools());
        $this->assertArrayHasKey('set_site_config', write\registry::get_tools());
        $this->assertTrue((new set_site_config())->requires_confirmation());
        $this->assertTrue((new set_site_config())->supports_dry_run());
        $this->assertSame('moodle/site:config', (new get_site_status())->get_required_capability());
    }

    public function test_settings_are_strictly_validated(): void {
        $this->assertSame('1', site_config::normalize('themedesignermode', 'true'));
        $this->assertSame('0', site_config::normalize('enabledashboard', 'false'));
        $this->assertSame('32767', site_config::normalize('debug', '32767'));
        $this->expectException(api_exception::class);
        site_config::normalize('auth', 'email');
    }

    public function test_invalid_batch_never_changes_existing_setting(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('themedesignermode', 0);
        $identity = new authenticated_identity((int)get_admin()->id, 'test', ['mcp:write']);
        try {
            (new set_site_config())->execute(['settings' => [
                ['name' => 'themedesignermode', 'value' => '1'],
                ['name' => 'passwordsaltmain', 'value' => 'not-allowed'],
            ]], $identity);
            $this->fail('Unsupported configuration must be rejected.');
        } catch (api_exception $e) {
            $this->assertSame('0', (string)get_config('core', 'themedesignermode'));
        }
    }

    public function test_preview_is_safe_and_batch_changes_are_applied(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabledashboard', 1);
        set_config('enablemycourses', 0);
        set_config('defaulthomepage', 1);
        $identity = new authenticated_identity((int)get_admin()->id, 'test', ['mcp:write']);
        $args = ['settings' => [
            ['name' => 'enabledashboard', 'value' => '0'],
            ['name' => 'enablemycourses', 'value' => '1'],
            ['name' => 'defaulthomepage', 'value' => '3'],
        ]];
        $tool = new set_site_config();
        $preview = $tool->preview($args, $identity);
        $this->assertCount(3, $preview['changes']);
        $this->assertSame('1', site_config::current('enabledashboard')['stored']);
        $result = $tool->execute($args, $identity);
        $this->assertTrue($result['success']);
        $this->assertSame('0', site_config::current('enabledashboard')['stored']);
        $this->assertSame('1', site_config::current('enablemycourses')['stored']);
        $this->assertSame('3', site_config::current('defaulthomepage')['stored']);
    }

    public function test_only_site_admin_can_read_or_edit_settings(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $identity = new authenticated_identity((int)$user->id, 'test', ['mcp:read', 'mcp:write']);
        $this->expectException(api_exception::class);
        (new get_site_config())->execute(['names' => ['themedesignermode']], $identity);
    }

    public function test_performance_report_uses_core_checks(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $identity = new authenticated_identity((int)get_admin()->id, 'test', ['mcp:read']);
        $report = (new get_site_status())->execute([
            'type' => 'performance',
            'checkref' => 'core_designermode',
        ], $identity);
        $this->assertSame('performance', $report['type']);
        $this->assertCount(1, $report['checks']);
        $this->assertSame('core_designermode', $report['checks'][0]['ref']);
    }
}
