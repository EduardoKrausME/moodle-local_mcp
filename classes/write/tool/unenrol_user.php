<?php
namespace local_mcp\write\tool;

defined('MOODLE_INTERNAL') || die;

final class unenrol_user extends base_tool {
    public function get_name(): string { return 'unenrol_user'; }
    public function get_title(): string { return 'Unenrol user'; }
    public function get_description(): string { return 'Remove a user from a manual enrolment instance.'; }
    public function get_required_capability(): string { return 'enrol/manual:unenrol'; }
    public function supports_dry_run(): bool { return true; }
    public function is_destructive(): bool { return true; }
    public function get_input_schema(): array { return $this->object_schema([
        'courseid'=>['type'=>'integer','minimum'=>1],'userid'=>['type'=>'integer','minimum'=>1]
    ],['courseid','userid']); }
    public function resolve_context(array $arguments): \context { return \context_course::instance((int)$arguments['courseid'],MUST_EXIST); }
    public function preview(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        return ['courseid'=>(int)$arguments['courseid'],'userid'=>(int)$arguments['userid'],
            'currently_enrolled'=>is_enrolled($this->resolve_context($arguments),(int)$arguments['userid'])];
    }
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        $instances=enrol_get_instances((int)$arguments['courseid'],true);
        $plugin=enrol_get_plugin('manual');
        foreach($instances as $instance){ if($instance->enrol==='manual'){ $plugin->unenrol_user($instance,(int)$arguments['userid']); } }
        return ['unenrolled'=>true];
    }
}
