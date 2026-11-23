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
 * Tests for server-side image copy between Page and MCP activities.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use context_module;
use local_mcp\course\activity_image_copier;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\write\registry;

/**
 * @covers \local_mcp\course\activity_image_copier
 * @covers \local_mcp\write\tool\copy_activity_images
 */
final class copy_activity_images_test extends advanced_testcase {
    /** A valid 1x1 PNG (the binary never travels through the MCP tool). */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGP4z8DwHwAFAAH/iZk9HQAAAABJRU5ErkJggg==';

    /**
     * @return array Course, source page, destination MCP activity, admin identity.
     */
    private function setup_modules(): array {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        if (!$DB->record_exists('modules', ['name' => 'mcp'])) {
            $this->markTestSkipped('mod_mcp is not installed on this Moodle site.');
        }
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id,
            'name' => 'Original page', 'content' => '<p>Content containing a figure</p>']);
        $mcp = $this->getDataGenerator()->create_module('mcp', ['course' => $course->id,
            'name' => 'Migration draft'], ['visible' => 0]);

        return [$course, $page, $mcp, new authenticated_identity(2, 'test', ['mcp:write'])];
    }

    /**
     * @param int $cmid Source course-module ID.
     * @param string $filename Filename to attach.
     * @param string $area Source file area.
     * @param string $bytes File bytes.
     * @return \stored_file Created Moodle file.
     */
    private function add_image(int $cmid, string $filename = 'figura-1.png',
            string $area = 'content', string $bytes = ''): \stored_file {
        if ($bytes === '') {
            $bytes = base64_decode(self::PNG);
        }
        $context = context_module::instance($cmid);
        return get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => 'mod_page', 'filearea' => $area,
            'itemid' => 0, 'filepath' => '/', 'filename' => $filename,
            'mimetype' => 'image/png', 'userid' => 2,
        ], $bytes);
    }

    /**
     * Preview writes nothing, copy is repeatable, and original activity content remains untouched.
     */
    public function test_copy_and_idempotent_reuse_without_affecting_html(): void {
        global $DB;
        [, $page, $mcp, $identity] = $this->setup_modules();
        $source = $this->add_image((int)$page->cmid);
        $arguments = ['sourcecmid' => (int)$page->cmid, 'destinationcmid' => (int)$mcp->cmid];

        $tools = registry::get_tools();
        $this->assertArrayHasKey('copy_activity_images', $tools);
        $tool = $tools['copy_activity_images'];
        $this->assertTrue($tool->requires_confirmation());
        $this->assertTrue($tool->supports_dry_run());
        $this->assertSame('mod/mcp:manage', $tool->get_required_capability());

        $preview = $tool->preview($arguments, $identity);
        $this->assertSame(1, $preview['would_copy']);
        $this->assertSame('copy', $preview['images'][0]['action']);
        $this->assertSame('@@PLUGINFILE@@/figura-1.png', $preview['images'][0]['source_placeholder']);

        $targetcontext = context_module::instance((int)$mcp->cmid);
        $storage = get_file_storage();
        $this->assertFalse($storage->file_exists($targetcontext->id, 'mod_mcp', 'assets',
            0, '/', 'figura-1.png'));

        $result = $tool->execute($arguments + [
            'expected_manifest_sha256' => $preview['manifest_sha256'],
        ], $identity);
        $this->assertSame(1, $result['copied']);
        $this->assertSame(0, $result['reused']);
        $this->assertStringContainsString('/mod_mcp/assets/', $result['images'][0]['destination_url']);
        $destination = $storage->get_file($targetcontext->id, 'mod_mcp', 'assets', 0, '/', 'figura-1.png');
        $this->assertNotFalse($destination);
        $this->assertSame($source->get_contenthash(), $destination->get_contenthash());

        $again = $tool->execute($arguments, $identity);
        $this->assertSame(0, $again['copied']);
        $this->assertSame(1, $again['reused']);
        $original = $DB->get_record('page', ['id' => $page->id], '*', MUST_EXIST);
        $draft = $DB->get_record('mcp', ['id' => $mcp->id], '*', MUST_EXIST);
        $this->assertSame('<p>Content containing a figure</p>', $original->content);
        $this->assertSame(0, (int)$draft->draftrevision);
        $this->assertSame(0, (int)$draft->publishedrevision);
    }

    /**
     * Never overwrite a different image with the same name.
     */
    public function test_name_conflicts_result_in_a_safe_new_filename(): void {
        [, $page, $mcp, $identity] = $this->setup_modules();
        $source = $this->add_image((int)$page->cmid);
        $targetcontext = context_module::instance((int)$mcp->cmid);
        get_file_storage()->create_file_from_string([
            'contextid' => $targetcontext->id, 'component' => 'mod_mcp',
            'filearea' => 'assets', 'itemid' => 0, 'filepath' => '/',
            'filename' => 'figura-1.png', 'mimetype' => 'image/png', 'userid' => 2,
        ], 'different original image data');

        $result = activity_image_copier::copy([
            'sourcecmid' => (int)$page->cmid, 'destinationcmid' => (int)$mcp->cmid,
        ], $identity->userid);
        $filename = $result['images'][0]['destination_filename'];
        $this->assertNotEquals('figura-1.png', $filename);
        $this->assertStringStartsWith('figura-1-', $filename);
        $copied = get_file_storage()->get_file($targetcontext->id, 'mod_mcp', 'assets', 0, '/', $filename);
        $this->assertNotFalse($copied);
        $this->assertSame($source->get_contenthash(), $copied->get_contenthash());
        $this->assertSame(1, $result['copied']);
    }

    /**
     * A single-image selection is honored, and source changes invalidate an old manifest.
     */
    public function test_single_filename_filter_and_manifest_conflict(): void {
        [, $page, $mcp, $identity] = $this->setup_modules();
        $this->add_image((int)$page->cmid, 'figura-1.png');
        $arguments = ['sourcecmid' => (int)$page->cmid, 'destinationcmid' => (int)$mcp->cmid];

        $preview = activity_image_copier::preview($arguments, $identity->userid);
        $this->add_image((int)$page->cmid, 'figura-2.png');

        try {
            activity_image_copier::copy($arguments + [
                'expected_manifest_sha256' => $preview['manifest_sha256'],
            ], $identity->userid);
            $this->fail('The tool must reject a changed source manifest.');
        } catch (api_exception $exception) {
            $this->assertSame(409, $exception->httpstatus);
            $this->assertSame('image_manifest_changed', $exception->machinecode);
        }

        $copy = activity_image_copier::copy($arguments + ['source_filename' => 'figura-1.png'],
            $identity->userid);
        $this->assertCount(1, $copy['images']);
        $this->assertSame('figura-1.png', $copy['images'][0]['source_filename']);
    }

    /**
     * Having read permission alone never grants permission to copy into an MCP activity.
     */
    public function test_student_cannot_copy_page_images(): void {
        [$course, $page, $mcp] = $this->setup_modules();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->add_image((int)$page->cmid);

        $this->expectException(api_exception::class);
        activity_image_copier::copy([
            'sourcecmid' => (int)$page->cmid, 'destinationcmid' => (int)$mcp->cmid,
        ], (int)$student->id);
    }

    /**
     * Page READ preserves the src attribute; WRITE can copy attached and
     * explicitly referenced course images without publishing either activity.
     */
    public function test_page_html_manifest_and_course_summary_reference_copy(): void {
        global $DB;
        [$course, $page, $mcp, $identity] = $this->setup_modules();
        $this->add_image((int)$page->cmid);
        $coursecontext = \context_course::instance((int)$course->id);
        $summary = get_file_storage()->create_file_from_string([
            'contextid' => $coursecontext->id, 'component' => 'course',
            'filearea' => 'summary', 'itemid' => 0, 'filepath' => '/',
            'filename' => 'course-figure.png', 'mimetype' => 'image/png', 'userid' => 2,
        ], base64_decode(self::PNG));
        $summaryurl = \moodle_url::make_pluginfile_url($coursecontext->id, 'course',
            'summary', 0, '/', 'course-figure.png')->out(false);
        $html = '<h2>Objectives</h2><img src="@@PLUGINFILE@@/figura-1.png">'
            . '<img src="' . s($summaryurl) . '">';
        $DB->set_field('page', 'content', $html, ['id' => $page->id]);

        $read = new \mcptool_page\read\get_content();
        $result = $read->execute(['cmid' => (int)$page->cmid], $identity);
        $this->assertSame($html, $result['html']);
        $this->assertStringNotContainsString('<img', $result['content']);
        $this->assertSame(1, $result['htmlformat']);
        $this->assertCount(2, $result['images']);
        $this->assertSame('resolved', $result['images'][0]['status']);
        $this->assertSame('@@PLUGINFILE@@/figura-1.png', $result['images'][0]['src']);
        $this->assertSame($summaryurl, $result['images'][1]['src']);
        $this->assertSame('course', $result['images'][1]['component']);
        $this->assertSame((int)$summary->get_id(), $result['images'][1]['fileid']);

        $args = ['sourcecmid' => (int)$page->cmid, 'destinationcmid' => (int)$mcp->cmid];
        $preview = activity_image_copier::preview($args, $identity->userid);
        $this->assertSame(2, $preview['would_copy']);
        $copied = activity_image_copier::copy($args + [
            'expected_manifest_sha256' => $preview['manifest_sha256'],
        ], $identity->userid);
        $this->assertSame(2, $copied['copied']);
        $this->assertCount(2, $copied['images']);

        $destinationcontext = context_module::instance((int)$mcp->cmid);
        $destination = get_file_storage()->get_file($destinationcontext->id, 'mod_mcp',
            'assets', 0, '/', 'course-figure.png');
        $this->assertNotFalse($destination);
        $this->assertSame($summary->get_contenthash(), $destination->get_contenthash());
        $this->assertSame($html, $DB->get_field('page', 'content', ['id' => $page->id]));
        $this->assertSame(0, (int)$DB->get_field('mcp', 'draftrevision', ['id' => $mcp->id]));
    }

    /**
     * A forged pluginfile URL does not grant access to files from another course.
     * External URLs and path traversal must not be fetched or copied.
     */
    public function test_external_cross_course_and_traversal_references_are_not_copied(): void {
        global $DB;
        [, $page, $mcp, $identity] = $this->setup_modules();
        $othercourse = $this->getDataGenerator()->create_course();
        $context = \context_course::instance((int)$othercourse->id);
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => 'course', 'filearea' => 'summary',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'secret.png',
            'mimetype' => 'image/png', 'userid' => 2,
        ], base64_decode(self::PNG));
        $url = \moodle_url::make_pluginfile_url($context->id, 'course',
            'summary', 0, '/', 'secret.png')->out(false);
        $html = '<img src="' . s($url) . '">'
            . '<img src="https://untrusted.example/image.png">'
            . '<img src="/pluginfile.php/' . $context->id . '/course/summary/%2e%2e/secret.png">';
        $DB->set_field('page', 'content', $html, ['id' => $page->id]);

        $manifest = \local_mcp\course\page_image_manifest::inspect((int)$page->cmid, $identity->userid);
        $this->assertCount(3, $manifest);
        foreach ($manifest as $entry) {
            $this->assertNotEquals('resolved', $entry['status']);
            $this->assertArrayNotHasKey('fileid', $entry);
        }
        $args = ['sourcecmid' => (int)$page->cmid, 'destinationcmid' => (int)$mcp->cmid];
        $preview = activity_image_copier::preview($args, $identity->userid);
        $this->assertSame(0, $preview['would_copy']);
        $this->assertSame([], $preview['images']);
    }

    /**
     * Course section images need a real section ID in the original course.
     */
    public function test_explicit_course_section_reference_can_be_copied(): void {
        global $DB;
        [$course, $page, $mcp, $identity] = $this->setup_modules();
        $section = $DB->get_record('course_sections',
            ['course' => $course->id, 'section' => 0], 'id', MUST_EXIST);
        $context = \context_course::instance((int)$course->id);
        $file = get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => 'course', 'filearea' => 'section',
            'itemid' => $section->id, 'filepath' => '/', 'filename' => 'section.png',
            'mimetype' => 'image/png', 'userid' => 2,
        ], base64_decode(self::PNG));
        $url = \moodle_url::make_pluginfile_url($context->id, 'course',
            'section', $section->id, '/', 'section.png')->out(false);
        $DB->set_field('page', 'content', '<img src="' . s($url) . '">', ['id' => $page->id]);

        $preview = activity_image_copier::preview([
            'sourcecmid' => (int)$page->cmid, 'destinationcmid' => (int)$mcp->cmid,
        ], $identity->userid);
        $this->assertSame(1, $preview['would_copy']);
        $this->assertSame('course', $preview['images'][0]['source_component']);
        $this->assertSame('section', $preview['images'][0]['source_filearea']);
    }
}
