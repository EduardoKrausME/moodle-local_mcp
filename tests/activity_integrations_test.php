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
 * Activity MCP providers and upload validation regression tests.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use coding_exception;
use core_component;
use local_mcp\course\activity_upload;
use local_mcp\exception\api_exception;
use local_mcp\extension\write_provider_interface;
use local_mcp\write\registry;

/**
 * The six activity integrations must be installable and discoverable.
 *
 * @covers \local_mcp\write\registry
 * @covers \local_mcp\course\activity_upload
 * @covers \mcptool_h5p\write\create_activity
 * @covers \local_mcp\write\tool\activity_create_base
 */
final class activity_integrations_test extends advanced_testcase {
    /**
     * test_provider_registry_exposes_new_activity_tools
     *
     * @return void
     * @throws coding_exception
     */
    public function test_provider_registry_exposes_new_activity_tools(): void {
        $installed = core_component::get_plugin_list('mcptool');
        $tools = registry::get_tools();
        foreach (['resource', 'h5p', 'url', 'label', 'quiz', 'glossary'] as $name) {
            $this->assertArrayHasKey($name, $installed);
            $class = '\\mcptool_' . $name . '\\provider';
            $this->assertTrue(class_exists($class));
            $this->assertInstanceOf(write_provider_interface::class, new $class());
            $this->assertArrayHasKey($name . '_create_activity', $tools);
            $this->assertTrue($tools[$name . '_create_activity']->requires_confirmation());
            $schema = $tools[$name . '_create_activity']->get_input_schema();
            $this->assertSame('object', $schema['type']);
            $this->assertContains('courseid', $schema['required']);
            $this->assertContains('sectionnum', $schema['required']);
        }
        $this->assertArrayHasKey('quiz_add_question', $tools);
        $this->assertArrayHasKey('glossary_create_entry', $tools);
        foreach (['resource', 'h5p'] as $name) {
            $schema = $tools[$name . '_create_activity']->get_input_schema();
            $this->assertContains('file_base64', $schema['required']);
            $this->assertContains('filename', $schema['required']);
        }
    }

    /**
     * test_upload_decoder_accepts_base64_and_checks_h5p_type
     *
     * @return void
     * @throws api_exception
     */
    public function test_upload_decoder_accepts_base64_and_checks_h5p_type(): void {
        [$filename, $bytes] = activity_upload::decode(
            'data:application/pdf;base64,' . base64_encode('%PDF-1.7'), 'handout.pdf'
        );
        $this->assertSame('handout.pdf', $filename);
        $this->assertSame('%PDF-1.7', $bytes);
        $temporary = tempnam(sys_get_temp_dir(), "h5p-test-");
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($temporary, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString("h5p.json", '{"title":"Test","mainLibrary":"H5P.Text"}');
        $zip->addFromString("content/content.json", '{"text":"Sample content"}');
        $zip->close();
        try {
            [$name, $bytes] = activity_upload::decode(base64_encode(file_get_contents($temporary)), "lesson.h5p", true);
            $this->assertSame("lesson.h5p", $name);
            $this->assertStringStartsWith("PK", $bytes);
        } finally {
            unlink($temporary);
        }
    }

    /**
     * A forged ZIP signature must be rejected during preview, before any token is issued.
     *
     * @return void
     */
    public function test_invalid_h5p_archive_and_preview_are_rejected(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        try {
            activity_upload::decode(base64_encode("PK\\x03\\x04test"), "lesson.h5p", true);
            $this->fail("A truncated ZIP signature must not pass H5P package validation.");
        } catch (api_exception $e) {
            $this->assertSame("invalid_h5p_package", $e->machinecode);
        }

        $course = $this->getDataGenerator()->create_course();
        $tool = new \\mcptool_h5p\\write\\create_activity();
        $identity = new \\local_mcp\\security\\authenticated_identity((int)$USER->id, "test", ["mcp:write"]);
        try {
            $tool->preview([
                "courseid" => (int)$course->id, "sectionnum" => 0, "name" => "Invalid H5P",
                "filename" => "content.h5p", "file_base64" => base64_encode("test"),
            ], $identity);
            $this->fail("Non-ZIP H5P preview must fail.");
        } catch (api_exception $e) {
            $this->assertSame("invalid_h5p_package", $e->machinecode);
        }
    }

    /**
     * test_upload_decoder_rejects_traversal_and_unexpected_h5p
     *
     * @return void
     * @throws api_exception
     */
    public function test_upload_decoder_rejects_traversal_and_unexpected_h5p(): void {
        $this->expectException(api_exception::class);
        activity_upload::decode(base64_encode('1234'), '../payload.php');
    }

    /**
     * test_h5p_requires_package_extension
     *
     * @return void
     * @throws api_exception
     */
    public function test_h5p_requires_package_extension(): void {
        $this->expectException(api_exception::class);
        activity_upload::decode(base64_encode("PK\x03\x04test"), 'lesson.zip', true);
    }
}
