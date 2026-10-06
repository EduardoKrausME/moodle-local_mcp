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
 * update_user.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;

defined('MOODLE_INTERNAL') || die;

/**
 * Class update_user.
 */
final class update_user extends base_tool {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string { return 'update_user'; }
    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string { return 'Update user'; }
    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string { return 'Update a Moodle user using the core user API.'; }
    /**
     * Method get_required_capability.
     *
     * @return string Return value.
     */
    public function get_required_capability(): string { return 'moodle/user:update'; }
    /**
     * Method get_input_schema.
     *
     * @return array Return value.
     */
    public function get_input_schema(): array { return $this->object_schema([
        'userid'=>['type'=>'integer','minimum'=>1],'firstname'=>['type'=>'string'],'lastname'=>['type'=>'string'],'email'=>['type'=>'string']
    ],['userid']); }
    /**
     * Method resolve_context.
     *
     * @param array $arguments Parameter arguments.
     * @return \context Return value.
     */
    public function resolve_context(array $arguments): \context { return \context_user::instance((int)$arguments['userid'],MUST_EXIST); }
    /**
     * Method execute.
     *
     * @param array $arguments Parameter arguments.
     * @param \local_mcp\security\authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        global $CFG;
        require_once($CFG->dirroot . '/user/lib.php');
        $user=(object)['id'=>(int)$arguments['userid']];
        foreach(['firstname','lastname'] as $f){ if(isset($arguments[$f])){$user->$f=clean_param($arguments[$f],PARAM_TEXT);} }
        if(isset($arguments['email'])){$user->email=clean_param($arguments['email'],PARAM_EMAIL);}
        user_update_user($user,false,false);
        return ['updated'=>true,'userid'=>$user->id];
    }
}
