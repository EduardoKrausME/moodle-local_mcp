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
 * get_grades.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\read\tool;
defined('MOODLE_INTERNAL') || die;
/**
 * Class get_grades.
 */
final class get_grades extends base_tool {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string { return 'get_grades'; }
    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string { return 'Get grades'; }
    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string { return 'Return course grades for one user.'; }
    /**
     * Method get_required_capability.
     *
     * @return string Return value.
     */
    public function get_required_capability(): string { return 'moodle/grade:viewall'; }
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
     * Method execute.
     *
     * @param array $arguments Parameter arguments.
     * @param \local_mcp\security\authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $grades=grade_get_grades((int)$arguments['courseid'],'','',0,(int)$arguments['userid']);
        $out=[];
        foreach($grades->items ?? [] as $item){
            $grade=$item->grades[(int)$arguments['userid']] ?? null;
            $out[]=['name'=>$item->name,'grademin'=>$item->grademin,'grademax'=>$item->grademax,
                'grade'=>$grade?->grade,'formatted'=>$grade?->str_grade];
        }
        return ['grades'=>$out];
    }
}
