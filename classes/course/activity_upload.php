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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Safe Base64 upload into the Moodle user draft file area.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\course;

use context_user;
use local_mcp\exception\api_exception;

/** Convert a single file payload into a Moodle draft item, for use by add_moduleinfo(). */
final class activity_upload {
    /** Maximum decoded upload size for resource files and H5P packages (40 MiB). */
    public const MAX_BYTES = 41943040;

    /**
     * Decode and validate an MCP file payload without external URL fetching.
     *
     * @param string $encoded Base64 bytes, optionally prefixed with a data URI.
     * @param string $filename Original filename.
     * @param bool $h5p Whether the file must be a ZIP-based H5P package.
     * @return array{string,string} Safe filename and binary.
     */
    public static function decode(string $encoded, string $filename, bool $h5p = false): array {
        if ($encoded === '' || $filename === '' || strpbrk($filename, '/\\') !== false) {
            throw new api_exception('invalid_upload', 400, 'A filename and Base64 file content are required.');
        }
        $cleanfilename = clean_param($filename, PARAM_FILE);
        if ($cleanfilename === '' || strlen($cleanfilename) > 255 || $cleanfilename !== $filename) {
            throw new api_exception('invalid_filename', 400);
        }
        if ($h5p && strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'h5p') {
            throw new api_exception('invalid_h5p_extension', 400, 'A .h5p file is required.');
        }
        if (preg_match('~^data:[^;,]+;base64,~i', $encoded, $match)) {
            $encoded = substr($encoded, strlen($match[0]));
        }
        if (strlen($encoded) > (int)ceil(self::MAX_BYTES * 4 / 3) + 8) {
            throw new api_exception('upload_too_large', 400);
        }
        $binary = base64_decode($encoded, true);
        if ($binary === false || $binary === '' || strlen($binary) > self::MAX_BYTES) {
            throw new api_exception('invalid_upload', 400, 'Invalid Base64 or file over 40 MiB.');
        }
        if ($h5p && !str_starts_with($binary, "PK\x03\x04")) {
            throw new api_exception('invalid_h5p_package', 400, 'H5P packages must be ZIP files.');
        }
        return [$cleanfilename, $binary];
    }

    /**
     * Create a temporary user draft file, subsequently moved by the module File API.
     *
     * @param string $encoded Base64 data.
     * @param string $filename File name.
     * @param int $userid Authenticated user.
     * @param bool $h5p Require H5P ZIP.
     * @return int Draft item ID.
     */
    public static function create_draft(string $encoded, string $filename, int $userid, bool $h5p = false): int {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        [$filename, $binary] = self::decode($encoded, $filename, $h5p);
        $draftid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => context_user::instance($userid)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftid,
            'filepath' => '/',
            'filename' => $filename,
            'userid' => $userid,
            'license' => 'allrightsreserved',
            'author' => fullname(\core_user::get_user($userid)),
        ], $binary);
        return $draftid;
    }
}
