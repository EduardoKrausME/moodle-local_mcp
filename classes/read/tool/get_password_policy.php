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
 * Read Moodle password policy for generating valid credentials.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\read\tool;

use context;
use context_system;
use local_mcp\security\authenticated_identity;
use local_mcp\security\password_policy;

/**
 * Expose password requirements to MCP clients without exposing passwords.
 */
final class get_password_policy extends base_tool {
    /** @return string */
    public function get_name(): string {
        return 'get_password_policy';
    }

    /** @return string */
    public function get_title(): string {
        return 'Read Moodle password policy';
    }

    /** @return string */
    public function get_description(): string {
        return 'Call before generating a password for create_user or update_user. '
            . 'Returns the current Moodle minimum length, required digits, uppercase, lowercase, '
            . 'special characters and policy status; no password or secret is returned.';
    }

    /** @return string */
    public function get_required_capability(): string {
        return 'moodle/user:update';
    }

    /** @return array */
    public function get_input_schema(): array {
        return $this->object_schema([]);
    }

    /** @return context */
    public function resolve_context(array $arguments): context {
        return context_system::instance();
    }

    /** @return array */
    public function execute(array $arguments, authenticated_identity $identity): array {
        return ['password_policy' => password_policy::current()];
    }
}
