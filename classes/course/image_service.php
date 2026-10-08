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
 * Course overview image integration through Moodle's File API.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\course;

use cache_helper;
use context_course;
use core\event\course_updated;
use curl;
use local_mcp\exception\api_exception;
use local_mcp\diagnostics;
use moodle_url;
use stored_file;

/**
 * Manage the same course/overviewfiles area used by Moodle's course image.
 */
final class image_service {
    /** Maximum upload size (5 MiB). */
    public const MAX_BYTES = 5242880;

    /** Maximum inline visual content sent back to MCP clients (2 MiB). */
    public const MAX_INLINE_BYTES = 2097152;

    /** Accepted raster image formats. */
    private const MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /**
     * List actual course overview images (no generated theme fallback).
     *
     * @param int $courseid Course ID.
     * @return stored_file[]
     */
    public static function images(int $courseid): array {
        $context = context_course::instance($courseid, MUST_EXIST);
        $files = get_file_storage()->get_area_files($context->id, 'course', 'overviewfiles', 0, 'sortorder ASC, id ASC', false);
        $images = [];
        foreach ($files as $file) {
            if ($file->is_valid_image()) {
                $images[] = $file;
            }
        }
        return $images;
    }

    /**
     * Export safe metadata and optional MCP image content for visual inspection.
     *
     * @param int $courseid Course ID.
     * @param bool $includeimage Attach the current image as an MCP image block.
     * @return array
     */
    public static function read(int $courseid, bool $includeimage = true): array {
        $files = self::images($courseid);
        $images = array_map([self::class, 'metadata'], $files);
        $result = [
            'courseid' => $courseid,
            'hasimage' => !empty($images),
            'image' => $images[0] ?? null,
            'images' => $images,
        ];
        if ($includeimage && $files) {
            $file = reset($files);
            if ($file->get_filesize() <= self::MAX_INLINE_BYTES && isset(self::MIMES[$file->get_mimetype()])) {
                // The protocol layer removes this internal key from structuredContent.
                $result['_mcp_content'] = [[
                    'type' => 'image',
                    'mimeType' => $file->get_mimetype(),
                    'data' => base64_encode($file->get_content()),
                ]];
            } else {
                $result['image_not_inlined'] = 'Current image exceeds the 2 MiB MCP image limit or uses an unsupported format.';
            }
        }
        return $result;
    }

    /**
     * Return metadata for an existing file.
     *
     * @param stored_file $file File in course/overviewfiles.
     * @return array
     */
    public static function metadata(stored_file $file): array {
        $url = moodle_url::make_pluginfile_url(
            $file->get_contextid(), 'course', 'overviewfiles', 0,
            $file->get_filepath(), $file->get_filename(), false
        );
        $width = null;
        $height = null;
        if ($file->get_filesize() <= self::MAX_BYTES) {
            $info = @getimagesizefromstring($file->get_content());
            if ($info) {
                $width = (int)$info[0];
                $height = (int)$info[1];
            }
        }
        return [
            'filename' => $file->get_filename(),
            'url' => $url->out(false),
            'mimetype' => $file->get_mimetype(),
            'filesize' => $file->get_filesize(),
            'width' => $width,
            'height' => $height,
            'contenthash' => $file->get_contenthash(),
            'timemodified' => $file->get_timemodified(),
        ];
    }

    /**
     * Validate and decode an image supplied by Base64 or a publicly accessible HTTPS URL.
     *
     * @param array $arguments MCP arguments.
     * @return array Tuple of binary data, MIME, file extension, width, height.
     */
    public static function decode(array $arguments): array {
        $hasbase64 = !empty($arguments['image_base64']);
        $hasurl = !empty($arguments['image_url']);
        if ($hasbase64 === $hasurl) {
            throw new api_exception('image_source_required', 400, 'Supply exactly one of image_base64 or image_url.');
        }
        diagnostics::event('course_image_source_received', [
            'courseid' => $arguments['courseid'] ?? null,
            'source' => $hasbase64 ? 'base64' : 'https_url',
            'source_bytes' => $hasbase64 ? strlen((string)$arguments['image_base64']) : null,
        ]);
        if ($hasbase64) {
            $encoded = (string)$arguments['image_base64'];
            if (preg_match('~^data:image/(?:png|jpeg|webp);base64,~i', $encoded, $match)) {
                $encoded = substr($encoded, strlen($match[0]));
            }
            if (strlen($encoded) > (int)ceil(self::MAX_BYTES * 4 / 3) + 16) {
                throw new api_exception('image_too_large', 400);
            }
            $binary = base64_decode($encoded, true);
            if ($binary === false) {
                throw new api_exception('invalid_image_base64', 400);
            }
        } else {
            $url = (string)$arguments['image_url'];
            $parts = parse_url($url);
            if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
                || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
                || (isset($parts['port']) && (int)$parts['port'] !== 443)
                || filter_var($url, FILTER_VALIDATE_URL) === false) {
                throw new api_exception('invalid_image_url', 400, 'Provide a public HTTPS image URL.');
            }
            // Restrict to DNS hostnames. Moodle's cURL security settings apply additional host/IP rules.
            $hostname = strtolower(rtrim((string)$parts['host'], '.'));
            if ($hostname === 'localhost' || str_ends_with($hostname, '.localhost')
                || str_ends_with($hostname, '.local') || filter_var(trim($hostname, '[]'), FILTER_VALIDATE_IP)) {
                throw new api_exception('invalid_image_url', 400, 'Use a public HTTPS hostname.');
            }
            // Redirects are disabled so a public URL cannot redirect into an internal resource.
            diagnostics::event('course_image_download_started', [
                'courseid' => $arguments['courseid'] ?? null, 'phase' => 'download',
                'source' => 'https_url',
            ]);
            $curl = new curl();
            $binary = $curl->get($url, [
                'CURLOPT_FOLLOWLOCATION' => false,
                'CURLOPT_MAXREDIRS' => 0,
                'CURLOPT_CONNECTTIMEOUT' => 5,
                'CURLOPT_TIMEOUT' => 25,
                'CURLOPT_MAXFILESIZE' => self::MAX_BYTES,
                'CURLOPT_SSL_VERIFYPEER' => true,
                'CURLOPT_SSL_VERIFYHOST' => 2,
            ]);
            $status = (int)($curl->get_info()['http_code'] ?? 0);
            diagnostics::event('course_image_download_finished', [
                'courseid' => $arguments['courseid'] ?? null,
                'phase' => 'download', 'download_status' => $status,
                'download_bytes' => is_string($binary) ? strlen($binary) : 0,
            ], $status === 200 ? 'INFO' : 'WARNING');
            if (!is_string($binary) || $status !== 200) {
                throw new api_exception('image_download_failed', 400, 'The image URL is not publicly downloadable.');
            }
        }
        if ($binary === '' || strlen($binary) > self::MAX_BYTES) {
            throw new api_exception('image_too_large', 400);
        }
        $info = @getimagesizefromstring($binary);
        if (!$info || empty($info['mime']) || !isset(self::MIMES[$info['mime']])) {
            throw new api_exception('unsupported_image', 400, 'Supported image types: PNG, JPEG and WebP.');
        }
        $width = (int)$info[0];
        $height = (int)$info[1];
        if ($width < 1 || $height < 1 || $width > 8192 || $height > 8192 || $width * $height > 40000000) {
            throw new api_exception('invalid_image_dimensions', 400);
        }
        diagnostics::event('course_image_decoded', [
            'courseid' => $arguments['courseid'] ?? null, 'phase' => 'decode',
            'source' => $hasbase64 ? 'base64' : 'https_url',
            'bytes' => strlen($binary), 'mime' => $info['mime'],
            'width' => $width, 'height' => $height,
        ]);
        return [$binary, $info['mime'], self::MIMES[$info['mime']], $width, $height];
    }

    /**
     * Replace only course overview images; preserve other files in this area.
     *
     * @param int $courseid Course ID.
     * @param array $arguments MCP arguments containing the image.
     * @param int $userid Acting Moodle user ID.
     * @return array New image metadata.
     */
    public static function replace(int $courseid, array $arguments, int $userid): array {
        global $DB;
        diagnostics::event('course_image_replace_started', [
            'tool' => 'set_course_image', 'courseid' => $courseid,
            'userid' => $userid, 'phase' => 'start',
            'source' => !empty($arguments['image_url']) ? 'https_url' : 'base64',
        ]);
        $context = context_course::instance($courseid, MUST_EXIST);
        $oldfiles = self::images($courseid);
        if (isset($arguments['expected_contenthash'])) {
            $currenthash = $oldfiles ? reset($oldfiles)->get_contenthash() : '';
            if (!hash_equals((string)$arguments['expected_contenthash'], $currenthash)) {
                throw new api_exception('course_image_changed', 409, 'The existing course image changed. Read it again.');
            }
        }
        try {
            [$bytes, $mime, $extension] = self::decode($arguments + ['courseid' => $courseid]);
        } catch (\Throwable $e) {
            diagnostics::exception($e, [
                'tool' => 'set_course_image', 'courseid' => $courseid, 'phase' => 'decode',
            ]);
            throw $e;
        }
        $filename = 'course-image-' . bin2hex(random_bytes(6)) . '.' . $extension;
        $record = [
            'contextid' => $context->id,
            'component' => 'course',
            'filearea' => 'overviewfiles',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
            'userid' => $userid,
            'mimetype' => $mime,
            'license' => 'allrightsreserved',
        ];
        diagnostics::event('course_image_storage_started', [
            'tool' => 'set_course_image', 'courseid' => $courseid, 'phase' => 'file_storage',
            'bytes' => strlen($bytes), 'mime' => $mime, 'existing_images' => count($oldfiles),
        ]);
        try {
            $transaction = $DB->start_delegated_transaction();
            foreach ($oldfiles as $oldfile) {
                $oldfile->delete();
            }
            $file = get_file_storage()->create_file_from_string($record, $bytes);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            diagnostics::exception($e, [
                'tool' => 'set_course_image', 'courseid' => $courseid, 'phase' => 'file_storage',
            ]);
            throw $e;
        }
        diagnostics::event('course_image_storage_committed', [
            'tool' => 'set_course_image', 'courseid' => $courseid, 'phase' => 'file_storage',
            'bytes' => $file->get_filesize(), 'mime' => $file->get_mimetype(),
            'replaced' => count($oldfiles),
        ]);
        try {
            cache_helper::purge_by_event('changesincourse');
            $course = get_course($courseid);
            course_updated::create([
                'objectid' => $courseid,
                'context' => $context,
                'other' => ['shortname' => $course->shortname, 'fullname' => $course->fullname],
            ])->trigger();
            $result = ['courseid' => $courseid, 'replaced' => count($oldfiles), 'image' => self::metadata($file)];
        } catch (\Throwable $e) {
            // File storage already committed: do not blindly retry before reading the current cover.
            diagnostics::exception($e, [
                'tool' => 'set_course_image', 'courseid' => $courseid, 'phase' => 'post_commit',
            ]);
            throw $e;
        }
        diagnostics::event('course_image_replace_completed', [
            'tool' => 'set_course_image', 'courseid' => $courseid,
            'phase' => 'completed', 'mime' => $mime, 'bytes' => $file->get_filesize(),
        ]);
        return $result;
    }
}
