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
 * unenrol_user.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;

defined('MOODLE_INTERNAL') || die;

/**
 * Class unenrol_user.
 */
final class unenrol_user extends base_tool {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string { return 'unenrol_user'; }
    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string { return 'Unenrol user'; }
    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string { return 'Remove a user from a manual enrolment instance.'; }
    /**
     * Method get_required_capability.
     *
     * @return string Return value.
     */
    public function get_required_capability(): string { return 'enrol/manual:unenrol'; }
    /**
     * Method supports_dry_run.
     *
     * @return bool Return value.
     */
    public function supports_dry_run(): bool { return true; }
    /**
     * Method is_destructive.
     *
     * @return bool Return value.
     */
    public function is_destructive(): bool { return true; }
    /**
     * Method get_input_schema.
     *
     * @return array Return value.
     */
    public function get_input_schema(): array { return $this->object_schema([
        'courseid'=>['type'=>'integer','minimum'=>1],'userid'=>['type'=>'integer','minimum'=>1]
    ],['courseid','userid']); }
    /**
     * Method resolve_context.
     *
     * @param array $arguments Parameter arguments.
     * @return \context Return value.
     */
    public function resolve_context(array $arguments): \context { return \context_course::instance((int)$arguments['courseid'],MUST_EXIST); }
    /**
     * Method preview.
     *
     * @param array $arguments Parameter arguments.
     * @param \local_mcp\security\authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public function preview(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        return ['courseid'=>(int)$arguments['courseid'],'userid'=>(int)$arguments['userid'],
            'currently_enrolled'=>is_enrolled($this->resolve_context($arguments),(int)$arguments['userid'])];
    }
    /**
     * Method execute.
     *
     * @param array $arguments Parameter arguments.
     * @param \local_mcp\security\authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        $instances=enrol_get_instances((int)$arguments['courseid'],true);
        $plugin=enrol_get_plugin('manual');
        foreach($instances as $instance){ if($instance->enrol==='manual'){ $plugin->unenrol_user($instance,(int)$arguments['userid']); } }
        return ['unenrolled'=>true];
    }
}
