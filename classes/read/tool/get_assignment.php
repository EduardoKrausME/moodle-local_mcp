<?php
namespace local_mcp\read\tool;
defined('MOODLE_INTERNAL') || die;
final class get_assignment extends base_tool {
    public function get_name(): string { return 'get_assignment'; }
    public function get_title(): string { return 'Get assignment'; }
    public function get_description(): string { return 'Return one assignment activity.'; }
    public function get_required_capability(): string { return 'mod/assign:view'; }
    public function get_input_schema(): array { return $this->object_schema(['cmid'=>['type'=>'integer','minimum'=>1]],['cmid']); }
    public function resolve_context(array $arguments): \context { return \context_module::instance((int)$arguments['cmid'],MUST_EXIST); }
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        [$course,$cm]=get_course_and_cm_from_cmid((int)$arguments['cmid'],'assign');
        global $DB;
        $a=$DB->get_record('assign',['id'=>$cm->instance],'id,name,intro,introformat,duedate,cutoffdate,grade',MUST_EXIST);
        return ['cmid'=>(int)$cm->id,'id'=>(int)$a->id,'name'=>$a->name,
            'intro'=>format_module_intro('assign',$a,$cm->id),'duedate'=>(int)$a->duedate,'cutoffdate'=>(int)$a->cutoffdate,'grade'=>$a->grade];
    }
}
