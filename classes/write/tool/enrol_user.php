<?php
namespace local_mcp\write\tool;

defined('MOODLE_INTERNAL') || die;

final class enrol_user extends base_tool {
    public function get_name(): string { return 'enrol_user'; }
    public function get_title(): string { return 'Enrol user'; }
    public function get_description(): string { return 'Enrol a user with an existing manual enrolment instance.'; }
    public function get_required_capability(): string { return 'enrol/manual:enrol'; }
    public function supports_dry_run(): bool { return true; }
    public function get_input_schema(): array { return $this->object_schema([
        'courseid'=>['type'=>'integer','minimum'=>1],'userid'=>['type'=>'integer','minimum'=>1],'roleid'=>['type'=>'integer','minimum'=>1]
    ],['courseid','userid','roleid']); }
    public function resolve_context(array $arguments): \context { return \context_course::instance((int)$arguments['courseid'],MUST_EXIST); }
    public function preview(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        $ctx=$this->resolve_context($arguments);
        return ['courseid'=>(int)$arguments['courseid'],'userid'=>(int)$arguments['userid'],
            'already_enrolled'=>is_enrolled($ctx,(int)$arguments['userid'])];
    }
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        $instances=enrol_get_instances((int)$arguments['courseid'],true);
        $instance=null; foreach($instances as $candidate){ if($candidate->enrol==='manual'){ $instance=$candidate; break; } }
        if(!$instance){ throw new \local_mcp\exception\api_exception('manual_enrolment_unavailable',409); }
        $plugin=enrol_get_plugin('manual');
        $plugin->enrol_user($instance,(int)$arguments['userid'],(int)$arguments['roleid']);
        return ['enrolled'=>true,'courseid'=>(int)$arguments['courseid'],'userid'=>(int)$arguments['userid']];
    }
}
