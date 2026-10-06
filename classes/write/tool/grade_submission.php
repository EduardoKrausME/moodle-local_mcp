<?php
namespace local_mcp\write\tool;
defined('MOODLE_INTERNAL') || die;
final class grade_submission extends base_tool {
    public function get_name(): string { return 'grade_submission'; }
    public function get_title(): string { return 'Grade assignment submission'; }
    public function get_description(): string { return 'Grade an assignment submission through the assignment API.'; }
    public function get_required_capability(): string { return 'mod/assign:grade'; }
    public function get_input_schema(): array { return $this->object_schema([
        'cmid'=>['type'=>'integer','minimum'=>1],'userid'=>['type'=>'integer','minimum'=>1],'grade'=>['type'=>'number']
    ],['cmid','userid','grade']); }
    public function resolve_context(array $arguments): \context { return \context_module::instance((int)$arguments['cmid'],MUST_EXIST); }
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        [$course,$cm]=get_course_and_cm_from_cmid((int)$arguments['cmid'],'assign');
        $assign=new \assign(\context_module::instance($cm->id),$cm,$course);
        $grade=(object)[
            'grade'=>(float)$arguments['grade'],
            'attemptnumber'=>-1,
            'addattempt'=>0,
            'workflowstate'=>'',
            'applytoall'=>0,
        ];
        $assign->save_grade((int)$arguments['userid'],$grade,false);
        return ['graded'=>true,'userid'=>(int)$arguments['userid'],'grade'=>(float)$arguments['grade']];
    }
}
