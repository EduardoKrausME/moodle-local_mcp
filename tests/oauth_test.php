<?php
namespace local_mcp;

defined('MOODLE_INTERNAL') || die;

final class oauth_test extends \advanced_testcase {
    public function test_redirect_uri_requires_exact_match(): void {
        $client = (object)['redirecturis' => json_encode(['https://client.example/callback'])];
        \local_mcp\oauth\client_service::validate_redirect_uri($client, 'https://client.example/callback');
        $this->expectException(\local_mcp\exception\api_exception::class);
        \local_mcp\oauth\client_service::validate_redirect_uri($client, 'https://client.example/callback/evil');
    }

    public function test_authorization_request_rejects_non_s256(): void {
        $this->resetAfterTest();
        global $DB;
        $clientid = 'test_' . bin2hex(random_bytes(4));
        $DB->insert_record('local_mcp_oauth_client', (object)[
            'clientid'=>$clientid,'name'=>'Test','description'=>'','clienturi'=>'https://client.example',
            'redirecturis'=>json_encode(['https://client.example/callback']),'enabled'=>1,'clienttype'=>'public',
            'dynamic'=>0,'timecreated'=>time(),'timemodified'=>time(),'lastused'=>null,
        ]);
        $this->expectException(\local_mcp\exception\api_exception::class);
        \local_mcp\oauth\authorization_service::validate_request([
            'client_id'=>$clientid,'redirect_uri'=>'https://client.example/callback','response_type'=>'code',
            'scope'=>'mcp:read','state'=>'state','code_challenge'=>str_repeat('a',43),'code_challenge_method'=>'plain',
        ]);
    }
}
