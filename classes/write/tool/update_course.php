<?php
namespace local_mcp\write\tool;

defined('MOODLE_INTERNAL') || die;

final class update_course extends base_tool {
    public function get_name(): string { return 'update_course'; }
    public function get_title(): string { return 'Update course'; }
    public function get_description(): string { return 'Update course metadata using the core course API.'; }
    public function get_required_capability(): string { return 'moodle/course:update'; }
    public function get_input_schema(): array { return $this->object_schema([
        'courseid'=>['type'=>'integer','minimum'=>1],'fullname'=>['type'=>'string'],'shortname'=>['type'=>'string'],'visible'=>['type'=>'boolean']
    ],['courseid']); }
    public function resolve_context(array $arguments): \context { return \context_course::instance((int)$arguments['courseid'],MUST_EXIST); }
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $data=(object)['id'=>(int)$arguments['courseid']];
        foreach(['fullname','shortname'] as $f){ if(isset($arguments[$f])){$data->$f=clean_param($arguments[$f],PARAM_TEXT);} }
        if(array_key_exists('visible',$arguments)){$data->visible=(int)(bool)$arguments['visible'];}
        update_course($data);
        $course=get_course($data->id);
        return ['id'=>(int)$course->id,'fullname'=>$course->fullname,'shortname'=>$course->shortname,'visible'=>(bool)$course->visible];
    }
}
