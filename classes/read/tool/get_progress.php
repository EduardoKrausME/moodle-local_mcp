<?php
namespace local_mcp\read\tool;
defined('MOODLE_INTERNAL') || die;
final class get_progress extends base_tool {
    public function get_name(): string { return 'get_progress'; }
    public function get_title(): string { return 'Get course progress'; }
    public function get_description(): string { return 'Return activity completion progress for a user in a course.'; }
    public function get_required_capability(): string { return 'moodle/course:view'; }
    public function get_input_schema(): array { return $this->object_schema([
        'courseid'=>['type'=>'integer','minimum'=>1],'userid'=>['type'=>'integer','minimum'=>1]
    ],['courseid','userid']); }
    public function resolve_context(array $arguments): \context { return \context_course::instance((int)$arguments['courseid'],MUST_EXIST); }
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        $course=get_course((int)$arguments['courseid']);
        $ctx=\context_course::instance($course->id);
        if ((int)$arguments['userid'] !== $identity->userid && !has_capability('moodle/course:viewparticipants',$ctx,$identity->userid)) {
            throw new \local_mcp\exception\api_exception('permission_denied',403);
        }
        $completion=new \completion_info($course);
        $modinfo=get_fast_modinfo($course,(int)$arguments['userid']);
        $items=[];$complete=0;$total=0;
        foreach($modinfo->cms as $cm){
            if(!$cm->uservisible || !$completion->is_enabled($cm)){continue;}
            $data=$completion->get_data($cm,false,(int)$arguments['userid']);
            $done=in_array((int)$data->completionstate,[COMPLETION_COMPLETE,COMPLETION_COMPLETE_PASS,COMPLETION_COMPLETE_FAIL],true);
            $items[]=['cmid'=>(int)$cm->id,'name'=>$cm->name,'complete'=>$done];
            $total++; if($done){$complete++;}
        }
        return ['courseid'=>$course->id,'userid'=>(int)$arguments['userid'],'completed'=>$complete,'total'=>$total,
            'percent'=>$total?round($complete*100/$total,2):0,'activities'=>$items];
    }
}
