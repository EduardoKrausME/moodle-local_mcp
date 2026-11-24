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
 * Failure isolation for tool extensions.
 *
 * @package local_mcp
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\extension;

use coding_exception;
use local_mcp\diagnostics;
use Throwable;

/**
 * Tool provider recovery boundary. Never use this to load access policies.
 */
final class tool_provider_loader {
    /**
     * Obtain a tool provider from a callback or class factory.
     *
     * @param callable $factory Provider factory.
     * @param string $interface Expected provider interface or empty for either tool interface.
     * @param string $component Moodle component.
     * @param string $source Class or callback name.
     * @return object|null
     */
    public static function load(callable $factory, string $interface, string $component, string $source): ?object {
        try {
            $provider = $factory();
            $valid = $interface !== '' ? $provider instanceof $interface :
                ($provider instanceof read_provider_interface || $provider instanceof write_provider_interface);
            if (!$valid) {
                throw new coding_exception('Invalid MCP tool provider interface.');
            }
            return $provider;
        } catch (Throwable $failure) {
            self::report($component, $source, 'provider_load', $failure);
            return null;
        }
    }

    /**
     * Load a conventional provider class.
     *
     * @param string $class Provider class.
     * @param string $component Moodle component.
     * @return object|null
     */
    public static function load_class(string $class, string $component): ?object {
        return self::load(static function() use ($class) {
            if (!class_exists($class)) {
                throw new coding_exception('MCP provider class cannot be loaded.');
            }
            return new $class();
        }, '', $component, $class);
    }

    /**
     * Instantiate built-in tools individually so an incomplete deployment cannot disable discovery.
     *
     * @param array $classes Built-in tool class names, whose short names are the public tool names.
     * @param string $interface Required tool interface.
     * @param array $conflicts Names excluded from both registries.
     * @return array
     */
    public static function collect_builtins(array $classes, string $interface, array &$conflicts): array {
        $indexed = [];
        foreach ($classes as $class) {
            $parts = explode('\\', $class);
            $expected = end($parts);
            try {
                if (!class_exists($class)) {
                    throw new coding_exception('MCP built-in tool class cannot be loaded.');
                }
                $tool = new $class();
                if (!$tool instanceof $interface ||
                        ($tool instanceof \local_mcp\read\tool_interface &&
                        $tool instanceof \local_mcp\write\tool_interface)) {
                    throw new coding_exception('Invalid MCP built-in tool interface.');
                }
                $name = $tool->get_name();
                if ($name !== $expected) {
                    throw new coding_exception('Unexpected MCP built-in tool name.');
                }
                if (isset($indexed[$name]) || isset($conflicts[$name])) {
                    throw new coding_exception('Duplicate MCP built-in tool name.');
                }
                $indexed[$name] = $tool;
            } catch (Throwable $failure) {
                // Do not allow an extension to take over an unavailable built-in name.
                unset($indexed[$expected]);
                $conflicts[$expected] = true;
                self::report('local_mcp', $class, 'builtin_registration', $failure, $expected);
            }
        }
        return $indexed;
    }

    /**
     * Add valid extension tools and quarantine all occurrences of duplicate names.
     *
     * @param array $indexed Indexed built-in tools.
     * @param array $providers Providers for one registry.
     * @param string $method Provider enumeration method.
     * @param string $interface Required tool interface.
     * @param array $conflicts Ambiguous names indexed by name.
     * @return array
     */
    public static function collect(array $indexed, array $providers, string $method,
                                   string $interface, array &$conflicts): array {
        foreach ($providers as $provider) {
            $class = get_class($provider);
            $component = explode('\\', ltrim($class, '\\'))[0];
            try {
                $tools = $provider->$method();
            } catch (Throwable $failure) {
                self::report($component, $class, 'provider_enumeration', $failure);
                continue;
            }
            foreach ($tools as $tool) {
                try {
                    if (!$tool instanceof $interface ||
                            ($tool instanceof \local_mcp\read\tool_interface &&
                            $tool instanceof \local_mcp\write\tool_interface)) {
                        throw new coding_exception('Invalid MCP tool interface.');
                    }
                    $name = $tool->get_name();
                    if ($name === '') {
                        throw new coding_exception('Empty MCP tool name.');
                    }
                    if (isset($indexed[$name]) || isset($conflicts[$name])) {
                        unset($indexed[$name]);
                        $conflicts[$name] = true;
                        diagnostics::event('tool_name_conflict', [
                            'component' => $component, 'provider' => $class, 'tool' => $name,
                            'phase' => 'provider_enumeration', 'resolution' => 'excluded',
                        ], 'ERROR');
                        continue;
                    }
                    $indexed[$name] = $tool;
                } catch (Throwable $failure) {
                    self::report($component, $class, 'tool_registration', $failure);
                }
            }
        }
        return $indexed;
    }

    /**
     * Combine both sides without exposing an ambiguous name.
     *
     * @param array $read READ tools.
     * @param array $write WRITE tools.
     * @param array $conflicts Names already blocked within either side.
     * @return array
     */
    public static function merge(array $read, array $write, array $conflicts): array {
        $conflicts += array_intersect_key($read, $write);
        foreach (array_keys($conflicts) as $name) {
            unset($read[$name], $write[$name]);
            diagnostics::event('tool_name_conflict', [
                'tool' => $name, 'phase' => 'registry_merge', 'resolution' => 'excluded',
            ], 'ERROR');
        }
        return array_merge($read, $write);
    }

    /**
     * Log structural failure metadata without arbitrary exception messages.
     *
     * @param string $component Moodle component.
     * @param string $provider Provider class or callback.
     * @param string $phase Discovery stage.
     * @param Throwable $failure Original failure.
     * @param string $tool Tool name if known.
     * @return void
     */
    public static function report(string $component, string $provider, string $phase,
                                  Throwable $failure, string $tool = ''): void {
        diagnostics::event('tool_provider_failed', [
            'component' => $component, 'provider' => $provider, 'phase' => $phase, 'tool' => $tool,
            'error_class' => get_class($failure), 'file' => basename($failure->getFile()),
            'line' => $failure->getLine(), 'resolution' => 'excluded',
        ], 'ERROR');
    }
}
