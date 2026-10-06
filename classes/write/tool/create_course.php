<?php
namespace local_mcp\write\tool;

defined('MOODLE_INTERNAL') || die;

final class create_course extends base_tool {
    public function get_name(): string { return 'create_course'; }
    public function get_title(): string { return 'Create course'; }
    public function get_description(): string { return 'Create a Moodle course using the core course API.'; }
    public function get_required_capability(): string { return 'moodle/course:create'; }
    public function get_input_schema(): array { return $this->object_schema([
        'fullname'=>['type'=>'string'],'shortname'=>['type'=>'string'],'categoryid'=>['type'=>'integer','minimum'=>1],
        'visible'=>['type'=>'boolean']
    ],['fullname','shortname','categoryid']); }
    public function resolve_context(array $arguments): \context { return \context_coursecat::instance((int)$arguments['categoryid']); }
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $course=create_course((object)[
            'fullname'=>clean_param($arguments['fullname'],PARAM_TEXT),
            'shortname'=>clean_param($arguments['shortname'],PARAM_TEXT),
            'category'=>(int)$arguments['categoryid'],
            'visible'=>array_key_exists('visible',$arguments)?(int)(bool)$arguments['visible']:1,
        ]);
        return ['id'=>(int)$course->id,'fullname'=>$course->fullname,'shortname'=>$course->shortname];
    }
}
