<?php
require_once(__DIR__ . '/../../../config.php');
require_login();
$context=context_system::instance();require_capability('moodle/site:config',$context);
$PAGE->set_url('/local/mcp/admin/clients.php');$PAGE->set_context($context);$PAGE->set_title(get_string('oauthclients','local_mcp'));$PAGE->set_heading(get_string('oauthclients','local_mcp'));
global $DB;$rows=[];
foreach($DB->get_records('local_mcp_oauth_client',null,'timecreated DESC') as $r){
    $rows[]=['clientid'=>s($r->clientid),'name'=>s($r->name),'type'=>s($r->clienttype),'dynamic'=>$r->dynamic?'Yes':'No',
        'enabled'=>$r->enabled?'Yes':'No','created'=>userdate($r->timecreated),'lastused'=>$r->lastused?userdate($r->lastused):'-'];
}
echo $OUTPUT->header();echo $OUTPUT->render_from_template('local_mcp/clients',['rows'=>$rows]);echo $OUTPUT->footer();
