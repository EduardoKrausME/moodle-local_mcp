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
 * get_user.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\read\tool;

defined('MOODLE_INTERNAL') || die;

/**
 * Class get_user.
 */
final class get_user extends base_tool {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string { return 'get_user'; }
    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string { return 'Get user'; }
    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string { return 'Return a Moodle user profile.'; }
    /**
     * Method get_required_capability.
     *
     * @return string Return value.
     */
    public function get_required_capability(): string { return 'moodle/user:viewdetails'; }
    /**
     * Method get_input_schema.
     *
     * @return array Return value.
     */
    public function get_input_schema(): array { return $this->object_schema(['userid'=>['type'=>'integer','minimum'=>1]], ['userid']); }
    /**
     * Method resolve_context.
     *
     * @param array $arguments Parameter arguments.
     * @return \context Return value.
     */
    public function resolve_context(array $arguments): \context { return $this->user_context((int)$arguments['userid']); }
    /**
     * Method execute.
     *
     * @param array $arguments Parameter arguments.
     * @param \local_mcp\security\authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        global $DB;
        $u=$DB->get_record('user',['id'=>(int)$arguments['userid'],'deleted'=>0],'id,firstname,lastname,email,city,country,suspended',MUST_EXIST);
        return ['id'=>(int)$u->id,'fullname'=>fullname($u),'email'=>$u->email,'city'=>$u->city,'country'=>$u->country,'suspended'=>(bool)$u->suspended];
    }
}
