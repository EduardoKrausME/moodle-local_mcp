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
 * create_course.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;

defined('MOODLE_INTERNAL') || die;

/**
 * Class create_course.
 */
final class create_course extends base_tool {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string { return 'create_course'; }
    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string { return 'Create course'; }
    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string { return 'Create a Moodle course using the core course API.'; }
    /**
     * Method get_required_capability.
     *
     * @return string Return value.
     */
    public function get_required_capability(): string { return 'moodle/course:create'; }
    /**
     * Method get_input_schema.
     *
     * @return array Return value.
     */
    public function get_input_schema(): array { return $this->object_schema([
        'fullname'=>['type'=>'string'],'shortname'=>['type'=>'string'],'categoryid'=>['type'=>'integer','minimum'=>1],
        'visible'=>['type'=>'boolean']
    ],['fullname','shortname','categoryid']); }
    /**
     * Method resolve_context.
     *
     * @param array $arguments Parameter arguments.
     * @return \context Return value.
     */
    public function resolve_context(array $arguments): \context { return \context_coursecat::instance((int)$arguments['categoryid']); }
    /**
     * Method execute.
     *
     * @param array $arguments Parameter arguments.
     * @param \local_mcp\security\authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $course=create_course((object)[
            'fullname'=>clean_param($arguments['fullname'],PARAM_TEXT),
            'shortname'=>clean_param($arguments['shortname'],PARAM_TEXT),
            'category'=>(int)$arguments['categoryid'],
            'visible'=>array_key_exists('visible',$arguments)?(int)(bool)$arguments['visible']:1,
        ]);
        return ['id'=>(int)$course->id,'fullname'=>$course->fullname,'shortname'=>$course->shortname];
    }
}
