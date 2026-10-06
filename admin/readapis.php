<?php
require_once(__DIR__ . '/../../../config.php');require_login();
$context=context_system::instance();require_capability('moodle/site:config',$context);
$PAGE->set_url('/local/mcp/admin/readapis.php');$PAGE->set_context($context);$PAGE->set_title(get_string('readapis','local_mcp'));$PAGE->set_heading(get_string('readapis','local_mcp'));
$rows=[];foreach(\local_mcp\read\registry::get_tools() as $tool){$rows[]=['name'=>$tool->get_name(),'description'=>$tool->get_description(),'capability'=>$tool->get_required_capability()];}
echo $OUTPUT->header();echo $OUTPUT->render_from_template('local_mcp/readapis',['rows'=>$rows]);echo $OUTPUT->footer();
