<?php
namespace local_mcp\read\tool;

defined('MOODLE_INTERNAL') || die;

class get_course_contents extends base_tool {
    public function get_name(): string { return 'get_course_contents'; }
    public function get_title(): string { return 'Get course contents'; }
    public function get_description(): string { return 'Return visible sections and activities for a course.'; }
    public function get_required_capability(): string { return 'moodle/course:view'; }
    public function get_input_schema(): array { return $this->object_schema(['courseid'=>['type'=>'integer','minimum'=>1]], ['courseid']); }
    public function resolve_context(array $arguments): \context { return $this->course_context((int)$arguments['courseid']); }
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        $course = get_course((int)$arguments['courseid']);
        $modinfo = get_fast_modinfo($course, $identity->userid);
        $sections = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            if (!$section->uservisible) { continue; }
            $activities = [];
            foreach ($modinfo->sections[$section->section] ?? [] as $cmid) {
                $cm = $modinfo->cms[$cmid];
                if (!$cm->uservisible) { continue; }
                $activities[] = ['cmid'=>(int)$cm->id,'name'=>$cm->name,'modname'=>$cm->modname,
                    'url'=>$cm->url ? $cm->url->out(false) : null];
            }
            $sections[] = ['id'=>(int)$section->id,'section'=>(int)$section->section,
                'name'=>get_section_name($course,$section),'activities'=>$activities];
        }
        return ['courseid'=>(int)$course->id,'sections'=>$sections];
    }
}
