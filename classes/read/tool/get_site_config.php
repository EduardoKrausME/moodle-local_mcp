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

namespace local_mcp\read\tool;

use context;
use context_system;
use local_mcp\config\site_config;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;

/**
 * Read a bounded set of core settings without exposing passwords or tokens.
 */
final class get_site_config extends base_tool {
    public function get_name(): string {
        return 'get_site_config';
    }

    public function get_title(): string {
        return 'Read Moodle site configuration';
    }

    public function get_description(): string {
        return 'Read approved core mdl_config settings, including developer debug, theme designer mode, '
            . 'Dashboard, My courses and default homepage. Returns DB and effective values; no secrets.';
    }

    public function get_required_capability(): string {
        return 'moodle/site:config';
    }

    public function get_input_schema(): array {
        return $this->object_schema([
            'names' => [
                'type' => 'array',
                'description' => 'Approved setting names. Omit to list all supported settings.',
                'items' => ['type' => 'string', 'enum' => site_config::names()],
                'maxItems' => 20,
                'uniqueItems' => true,
            ],
        ]);
    }

    public function resolve_context(array $arguments): context {
        return context_system::instance();
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        if (!is_siteadmin($identity->userid)) {
            throw new api_exception('permission_denied', 403);
        }
        $names = $arguments['names'] ?? site_config::names();
        if (!is_array($names) || count($names) > 20) {
            throw new api_exception('invalid_config_names', 400);
        }
        $items = [];
        foreach (array_unique($names) as $name) {
            if (!is_string($name)) {
                throw new api_exception('invalid_config_names', 400);
            }
            $items[] = site_config::current($name);
        }
        return ['settings' => $items, 'supported_names' => site_config::names()];
    }
}
