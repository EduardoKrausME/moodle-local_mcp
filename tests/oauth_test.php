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
 * oauth_test.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

defined('MOODLE_INTERNAL') || die;

/**
 * Class oauth_test.
 */
final class oauth_test extends \advanced_testcase {
    /**
     * Method test_redirect_uri_requires_exact_match.
     *
     * @return void Return value.
     */
    public function test_redirect_uri_requires_exact_match(): void {
        $client = (object)['redirecturis' => json_encode(['https://client.example/callback'])];
        \local_mcp\oauth\client_service::validate_redirect_uri($client, 'https://client.example/callback');
        $this->expectException(\local_mcp\exception\api_exception::class);
        \local_mcp\oauth\client_service::validate_redirect_uri($client, 'https://client.example/callback/evil');
    }

    /**
     * Method test_authorization_request_rejects_non_s256.
     *
     * @return void Return value.
     */
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
