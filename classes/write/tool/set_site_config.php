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

namespace local_mcp\write\tool;

use context;
use context_system;
use local_mcp\audit\logger;
use local_mcp\config\site_config;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\security\capability_guard;

/**
 * Confirmed, restricted core mdl_config editing using Moodle set_config().
 */
final class set_site_config extends base_tool {
    public function get_name(): string {
        return 'set_site_config';
    }

    public function get_title(): string {
        return 'Update Moodle site configuration';
    }

    public function get_description(): string {
        return 'Change one or more approved core Moodle mdl_config settings with set_config(), '
            . 'after displaying the old/new values and obtaining explicit confirmation. '
            . 'Values must be strings (for example 0, 1 or 32767). To replace Dashboard with '
            . 'My courses send enabledashboard=0, enablemycourses=1 and defaulthomepage=3 in one batch. '
            . 'Secrets, arbitrary keys and plugin configurations are not supported.';
    }

    public function get_required_capability(): string {
        return 'moodle/site:config';
    }

    public function get_input_schema(): array {
        return $this->object_schema([
            'settings' => [
                'type' => 'array',
                'minItems' => 1,
                'maxItems' => 20,
                'description' => 'Atomic batch of approved core site settings.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'enum' => site_config::names()],
                        'value' => [
                            'type' => 'string',
                            'description' => 'Value, encoded as string. Booleans: 0/1; developer debugging: 32767.',
                        ],
                    ],
                    'required' => ['name', 'value'],
                    'additionalProperties' => false,
                ],
            ],
        ], ['settings']);
    }

    public function resolve_context(array $arguments): context {
        return context_system::instance();
    }

    public function supports_dry_run(): bool {
        return true;
    }

    /**
     * Only actual administrators can change these settings, never a delegated manager.
     *
     * @param authenticated_identity $identity Connected Moodle identity.
     * @return void
     */
    private function assert_admin(authenticated_identity $identity): void {
        capability_guard::check($this->get_required_capability(), context_system::instance(), $identity->userid);
        if (!is_siteadmin($identity->userid)) {
            throw new api_exception('permission_denied', 403);
        }
    }

    /**
     * Summarize potential impact without logging secrets or performing writes.
     *
     * @param array $settings Normalized settings.
     * @return string[]
     */
    private static function warnings(array $settings): array {
        $warnings = [];
        if (($settings['debug'] ?? null) === '32767') {
            $warnings[] = 'Developer debugging may expose internal information and reduce performance.';
        }
        if (($settings['debugdisplay'] ?? null) === '1') {
            $warnings[] = 'Showing debugging messages on pages can expose error details to users.';
        }
        if (($settings['themedesignermode'] ?? null) === '1') {
            $warnings[] = 'Theme designer mode disables theme caching and can heavily reduce performance.';
        }
        if (($settings['cachejs'] ?? null) === '0') {
            $warnings[] = 'Disabling JavaScript caching can slow every page load.';
        }
        if (($settings['enabledashboard'] ?? null) === '0') {
            $warnings[] = 'Dashboard will disappear from the navigation for all users.';
        }
        return $warnings;
    }

    public function preview(array $arguments, authenticated_identity $identity): array {
        $this->assert_admin($identity);
        $settings = site_config::validate_batch($arguments['settings'] ?? []);
        $changes = [];
        foreach ($settings as $name => $value) {
            $current = site_config::current($name);
            $changes[] = [
                'name' => $name,
                'old_value' => $current['stored'],
                'effective_value' => $current['effective'],
                'new_value' => $value,
                'will_change' => $current['stored'] !== $value,
            ];
        }
        return [
            'changes' => $changes,
            'warnings' => self::warnings($settings),
            'requires_confirmation' => true,
        ];
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        global $DB;
        $this->assert_admin($identity);
        // The confirmation is checked by mcp_server before this method is reached.
        // Revalidate everything before the first mutation, including on the second call.
        $settings = site_config::validate_batch($arguments['settings'] ?? []);
        $before = [];
        foreach ($settings as $name => $value) {
            $before[$name] = site_config::current($name)['stored'];
        }
        $transaction = $DB->start_delegated_transaction();
        $changed = [];
        foreach ($settings as $name => $value) {
            if ($before[$name] !== $value) {
                if (!set_config($name, $value, null, true)) {
                    throw new api_exception('config_write_failed', 500, 'Moodle could not save a configuration setting.');
                }
                $changed[] = $name;
            }
        }
        $transaction->allow_commit();

        // Theme settings need the same cache invalidation as the administration UI.
        if (in_array('themedesignermode', $changed, true)) {
            theme_reset_all_caches();
        }

        // Dedicated audit entry contains setting names only, never values.
        logger::record('site_config_changed', $identity, $this->get_name(), 'write',
            context_system::instance(), ['names' => $changed], false, false, true, 'ok');
        $after = [];
        foreach ($settings as $name => $value) {
            $after[] = site_config::current($name);
        }
        return [
            'success' => true,
            'changed' => $changed,
            'settings' => $after,
            'warnings' => self::warnings($settings),
        ];
    }
}
