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
use dml_exception;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\security\capability_guard;

/**
 * An administrator-only WRITE tool with an explicit confirmation preview.
 */
final class purge_all_caches extends base_tool {

    /**
     * get_name
     *
     * @return string
     */
    public function get_name(): string {
        return 'purge_all_caches';
    }

    /**
     * get_title
     *
     * @return string
     */
    public function get_title(): string {
        return 'Purge all Moodle caches';
    }

    /**
     * get_description
     *
     * @return string
     */
    public function get_description(): string {
        return 'Clear every Moodle cache via the core API. Rebuilding caches can temporarily slow the site. Requires explicit user approval.';
    }

    /**
     * get_required_capability
     *
     * @return string
     */
    public function get_required_capability(): string {
        return 'moodle/site:config';
    }

    /**
     * get_input_schema
     *
     * @return array
     */
    public function get_input_schema(): array {
        return $this->object_schema([]);
    }

    /**
     * resolve_context
     *
     * @param array $arguments
     * @return context
     * @throws dml_exception
     */
    public function resolve_context(array $arguments): context {
        return context_system::instance();
    }

    /**
     * supports_dry_run
     *
     * @return bool
     */
    public function supports_dry_run(): bool {
        return true;
    }

    /**
     * is_destructive
     *
     * @return bool
     */
    public function is_destructive(): bool {
        return true;
    }

    /**
     * preview
     *
     * @param array $arguments
     * @param authenticated_identity $identity
     * @return array
     * @throws dml_exception
     * @throws api_exception
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
     * execute
     *
     * @param array $arguments
     * @param authenticated_identity $identity
     * @return array
     * @throws dml_exception
     * @throws api_exception
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
