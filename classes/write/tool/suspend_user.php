<?php
namespace local_mcp\write\tool;

defined('MOODLE_INTERNAL') || die;

final class suspend_user extends base_tool {
    public function get_name(): string { return 'suspend_user'; }
    public function get_title(): string { return 'Suspend user'; }
    public function get_description(): string { return 'Suspend a Moodle user account.'; }
    public function get_required_capability(): string { return 'moodle/user:update'; }
    public function get_input_schema(): array { return $this->object_schema(['userid'=>['type'=>'integer','minimum'=>1]],['userid']); }
    public function resolve_context(array $arguments): \context { return \context_user::instance((int)$arguments['userid'],MUST_EXIST); }
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        global $CFG;
        require_once($CFG->dirroot . '/user/lib.php');
        user_update_user((object)['id'=>(int)$arguments['userid'],'suspended'=>1],false,false);
        return ['suspended'=>true,'userid'=>(int)$arguments['userid']];
    }
}
