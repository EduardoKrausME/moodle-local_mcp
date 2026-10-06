<?php
namespace local_mcp\write\tool;
defined('MOODLE_INTERNAL') || die;
final class update_section extends base_tool {
    public function get_name(): string { return 'update_section'; }
    public function get_title(): string { return 'Update section'; }
    public function get_description(): string { return 'Update course section metadata.'; }
    public function get_required_capability(): string { return 'moodle/course:update'; }
    public function get_input_schema(): array { return $this->object_schema([
        'courseid'=>['type'=>'integer','minimum'=>1],'sectionnum'=>['type'=>'integer','minimum'=>0],'name'=>['type'=>'string'],'visible'=>['type'=>'boolean']
    ],['courseid','sectionnum']); }
    public function resolve_context(array $arguments): \context { return \context_course::instance((int)$arguments['courseid'],MUST_EXIST); }
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $data=new \stdClass();
        if(isset($arguments['name'])){$data->name=clean_param($arguments['name'],PARAM_TEXT);}
        if(array_key_exists('visible',$arguments)){$data->visible=(int)(bool)$arguments['visible'];}
        course_update_section((int)$arguments['courseid'],(int)$arguments['sectionnum'],$data);
        return ['updated'=>true,'courseid'=>(int)$arguments['courseid'],'sectionnum'=>(int)$arguments['sectionnum']];
    }
}
