<?php
namespace local_mcp\write\tool;

defined('MOODLE_INTERNAL') || die;

final class create_user extends base_tool {
    public function get_name(): string { return 'create_user'; }
    public function get_title(): string { return 'Create user'; }
    public function get_description(): string { return 'Create a Moodle user using the core user API.'; }
    public function get_required_capability(): string { return 'moodle/user:create'; }
    public function get_input_schema(): array { return $this->object_schema([
        'username'=>['type'=>'string'],'firstname'=>['type'=>'string'],'lastname'=>['type'=>'string'],'email'=>['type'=>'string'],'auth'=>['type'=>'string']
    ],['username','firstname','lastname','email']); }
    public function resolve_context(array $arguments): \context { return \context_system::instance(); }
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        global $CFG;
        require_once($CFG->dirroot . '/user/lib.php');
        $user=(object)[
            'username'=>clean_param($arguments['username'],PARAM_USERNAME),
            'firstname'=>clean_param($arguments['firstname'],PARAM_TEXT),
            'lastname'=>clean_param($arguments['lastname'],PARAM_TEXT),
            'email'=>clean_param($arguments['email'],PARAM_EMAIL),
            'auth'=>clean_param($arguments['auth']??'manual',PARAM_PLUGIN),
            'confirmed'=>1,'mnethostid'=>$CFG->mnet_localhost_id
        ];
        $id=user_create_user($user,true,false);
        return ['id'=>(int)$id,'username'=>$user->username,'email'=>$user->email];
    }
}
