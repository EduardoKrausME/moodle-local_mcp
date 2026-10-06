<?php
require_once(__DIR__ . '/../../../config.php');
require_login();
$context=context_system::instance();require_capability('moodle/site:config',$context);
$PAGE->set_url('/local/mcp/admin/audit.php');$PAGE->set_context($context);$PAGE->set_title(get_string('audit','local_mcp'));$PAGE->set_heading(get_string('audit','local_mcp'));
global $DB;$rows=[];
foreach($DB->get_records('local_mcp_audit',null,'timecreated DESC','*',0,200) as $r){
    $rows[]=['time'=>userdate($r->timecreated),'event'=>s($r->event),'side'=>s($r->side ?? ''),'tool'=>s($r->tool ?? ''),
        'userid'=>$r->userid ?: '-','ip'=>s($r->ip ?? ''),'status'=>s($r->status ?? ''),'destructive'=>$r->destructive?'Yes':'No'];
}
echo $OUTPUT->header();echo $OUTPUT->render_from_template('local_mcp/audit',['rows'=>$rows]);echo $OUTPUT->footer();
