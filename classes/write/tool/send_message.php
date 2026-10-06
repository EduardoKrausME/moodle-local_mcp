<?php
namespace local_mcp\write\tool;

defined('MOODLE_INTERNAL') || die;

final class send_message extends base_tool {
    public function get_name(): string { return 'send_message'; }
    public function get_title(): string { return 'Send message'; }
    public function get_description(): string { return 'Send a Moodle instant message from the connected user.'; }
    public function get_required_capability(): string { return 'moodle/site:sendmessage'; }
    public function get_input_schema(): array { return $this->object_schema([
        'touserid'=>['type'=>'integer','minimum'=>1],'message'=>['type'=>'string','minLength'=>1,'maxLength'=>10000]
    ],['touserid','message']); }
    public function resolve_context(array $arguments): \context { return \context_user::instance((int)$arguments['touserid'],MUST_EXIST); }
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        global $DB;
        $from=$DB->get_record('user',['id'=>$identity->userid,'deleted'=>0],'*',MUST_EXIST);
        $to=$DB->get_record('user',['id'=>(int)$arguments['touserid'],'deleted'=>0],'*',MUST_EXIST);
        $message=new \core\message\message();
        $message->component='moodle';
        $message->name='instantmessage';
        $message->userfrom=$from;
        $message->userto=$to;
        $message->subject='';
        $message->fullmessage=clean_param($arguments['message'],PARAM_TEXT);
        $message->fullmessageformat=FORMAT_PLAIN;
        $message->fullmessagehtml='';
        $message->smallmessage=$message->fullmessage;
        $message->notification=0;
        $id=message_send($message);
        return ['sent'=>true,'messageid'=>(int)$id];
    }
}
