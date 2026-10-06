<?php
require_once(__DIR__ . '/../../../config.php');
require_login();
$context=context_system::instance();
require_capability('moodle/site:config',$context);
$PAGE->set_url('/local/mcp/admin/connections.php');$PAGE->set_context($context);$PAGE->set_title(get_string('connections','local_mcp'));$PAGE->set_heading(get_string('connections','local_mcp'));
if(optional_param('revoke',0,PARAM_INT)){
    require_sesskey();
    \local_mcp\oauth\connection_service::revoke(required_param('revoke',PARAM_INT));
    redirect($PAGE->url);
}
global $DB;
$sql="SELECT c.*, o.name AS clientname, u.firstname, u.lastname
        FROM {local_mcp_connection} c
        JOIN {local_mcp_oauth_client} o ON o.id=c.clientid
        JOIN {user} u ON u.id=c.userid
       ORDER BY c.timecreated DESC";
$rows=[];
foreach($DB->get_records_sql($sql) as $r){
    $rows[]=['clientname'=>s($r->clientname),'user'=>fullname($r),'scopes'=>s($r->scopes),
        'created'=>userdate($r->timecreated),'lastused'=>$r->lastused?userdate($r->lastused):'-',
        'enabled'=>(bool)$r->enabled,'revokeurl'=>(new moodle_url($PAGE->url,['revoke'=>$r->id,'sesskey'=>sesskey()]))->out(false)];
}
echo $OUTPUT->header();echo $OUTPUT->render_from_template('local_mcp/connections',['rows'=>$rows]);echo $OUTPUT->footer();
