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
 * enrol_user.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;

use context;
use context_course;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\security\capability_guard;

/**
 * Class enrol_user.
 */
final class enrol_user extends base_tool {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string {
        return 'enrol_user';
    }

    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string {
        return 'Enrol user';
    }

    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string {
        return 'Enrol a user with an existing manual enrolment instance.';
    }

    /**
     * Method get_required_capability.
     *
     * @return string Return value.
     */
    public function get_required_capability(): string {
        return 'enrol/manual:enrol';
    }

    /**
     * Method supports_dry_run.
     *
     * @return bool Return value.
     */
    public function supports_dry_run(): bool {
        return true;
    }

    /**
     * Method get_input_schema.
     *
     * @return array Return value.
     */
    public function get_input_schema(): array {
        return $this->object_schema([
            'courseid' => ['type' => 'integer', 'minimum' => 1], 'userid' => ['type' => 'integer', 'minimum' => 1], 'roleid' => ['type' => 'integer', 'minimum' => 1]
        ], ['courseid', 'userid', 'roleid']);
    }

    /**
     * Method resolve_context.
     *
     * @param array $arguments Parameter arguments.
     * @return context Return value.
     */
    public function resolve_context(array $arguments): context {
        return context_course::instance((int)$arguments['courseid'], MUST_EXIST);
    }

    /**
     * Method preview.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        $this->validate_role($arguments, $identity);
        $ctx = $this->resolve_context($arguments);
        return ['roleid' => (int)$arguments["roleid"], 'courseid' => (int)$arguments['courseid'], 'userid' => (int)$arguments['userid'],
            'already_enrolled' => is_enrolled($ctx, (int)$arguments['userid'])];
    }

    /**
     * Method execute.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        $this->validate_role($arguments, $identity);
        $instances = enrol_get_instances((int)$arguments['courseid'], true);
        $instance = null;
        foreach ($instances as $candidate) {
            if ($candidate->enrol === 'manual') {
                $instance = $candidate;
                break;
            }
        }
        if (!$instance) {
            throw new api_exception('manual_enrolment_unavailable', 409);
        }
        $plugin = enrol_get_plugin('manual');
        $plugin->enrol_user($instance, (int)$arguments['userid'], (int)$arguments['roleid']);
        return ['enrolled' => true, 'courseid' => (int)$arguments['courseid'], 'userid' => (int)$arguments['userid']];
    }

    /**
     * Check both enrolment permission and assignability of the requested role.
     *
     * @param array $arguments Validated tool arguments.
     * @param authenticated_identity $identity OAuth user performing the action.
     * @return void
     */
    private function validate_role(array $arguments, authenticated_identity $identity): void {
        $context = $this->resolve_context($arguments);
        capability_guard::check("enrol/manual:enrol", $context, $identity->userid);
        capability_guard::check("moodle/role:assign", $context, $identity->userid);

        $roles = get_assignable_roles($context, ROLENAME_ALIAS, false, $identity->userid);
        if (!array_key_exists((int)$arguments["roleid"], $roles)) {
            throw new api_exception("permission_denied", 403);
        }
    }
}
