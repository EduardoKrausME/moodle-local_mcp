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
 * base_tool.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;

use local_mcp\security\authenticated_identity;
use local_mcp\write\tool_interface;

/**
 * Class base_tool.
 */
abstract class base_tool implements tool_interface {
    /**
     * Method supports_dry_run.
     *
     * @return bool Return value.
     */
    public function supports_dry_run(): bool {
        return false;
    }

    /**
     * Method requires_confirmation.
     *
     * @return bool Return value.
     */
    public function requires_confirmation(): bool {
        return true;
    }

    /**
     * Method is_destructive.
     *
     * @return bool Return value.
     */
    public function is_destructive(): bool {
        return false;
    }

    /**
     * Method preview.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        return ['tool' => $this->get_name(), 'arguments' => $arguments];
    }

    /**
     * Method object_schema.
     *
     * @param array $properties Parameter properties.
     * @param array $required Parameter required.
     * @return array Return value.
     */
    protected function object_schema(array $properties, array $required = []): array {
        return ['type' => 'object', 'properties' => $properties, 'required' => $required, 'additionalProperties' => false];
    }
}
