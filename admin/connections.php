<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * connections.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
