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
 * Purge all Moodle caches via Moodle core APIs.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;

use context;
use context_system;
use local_mcp\security\authenticated_identity;
use local_mcp\security\capability_guard;

/**
 * An administrator-only WRITE tool with an explicit confirmation preview.
 */
final class purge_all_caches extends base_tool {
    /** @return string Tool identifier. */
    public function get_name(): string {
        return 'purge_all_caches';
    }

    /** @return string Displayed title. */
    public function get_title(): string {
        return 'Purge all Moodle caches';
    }

    /** @return string Description for MCP clients. */
    public function get_description(): string {
        return 'Clear every Moodle cache via the core API. Rebuilding caches can temporarily slow the site. Requires explicit user approval.';
    }

    /** @return string Required Moodle capability. */
    public function get_required_capability(): string {
        return 'moodle/site:config';
    }

    /** @return array JSON Schema for a tool without inputs. */
    public function get_input_schema(): array {
        return $this->object_schema([]);
    }

    /**
     * @param array $arguments Tool arguments.
     * @return context System context.
     */
    public function resolve_context(array $arguments): context {
        return context_system::instance();
    }

    /** @return bool Previews must never purge caches. */
    public function supports_dry_run(): bool {
        return true;
    }

    /** @return bool Full cache purging is a site-wide operation. */
    public function is_destructive(): bool {
        return true;
    }

    /**
     * @param array $arguments Tool arguments.
     * @param authenticated_identity $identity Connected user.
     * @return array Safe preview.
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        capability_guard::check($this->get_required_capability(), $this->resolve_context($arguments),
            $identity->userid);
        return [
            'scope' => 'all',
            'effect' => 'Invalidates all site caches, including themes, CSS, JavaScript, language, course and application caches. Rebuilding them may slow requests.',
            'requires_confirmation' => true,
        ];
    }

    /**
     * @param array $arguments Tool arguments.
     * @param authenticated_identity $identity Connected user.
     * @return array Successful operation metadata.
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        capability_guard::check($this->get_required_capability(), $this->resolve_context($arguments),
            $identity->userid);
        \purge_all_caches();
        return [
            'success' => true,
            'scope' => 'all',
            'message' => 'All Moodle caches purged.',
        ];
    }
}
