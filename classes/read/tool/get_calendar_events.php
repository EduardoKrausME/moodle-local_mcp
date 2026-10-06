<?php
namespace local_mcp\read\tool;
defined('MOODLE_INTERNAL') || die;
final class get_calendar_events extends base_tool {
    public function get_name(): string { return 'get_calendar_events'; }
    public function get_title(): string { return 'Get calendar events'; }
    public function get_description(): string { return 'Return course calendar events in a time range.'; }
    public function get_required_capability(): string { return 'moodle/course:view'; }
    public function get_input_schema(): array { return $this->object_schema([
        'courseid'=>['type'=>'integer','minimum'=>1],'timestart'=>['type'=>'integer'],'timeend'=>['type'=>'integer']
    ],['courseid']); }
    public function resolve_context(array $arguments): \context { return \context_course::instance((int)$arguments['courseid'],MUST_EXIST); }
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        global $DB;$start=(int)($arguments['timestart']??time());$end=(int)($arguments['timeend']??($start+30*DAYSECS));
        $rows=$DB->get_records_select('event','courseid = :courseid AND timestart >= :start AND timestart <= :end',
            ['courseid'=>(int)$arguments['courseid'],'start'=>$start,'end'=>$end],'timestart ASC',
            'id,name,eventtype,timestart,timeduration,modulename,instance');
        $out=[];foreach($rows as $e){$out[]=['id'=>(int)$e->id,'name'=>$e->name,'eventtype'=>$e->eventtype,'timestart'=>(int)$e->timestart,
            'timeduration'=>(int)$e->timeduration,'modulename'=>$e->modulename,'instance'=>(int)$e->instance];}
        return ['events'=>$out];
    }
}
