<?php
namespace local_mcp\read\tool;
defined('MOODLE_INTERNAL') || die;
final class get_submissions extends base_tool {
    public function get_name(): string { return 'get_submissions'; }
    public function get_title(): string { return 'Get assignment submissions'; }
    public function get_description(): string { return 'Return assignment submission metadata for grading/reporting.'; }
    public function get_required_capability(): string { return 'mod/assign:grade'; }
    public function get_input_schema(): array { return $this->object_schema(['cmid'=>['type'=>'integer','minimum'=>1]],['cmid']); }
    public function resolve_context(array $arguments): \context { return \context_module::instance((int)$arguments['cmid'],MUST_EXIST); }
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        global $CFG,$DB;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        [$course,$cm]=get_course_and_cm_from_cmid((int)$arguments['cmid'],'assign');
        $assign=new \assign(\context_module::instance($cm->id),$cm,$course);
        $subs=$DB->get_records('assign_submission',['assignment'=>$assign->get_instance()->id], 'timemodified DESC',
            'id,userid,status,timecreated,timemodified,attemptnumber');
        $out=[]; foreach($subs as $s){$out[]=['id'=>(int)$s->id,'userid'=>(int)$s->userid,'status'=>$s->status,
            'timecreated'=>(int)$s->timecreated,'timemodified'=>(int)$s->timemodified,'attemptnumber'=>(int)$s->attemptnumber];}
        return ['submissions'=>$out];
    }
}
