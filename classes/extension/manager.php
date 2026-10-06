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

defined('MOODLE_INTERNAL') || die;

/**
 * Class manager.
 */
final class manager {
    /** @return read_provider_interface[] */
    public static function read_providers(): array {
        return self::providers('mcp_read_provider', read_provider_interface::class);
    }

    /** @return write_provider_interface[] */
    public static function write_providers(): array {
        return self::providers('mcp_write_provider', write_provider_interface::class);
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
                    throw new \coding_exception($component . ' returned an invalid MCP provider.');
                }
                $providers[] = $provider;
            }
        }
        return $providers;
    }
}
