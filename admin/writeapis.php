<?php
require_once(__DIR__ . '/../../../config.php');require_login();
$context=context_system::instance();require_capability('moodle/site:config',$context);
$PAGE->set_url('/local/mcp/admin/writeapis.php');$PAGE->set_context($context);$PAGE->set_title(get_string('writeapis','local_mcp'));$PAGE->set_heading(get_string('writeapis','local_mcp'));
$rows=[];foreach(\local_mcp\write\registry::get_tools() as $tool){$rows[]=['name'=>$tool->get_name(),'description'=>$tool->get_description(),'capability'=>$tool->get_required_capability(),
'dryrun'=>$tool->supports_dry_run()?'Yes':'No','confirmation'=>$tool->requires_confirmation()?'Yes':'No','destructive'=>$tool->is_destructive()?'Yes':'No'];}
echo $OUTPUT->header();echo $OUTPUT->render_from_template('local_mcp/writeapis',['rows'=>$rows]);echo $OUTPUT->footer();
