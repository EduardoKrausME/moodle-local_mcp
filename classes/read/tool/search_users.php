<?php
namespace local_mcp\read\tool;

defined('MOODLE_INTERNAL') || die;

final class search_users extends base_tool {
    public function get_name(): string { return 'search_users'; }
    public function get_title(): string { return 'Search users'; }
    public function get_description(): string { return 'Search Moodle user accounts.'; }
    public function get_required_capability(): string { return 'moodle/user:viewdetails'; }
    public function get_input_schema(): array { return $this->object_schema(['query'=>['type'=>'string'],'limit'=>['type'=>'integer','minimum'=>1,'maximum'=>100]], ['query']); }
    public function resolve_context(array $arguments): \context { return \context_system::instance(); }
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        global $DB;
        $needle = '%' . $DB->sql_like_escape(trim((string)$arguments['query'])) . '%';
        $where = 'deleted = 0 AND (' . $DB->sql_like('firstname', ':q1', false) . ' OR ' .
            $DB->sql_like('lastname', ':q2', false) . ' OR ' . $DB->sql_like('email', ':q3', false) . ')';
        $users = $DB->get_records_select('user', $where, ['q1'=>$needle,'q2'=>$needle,'q3'=>$needle],
            'lastname,firstname', 'id,firstname,lastname,email,suspended', 0, min(100,(int)($arguments['limit']??20)));
        $out=[]; foreach ($users as $u) { $out[]=['id'=>(int)$u->id,'fullname'=>fullname($u),'email'=>$u->email,'suspended'=>(bool)$u->suspended]; }
        return ['users'=>$out];
    }
}
