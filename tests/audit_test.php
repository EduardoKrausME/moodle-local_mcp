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
 * Test privacy-safe MCP audit metadata.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\audit\logger;
use local_mcp\audit\summary;
use local_mcp\security\authenticated_identity;

/**
 * Audit metadata tests.
 */
final class audit_test extends advanced_testcase {
    /**
     * Summaries do not contain arbitrary input or result values.
     */
    public function test_summary_only_records_safe_identifiers_and_fields(): void {
        $input = ['courseid' => 17, 'summary' => '<p>Private data</p>',
            'image_base64' => 'secret123', 'confirmation_token' => 'mcp_confirm_secret123'];
        $result = ['id' => 17, 'fullname' => 'Private name', 'created_count' => 1,
            'created' => [['id' => 18]]];
        $metadata = summary::sanitise(summary::from_call('update_course', $input, $result));
        $json = json_encode($metadata);
        $this->assertStringNotContainsString('Private', $json);
        $this->assertStringNotContainsString('secret123', $json);
        $this->assertStringNotContainsString('confirmation_token', $json);
        $this->assertSame([17], $metadata['input_ids']['courseid']);
        $this->assertSame([17, 18], $metadata['result_ids']['id']);
        $this->assertSame(1, $metadata['counts']['created_count']);
    }

    /**
     * Arbitrary metadata never reaches the audit table.
     */
    public function test_metadata_allowlist_drops_secrets(): void {
        $safe = summary::sanitise([
            'prompt' => 'private conversation',
            'response' => 'private answer',
            'api_key' => 'secret',
            'names' => ['themedesignermode', 'dangerous.value'],
            'input_ids' => ['userid' => [7], 'token' => [42]],
            'error_code' => 'permission_denied',
        ]);
        $this->assertSame(['themedesignermode'], $safe['names']);
        $this->assertSame(['userid' => [7]], $safe['input_ids']);
        $this->assertArrayNotHasKey('prompt', $safe);
        $this->assertArrayNotHasKey('api_key', $safe);
    }

    /**
     * Audit records correlate with request diagnostics.
     */
    public function test_logger_stores_redacted_metadata(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $identity = new authenticated_identity((int)get_admin()->id, 'test', ['mcp:write']);
        logger::record('tool_called', $identity, 'create_course', 'write',
            \context_system::instance(), ['input_fields' => ['fullname'],
                'payload' => 'secret content', 'result_ids' => ['id' => [99]]],
            false, false, true, 'ok');
        $record = $DB->get_record('local_mcp_audit', ['tool' => 'create_course'], '*', MUST_EXIST);
        $metadata = json_decode($record->metadata, true);
        $this->assertSame([99], $metadata['result_ids']['id']);
        $this->assertArrayNotHasKey('payload', $metadata);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{12}$/', $metadata['request_id']);
    }
}
