<?php
require_once(__DIR__ . '/../../../config.php');
require_login();
$context=context_system::instance();
require_capability('moodle/site:config',$context);
$PAGE->set_url('/local/mcp/admin/dashboard.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('dashboard','local_mcp'));
$PAGE->set_heading(get_string('pluginname','local_mcp'));
global $DB;
$data=[
    'connections'=>$DB->count_records('local_mcp_connection',['enabled'=>1]),
    'clients'=>$DB->count_records('local_mcp_oauth_client',['enabled'=>1]),
    'manualtokens'=>$DB->count_records('local_mcp_manual_token',['enabled'=>1]),
    'readtoday'=>$DB->count_records_select('local_mcp_audit','side = ? AND timecreated >= ?',['read',usergetmidnight(time())]),
    'writetoday'=>$DB->count_records_select('local_mcp_audit','side = ? AND timecreated >= ?',['write',usergetmidnight(time())]),
    'destructive'=>$DB->count_records_select('local_mcp_audit','destructive = 1 AND timecreated >= ?',[usergetmidnight(time())]),
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_mcp/dashboard',$data);
echo $OUTPUT->footer();
