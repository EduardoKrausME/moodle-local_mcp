<?php
namespace local_mcp\read\tool;
defined('MOODLE_INTERNAL') || die;
final class get_quiz extends base_tool {
    public function get_name(): string { return 'get_quiz'; }
    public function get_title(): string { return 'Get quiz'; }
    public function get_description(): string { return 'Return quiz metadata.'; }
    public function get_required_capability(): string { return 'mod/quiz:view'; }
    public function get_input_schema(): array { return $this->object_schema(['cmid'=>['type'=>'integer','minimum'=>1]],['cmid']); }
    public function resolve_context(array $arguments): \context { return \context_module::instance((int)$arguments['cmid'],MUST_EXIST); }
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        global $DB;[$course,$cm]=get_course_and_cm_from_cmid((int)$arguments['cmid'],'quiz');
        $q=$DB->get_record('quiz',['id'=>$cm->instance],'id,name,intro,introformat,timeopen,timeclose,timelimit,grade,attempts',MUST_EXIST);
        return ['cmid'=>(int)$cm->id,'id'=>(int)$q->id,'name'=>$q->name,'intro'=>format_module_intro('quiz',$q,$cm->id),
            'timeopen'=>(int)$q->timeopen,'timeclose'=>(int)$q->timeclose,'timelimit'=>(int)$q->timelimit,'grade'=>$q->grade,'attempts'=>(int)$q->attempts];
    }
}
