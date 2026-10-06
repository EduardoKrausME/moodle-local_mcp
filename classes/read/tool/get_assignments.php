<?php
namespace local_mcp\read\tool;
defined('MOODLE_INTERNAL') || die;
final class get_assignments extends base_tool {
    public function get_name(): string { return 'get_assignments'; }
    public function get_title(): string { return 'Get assignments'; }
    public function get_description(): string { return 'List assignment activities visible in a course.'; }
    public function get_required_capability(): string { return 'moodle/course:view'; }
    public function get_input_schema(): array { return $this->object_schema(['courseid'=>['type'=>'integer','minimum'=>1]],['courseid']); }
    public function resolve_context(array $arguments): \context { return \context_course::instance((int)$arguments['courseid'],MUST_EXIST); }
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        $course=get_course((int)$arguments['courseid']);$modinfo=get_fast_modinfo($course,$identity->userid);$out=[];
        foreach($modinfo->get_instances_of('assign') as $cm){
            if(!$cm->uservisible || !has_capability('mod/assign:view',\context_module::instance($cm->id),$identity->userid)){continue;}
            $out[]=['cmid'=>(int)$cm->id,'instanceid'=>(int)$cm->instance,'name'=>$cm->name,'url'=>$cm->url?->out(false)];
        }
        return ['assignments'=>$out];
    }
}
