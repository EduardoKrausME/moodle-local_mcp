<?php
namespace local_mcp\write\tool;

defined('MOODLE_INTERNAL') || die;

final class update_user extends base_tool {
    public function get_name(): string { return 'update_user'; }
    public function get_title(): string { return 'Update user'; }
    public function get_description(): string { return 'Update a Moodle user using the core user API.'; }
    public function get_required_capability(): string { return 'moodle/user:update'; }
    public function get_input_schema(): array { return $this->object_schema([
        'userid'=>['type'=>'integer','minimum'=>1],'firstname'=>['type'=>'string'],'lastname'=>['type'=>'string'],'email'=>['type'=>'string']
    ],['userid']); }
    public function resolve_context(array $arguments): \context { return \context_user::instance((int)$arguments['userid'],MUST_EXIST); }
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        global $CFG;
        require_once($CFG->dirroot . '/user/lib.php');
        $user=(object)['id'=>(int)$arguments['userid']];
        foreach(['firstname','lastname'] as $f){ if(isset($arguments[$f])){$user->$f=clean_param($arguments[$f],PARAM_TEXT);} }
        if(isset($arguments['email'])){$user->email=clean_param($arguments['email'],PARAM_EMAIL);}
        user_update_user($user,false,false);
        return ['updated'=>true,'userid'=>$user->id];
    }
}
