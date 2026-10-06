<?php
require_once(__DIR__ . '/../../../config.php');
require_login();
$context=context_system::instance();require_capability('moodle/site:config',$context);
$PAGE->set_url('/local/mcp/admin/manualtokens.php');$PAGE->set_context($context);$PAGE->set_title(get_string('manualtokens','local_mcp'));$PAGE->set_heading(get_string('manualtokens','local_mcp'));
global $DB;$secret=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    require_sesskey();
    if(optional_param('revoke',0,PARAM_INT)){
        \local_mcp\security\manual_token_service::revoke(required_param('revoke',PARAM_INT));
        redirect($PAGE->url);
    }
    $name=required_param('name',PARAM_TEXT);
    $userid=required_param('userid',PARAM_INT);
    $read=optional_param('read',0,PARAM_BOOL);
    $write=optional_param('write',0,PARAM_BOOL);
    $days=max(1,optional_param('days',90,PARAM_INT));
    $result=\local_mcp\security\manual_token_service::create($name,$userid,(bool)$read,(bool)$write,time()+$days*DAYSECS);
    $secret=$result['token'];
}
$rows=[];
foreach($DB->get_records('local_mcp_manual_token',null,'timecreated DESC') as $r){
    $u=$DB->get_record('user',['id'=>$r->userid],'id,firstname,lastname');
    $rows[]=['id'=>$r->id,'name'=>s($r->name),'user'=>$u?fullname($u):'#'.$r->userid,'prefix'=>s($r->prefix),
        'read'=>$r->readenabled?'Yes':'No','write'=>$r->writeenabled?'Yes':'No','expires'=>$r->expires?userdate($r->expires):'-',
        'enabled'=>(bool)$r->enabled];
}
echo $OUTPUT->header();echo $OUTPUT->render_from_template('local_mcp/manualtokens',[
    'secret'=>$secret,'sesskey'=>sesskey(),'rows'=>$rows
]);echo $OUTPUT->footer();
