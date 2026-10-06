<?php
namespace local_mcp\read\tool;

defined('MOODLE_INTERNAL') || die;

final class get_user extends base_tool {
    public function get_name(): string { return 'get_user'; }
    public function get_title(): string { return 'Get user'; }
    public function get_description(): string { return 'Return a Moodle user profile.'; }
    public function get_required_capability(): string { return 'moodle/user:viewdetails'; }
    public function get_input_schema(): array { return $this->object_schema(['userid'=>['type'=>'integer','minimum'=>1]], ['userid']); }
    public function resolve_context(array $arguments): \context { return $this->user_context((int)$arguments['userid']); }
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        global $DB;
        $u=$DB->get_record('user',['id'=>(int)$arguments['userid'],'deleted'=>0],'id,firstname,lastname,email,city,country,suspended',MUST_EXIST);
        return ['id'=>(int)$u->id,'fullname'=>fullname($u),'email'=>$u->email,'city'=>$u->city,'country'=>$u->country,'suspended'=>(bool)$u->suspended];
    }
}
