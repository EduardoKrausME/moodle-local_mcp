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
use core_component;
use core_plugin_manager;

/**
 * Class manager.
 */
final class manager {
    /** @var array<string, object>|null Providers discovered by convention for this request. */
    private static ?array $classproviders = null;

    /**
     * read_providers
     *
     * @return array
     * @throws coding_exception
     */
    public static function read_providers(): array {
        return array_merge(
            self::tool_callbacks('mcp_read_provider', read_provider_interface::class),
            self::class_providers(read_provider_interface::class),
            self::subplugin_providers(read_provider_interface::class)
        );
    }

    /**
     * write_providers
     *
     * @return array
     * @throws coding_exception
     */
    public static function write_providers(): array {
        return array_merge(
            self::tool_callbacks('mcp_write_provider', write_provider_interface::class),
            self::class_providers(write_provider_interface::class),
            self::subplugin_providers(write_provider_interface::class)
        );
    }

    /**
     * Optional policy providers, discovered through Moodle's plugin callback API.
     * No policy provider may grant privileges absent from native Moodle checks.
     *
     * @return access_policy_interface[]
     */
    public static function access_policies(): array {
        return self::providers('mcp_access_policy', access_policy_interface::class);
    }

    /**
     * Automatically discover providers in any installed Moodle plugin:
     * /<plugintype>/<name>/classes/mcp/provider.php
     *
     * Class name is \<component>\mcp\provider. A provider may implement
     * READ, WRITE or both contracts. No lib.php or subplugin is necessary.
     *
     * @param string $interface Desired provider interface.
     * @return array Providers implementing the requested contract.
     */
    private static function class_providers(string $interface): array {
        if (self::$classproviders === null) {
            self::$classproviders = [];
            foreach (core_component::get_plugin_types() as $type => $directory) {
                // MCP subplugins are handled by the existing dedicated registry.
                if ($type === 'mcptool') {
                    continue;
                }
                foreach (core_component::get_plugin_list_with_file(
                    $type, 'classes/mcp/provider.php'
                ) as $name => $file) {
                    $component = $type . '_' . $name;
                    if ($component === 'local_mcp' || !self::plugin_enabled($component)) {
                        continue;
                    }
                    $class = '\\' . $component . '\\mcp\\provider';
                    $provider = tool_provider_loader::load_class($class, $component);
                    if ($provider !== null) {
                        self::$classproviders[$component] = $provider;
                    }
                }
            }
        }

        $providers = [];
        foreach (self::$classproviders as $provider) {
            if ($provider instanceof $interface) {
                $providers[] = $provider;
            }
        }
        return $providers;
    }

    /**
     * Ignore plugins disabled through Moodle's plugin manager.
     *
     * @param string $component Frankenstyle plugin component.
     * @return bool
     */
    private static function plugin_enabled(string $component): bool {
        $info = core_plugin_manager::instance()->get_plugin_info($component);
        return $info && $info->is_enabled() !== false;
    }

    /**
     * Discover tool callbacks separately from mandatory access policies.
     *
     * @param string $callback Callback name.
     * @param string $interface Required interface.
     * @return array
     */
    private static function tool_callbacks(string $callback, string $interface): array {
        $providers = [];
        foreach (get_plugins_with_function($callback, 'lib.php') as $component => $functions) {
            foreach ($functions as $function) {
                $provider = tool_provider_loader::load(static function() use ($function) {
                    return $function();
                }, $interface, $component, (string)$function);
                if ($provider !== null) {
                    $providers[] = $provider;
                }
            }
        }
        return $providers;
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
        foreach (core_component::get_plugin_list('mcptool') as $name => $directory) {
            $component = 'mcptool_' . $name;
            if (!self::plugin_enabled($component)) {
                continue;
            }
            $class = '\\' . $component . '\\provider';
            $provider = tool_provider_loader::load_class($class, $component);
            if ($provider instanceof $interface) {
                $providers[] = $provider;
            }
        }
        return $providers;
    }
}
