<?php
namespace local_mcp\read\tool;
defined('MOODLE_INTERNAL') || die;
final class get_attempts extends base_tool {
    public function get_name(): string { return 'get_attempts'; }
    public function get_title(): string { return 'Get quiz attempts'; }
    public function get_description(): string { return 'Return quiz attempt metadata for reporting.'; }
    public function get_required_capability(): string { return 'mod/quiz:viewreports'; }
    public function get_input_schema(): array { return $this->object_schema(['cmid'=>['type'=>'integer','minimum'=>1]],['cmid']); }
    public function resolve_context(array $arguments): \context { return \context_module::instance((int)$arguments['cmid'],MUST_EXIST); }
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        global $DB;[$course,$cm]=get_course_and_cm_from_cmid((int)$arguments['cmid'],'quiz');
        $rows=$DB->get_records('quiz_attempts',['quiz'=>$cm->instance],'timestart DESC','id,userid,attempt,state,timestart,timefinish,sumgrades');
        $out=[];foreach($rows as $a){$out[]=['id'=>(int)$a->id,'userid'=>(int)$a->userid,'attempt'=>(int)$a->attempt,'state'=>$a->state,
            'timestart'=>(int)$a->timestart,'timefinish'=>(int)$a->timefinish,'sumgrades'=>$a->sumgrades];}
        return ['attempts'=>$out];
    }
}
