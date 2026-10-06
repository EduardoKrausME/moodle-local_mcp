<?php
namespace local_mcp\read\tool;

defined('MOODLE_INTERNAL') || die;

final class get_user_courses extends base_tool {
    public function get_name(): string { return 'get_user_courses'; }
    public function get_title(): string { return 'Get user courses'; }
    public function get_description(): string { return 'Return courses in which a user is enrolled.'; }
    public function get_required_capability(): string { return 'moodle/user:viewdetails'; }
    public function get_input_schema(): array { return $this->object_schema(['userid'=>['type'=>'integer','minimum'=>1]], ['userid']); }
    public function resolve_context(array $arguments): \context { return $this->user_context((int)$arguments['userid']); }
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        $out=[];
        foreach (enrol_get_all_users_courses((int)$arguments['userid'],true,['id','fullname','shortname','visible']) as $course) {
            $ctx=\context_course::instance($course->id);
            if ((int)$arguments['userid'] === $identity->userid || has_capability('moodle/course:viewparticipants',$ctx,$identity->userid)) {
                $out[]=['id'=>(int)$course->id,'fullname'=>$course->fullname,'shortname'=>$course->shortname,'visible'=>(bool)$course->visible];
            }
        }
        return ['courses'=>$out];
    }
}
