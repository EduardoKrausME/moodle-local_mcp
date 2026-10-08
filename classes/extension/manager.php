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
 * manager.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\extension;

use coding_exception;

/**
 * Class manager.
 */
final class manager {
    /** @return read_provider_interface[] */
    public static function read_providers(): array {
        return array_merge(self::providers('mcp_read_provider', read_provider_interface::class),
            self::subplugin_providers(read_provider_interface::class));
    }

    /** @return write_provider_interface[] */
    public static function write_providers(): array {
        return array_merge(self::providers('mcp_write_provider', write_provider_interface::class),
            self::subplugin_providers(write_provider_interface::class));
    }

    /**
     * Method providers.
     *
     * @param string $callback Parameter callback.
     * @param string $interface Parameter interface.
     * @return array Return value.
     */
    private static function providers(string $callback, string $interface): array {
        $providers = [];
        $callbacks = get_plugins_with_function($callback, 'lib.php');
        foreach ($callbacks as $component => $functions) {
            foreach ($functions as $function) {
                $provider = $function();
                if (!$provider instanceof $interface) {
                    throw new coding_exception($component . ' returned an invalid MCP provider.');
                }
                $providers[] = $provider;
            }
        }
        return $providers;
    }

    /**
     * Discover registered activity subplugins through Moodle's plugin component registry.
     *
     * @param string $interface Provider contract.
     * @return array Provider objects implementing the requested interface.
     */
    private static function subplugin_providers(string $interface): array {
        $providers = [];
        foreach (\core_component::get_plugin_list('mcptool') as $name => $directory) {
            $component = 'mcptool_' . $name;
            $info = \core_plugin_manager::instance()->get_plugin_info($component);
            if ($info && $info->is_enabled() === false) {
                continue;
            }
            $class = '\\' . $component . '\\provider';
            if (!class_exists($class)) {
                throw new coding_exception($component . ' does not define a provider class.');
            }
            $provider = new $class();
            if (!$provider instanceof read_provider_interface || !$provider instanceof write_provider_interface) {
                throw new coding_exception($component . ' must implement both MCP provider contracts.');
            }
            if ($provider instanceof $interface) {
                $providers[] = $provider;
            }
        }
        return $providers;
    }
}
