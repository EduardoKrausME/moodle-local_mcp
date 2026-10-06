<?php
namespace local_mcp\read\tool;
defined('MOODLE_INTERNAL') || die;
final class get_grades extends base_tool {
    public function get_name(): string { return 'get_grades'; }
    public function get_title(): string { return 'Get grades'; }
    public function get_description(): string { return 'Return course grades for one user.'; }
    public function get_required_capability(): string { return 'moodle/grade:viewall'; }
    public function get_input_schema(): array { return $this->object_schema([
        'courseid'=>['type'=>'integer','minimum'=>1],'userid'=>['type'=>'integer','minimum'=>1]
    ],['courseid','userid']); }
    public function resolve_context(array $arguments): \context { return \context_course::instance((int)$arguments['courseid'],MUST_EXIST); }
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $grades=grade_get_grades((int)$arguments['courseid'],'','',0,(int)$arguments['userid']);
        $out=[];
        foreach($grades->items ?? [] as $item){
            $grade=$item->grades[(int)$arguments['userid']] ?? null;
            $out[]=['name'=>$item->name,'grademin'=>$item->grademin,'grademax'=>$item->grademax,
                'grade'=>$grade?->grade,'formatted'=>$grade?->str_grade];
        }
        return ['grades'=>$out];
    }
}
