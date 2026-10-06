<?php
namespace local_mcp\read\tool;

defined('MOODLE_INTERNAL') || die;

final class get_course extends base_tool {
    public function get_name(): string { return 'get_course'; }
    public function get_title(): string { return 'Get course'; }
    public function get_description(): string { return 'Return core metadata for one course.'; }
    public function get_required_capability(): string { return 'moodle/course:view'; }
    public function get_input_schema(): array { return $this->object_schema(['courseid'=>['type'=>'integer','minimum'=>1]], ['courseid']); }
    public function resolve_context(array $arguments): \context { return $this->course_context((int)$arguments['courseid']); }
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        $course = get_course((int)$arguments['courseid']);
        return ['id'=>(int)$course->id,'fullname'=>$course->fullname,'shortname'=>$course->shortname,
            'summary'=>format_text($course->summary,$course->summaryformat,['context'=>\context_course::instance($course->id)]),
            'visible'=>(bool)$course->visible,'startdate'=>(int)$course->startdate,'enddate'=>(int)$course->enddate];
    }
}
