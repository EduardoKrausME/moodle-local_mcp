<?php
namespace local_mcp\write\tool;
defined('MOODLE_INTERNAL') || die;
final class create_section extends base_tool {
    public function get_name(): string { return 'create_section'; }
    public function get_title(): string { return 'Create section'; }
    public function get_description(): string { return 'Create a course section using Moodle core course APIs.'; }
    public function get_required_capability(): string { return 'moodle/course:update'; }
    public function get_input_schema(): array { return $this->object_schema([
        'courseid'=>['type'=>'integer','minimum'=>1],'name'=>['type'=>'string']
    ],['courseid']); }
    public function resolve_context(array $arguments): \context { return \context_course::instance((int)$arguments['courseid'],MUST_EXIST); }
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $courseid=(int)$arguments['courseid'];
        $section=course_create_section($courseid,0);
        if(isset($arguments['name']) && trim($arguments['name'])!==''){
            course_update_section($courseid,$section,(object)['name'=>clean_param($arguments['name'],PARAM_TEXT)]);
            $section=get_fast_modinfo($courseid)->get_section_info($section->section);
        }
        return ['id'=>(int)$section->id,'section'=>(int)$section->section,'name'=>$section->name];
    }
}
