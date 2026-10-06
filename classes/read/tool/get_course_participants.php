<?php
namespace local_mcp\read\tool;

defined('MOODLE_INTERNAL') || die;

final class get_course_participants extends base_tool {
    public function get_name(): string { return 'get_course_participants'; }
    public function get_title(): string { return 'Get course participants'; }
    public function get_description(): string { return 'Return enrolled users in a course.'; }
    public function get_required_capability(): string { return 'moodle/course:viewparticipants'; }
    public function get_input_schema(): array { return $this->object_schema(['courseid'=>['type'=>'integer','minimum'=>1]], ['courseid']); }
    public function resolve_context(array $arguments): \context { return $this->course_context((int)$arguments['courseid']); }
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        $ctx = $this->course_context((int)$arguments['courseid']);
        $users = get_enrolled_users($ctx, '', 0, 'u.id,u.firstname,u.lastname,u.email', 'u.lastname,u.firstname', 0, 200);
        $out = [];
        foreach ($users as $user) { $out[] = ['id'=>(int)$user->id,'fullname'=>fullname($user),'email'=>$user->email]; }
        return ['participants'=>$out];
    }
}
