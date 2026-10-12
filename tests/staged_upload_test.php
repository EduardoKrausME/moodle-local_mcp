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
 * Tests for short-lived binary uploads.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\course\staged_upload;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;

/**
 * Uploaded references must never cross OAuth connections.
 */
final class staged_upload_test extends advanced_testcase {
    public function test_file_is_private_expires_and_can_be_used_as_draft(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $identity = new authenticated_identity($user->id, 'oauth', ['mcp:write'], 1, 2, 3);
        $other = new authenticated_identity($user->id, 'oauth', ['mcp:write'], 1, 9, 4);
        $tmp = tempnam(make_temp_directory('local_mcp_test'), 'upload_');
        file_put_contents($tmp, 'Moodle upload from a binary HTTP request');
        try {
            $result = staged_upload::store($tmp, 'guide.txt', $identity);
            $this->assertStringStartsWith('mcp_file_', $result['upload_id']);
            $this->assertSame('guide.txt', staged_upload::resolve($result['upload_id'], $identity)->get_filename());
            $this->assertSame('Moodle upload from a binary HTTP request',
                staged_upload::bytes($result['upload_id'], $identity, staged_upload::MAX_BYTES));
            $draftid = staged_upload::create_draft($result['upload_id'], $identity);
            $this->assertGreaterThan(0, $draftid);

            try {
                staged_upload::resolve($result['upload_id'], $other);
                $this->fail('Cross-connection file access should be denied.');
            } catch (api_exception $e) {
                $this->assertSame('upload_not_found', $e->machinecode);
            }
            $DB->set_field('local_mcp_upload', 'expiresat', time() - 10,
                ['tokenhash' => hash('sha256', $result['upload_id'])]);
            try {
                staged_upload::resolve($result['upload_id'], $identity);
                $this->fail('Expired files should be rejected.');
            } catch (api_exception $e) {
                $this->assertSame('upload_expired', $e->machinecode);
            }
            staged_upload::cleanup();
            $this->assertFalse($DB->record_exists('local_mcp_upload',
                ['tokenhash' => hash('sha256', $result['upload_id'])]));
        } finally {
            @unlink($tmp);
        }
    }

    public function test_invalid_filename_and_size(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $identity = new authenticated_identity($user->id, 'oauth', ['mcp:write'], 1, 2);
        $tmp = tempnam(make_temp_directory('local_mcp_test'), 'upload_');
        file_put_contents($tmp, 'ok');
        try {
            $this->expectException(api_exception::class);
            staged_upload::store($tmp, '../secret.txt', $identity);
        } finally {
            @unlink($tmp);
        }
    }
}
