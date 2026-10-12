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
 * Short-lived, OAuth-bound binary uploads for MCP tools.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\course;

use context_user;
use core\lock\lock_config;
use core_user;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\security\scope;
use local_mcp\security\secret;
use stored_file;

/**
 * Store incoming HTTPS file transfers in a private Moodle File API area.
 *
 * Never expose Moodle draft item IDs or file paths as public MCP references.
 */
final class staged_upload {
    /** Maximum accepted bytes in a single binary upload. */
    public const MAX_BYTES = 41943040;

    /** Uploaded bytes expire after one hour. */
    public const TTL = 3600;

    /** Bound the unexpired storage allocation for one OAuth connection. */
    private const MAX_ACTIVE_BYTES = 104857600;

    /** Bound the number of unexpired uploads for one OAuth connection. */
    private const MAX_ACTIVE_FILES = 50;

    /**
     * Verify a filename exactly, rather than silently rewriting it.
     *
     * @param string $filename User-provided filename.
     * @return string
     */
    public static function validate_filename(string $filename): string {
        if ($filename === '' || strpbrk($filename, '/\\') !== false
                || strlen($filename) > 255 || clean_param($filename, PARAM_FILE) !== $filename
                || $filename === '.' || $filename === '..') {
            throw new api_exception('invalid_filename', 400);
        }
        return $filename;
    }

    /**
     * Persist the bytes of a PHP-validated upload, associated with one OAuth connection.
     *
     * @param string $path Absolute temporary file path supplied by PHP.
     * @param string $filename File basename.
     * @param authenticated_identity $identity OAuth bearer identity.
     * @return array Public, non-sensitive upload receipt.
     */
    public static function store(string $path, string $filename, authenticated_identity $identity): array {
        global $DB;
        self::check_identity($identity);
        $filename = self::validate_filename($filename);
        $bytes = is_file($path) ? filesize($path) : false;
        if ($bytes === false || $bytes < 1 || $bytes > self::MAX_BYTES) {
            throw new api_exception('upload_too_large', 413, 'File must be between 1 byte and 40 MiB.');
        }

        $lock = lock_config::get_lock_factory('local_mcp')->get_lock(
            'upload_connection_' . $identity->connectionid, 10);
        if (!$lock) {
            throw new api_exception('upload_busy', 503, 'Could not lock the connection upload quota.');
        }
        try {
            // This quota is supplemental to HTTP size limits and per-token rate limiting.
            $existing = $DB->get_records_select('local_mcp_upload',
                'userid = ? AND connectionid = ? AND expiresat > ?',
                [$identity->userid, $identity->connectionid, time()], '', 'id, filesize');
            $total = 0;
            foreach ($existing as $row) {
                $total += (int)$row->filesize;
            }
            if (count($existing) >= self::MAX_ACTIVE_FILES || $total + $bytes > self::MAX_ACTIVE_BYTES) {
                throw new api_exception('upload_quota_exceeded', 429);
            }

            $now = time();
            $token = secret::generate('mcp_file_', 32);
            $record = (object)[
                'tokenhash' => secret::hash($token),
                'userid' => $identity->userid,
                'clientid' => $identity->clientid,
                'connectionid' => $identity->connectionid,
                'filename' => $filename,
                'filesize' => $bytes,
                'timecreated' => $now,
                'expiresat' => $now + self::TTL,
            ];
            $transaction = $DB->start_delegated_transaction();
            $record->id = $DB->insert_record('local_mcp_upload', $record);
            $file = get_file_storage()->create_file_from_pathname([
                'contextid' => context_user::instance($identity->userid)->id,
                'component' => 'local_mcp',
                'filearea' => 'uploads',
                'itemid' => $record->id,
                'filepath' => '/',
                'filename' => $filename,
                'userid' => $identity->userid,
                'license' => 'allrightsreserved',
                'author' => fullname(core_user::get_user($identity->userid)),
            ], $path);
            $transaction->allow_commit();

            return [
                'upload_id' => $token,
                'filename' => $filename,
                'mime_type' => $file->get_mimetype(),
                'size_bytes' => (int)$file->get_filesize(),
                'expires_at' => $record->expiresat,
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * Resolve only within the same user, OAuth client and connection.
     *
     * @param string $uploadid Opaque upload reference.
     * @param authenticated_identity $identity Current OAuth connection.
     * @param int $maxbytes Consumer-specific maximum size.
     * @return stored_file
     */
    public static function resolve(string $uploadid, authenticated_identity $identity,
                                   int $maxbytes = self::MAX_BYTES): stored_file {
        global $DB;
        self::check_identity($identity);
        if (!preg_match('/^mcp_file_[A-Za-z0-9_-]{43}$/D', $uploadid)) {
            throw new api_exception('invalid_upload_id', 400);
        }
        $record = $DB->get_record('local_mcp_upload', ['tokenhash' => secret::hash($uploadid)]);
        // A missing reference and one belonging to another user/client are indistinguishable.
        if (!$record || (int)$record->userid !== $identity->userid
                || (int)$record->clientid !== $identity->clientid
                || (int)$record->connectionid !== $identity->connectionid) {
            throw new api_exception('upload_not_found', 404);
        }
        if ((int)$record->expiresat <= time()) {
            throw new api_exception('upload_expired', 410);
        }
        if ((int)$record->filesize > $maxbytes) {
            throw new api_exception('upload_too_large', 413);
        }
        $file = get_file_storage()->get_file(
            context_user::instance($identity->userid)->id, 'local_mcp', 'uploads',
            (int)$record->id, '/', $record->filename
        );
        if (!$file || $file->is_directory() || (int)$file->get_filesize() !== (int)$record->filesize) {
            throw new api_exception('upload_not_found', 404);
        }
        return $file;
    }

    /**
     * Copy the validated upload to the user's draft area for Moodle module APIs.
     *
     * @param string $uploadid OAuth-bound reference.
     * @param authenticated_identity $identity Current user.
     * @param bool $h5p Require a valid H5P ZIP header and filename.
     * @return int Fresh Moodle draft item ID.
     */
    public static function create_draft(string $uploadid, authenticated_identity $identity,
                                        bool $h5p = false): int {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $source = self::resolve($uploadid, $identity);
        if ($h5p) {
            if (strtolower(pathinfo($source->get_filename(), PATHINFO_EXTENSION)) !== 'h5p') {
                throw new api_exception('invalid_h5p_extension', 400);
            }
            $handle = $source->get_content_file_handle();
            $header = $handle ? fread($handle, 4) : false;
            if (is_resource($handle)) {
                fclose($handle);
            }
            if ($header !== "PK\x03\x04") {
                throw new api_exception('invalid_h5p_package', 400);
            }
        }
        $draftid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_storedfile([
            'contextid' => context_user::instance($identity->userid)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftid,
            'filepath' => '/',
            'filename' => $source->get_filename(),
            'userid' => $identity->userid,
        ], $source);
        return $draftid;
    }

    /**
     * Read image-sized bytes only for a consumer that has already checked course capabilities.
     *
     * @param string $uploadid Upload reference.
     * @param authenticated_identity $identity Current identity.
     * @param int $maxbytes Consumer-specific size limit.
     * @return string
     */
    public static function bytes(string $uploadid, authenticated_identity $identity, int $maxbytes): string {
        return self::resolve($uploadid, $identity, $maxbytes)->get_content();
    }

    /**
     * Remove expired blobs as well as their database references.
     *
     * @return void
     */
    public static function cleanup(): void {
        global $DB;
        $records = $DB->get_records_select('local_mcp_upload', 'expiresat <= ?', [time()]);
        foreach ($records as $record) {
            self::delete_record($record);
        }
    }

    /**
     * Erase uploads belonging to a specific Moodle user (privacy requests).
     *
     * @param int $userid Moodle user ID.
     * @return void
     */
    public static function delete_user(int $userid): void {
        global $DB;
        foreach ($DB->get_records('local_mcp_upload', ['userid' => $userid]) as $record) {
            self::delete_record($record);
        }
    }

    /**
     * Delete private stored bytes before removing the metadata row.
     *
     * @param object $record Upload database row.
     * @return void
     */
    private static function delete_record(object $record): void {
        global $DB;
        $context = context_user::instance((int)$record->userid, IGNORE_MISSING);
        if ($context) {
            $file = get_file_storage()->get_file($context->id, 'local_mcp', 'uploads',
                (int)$record->id, '/', $record->filename);
            if ($file) {
                $file->delete();
            }
        }
        $DB->delete_records('local_mcp_upload', ['id' => $record->id]);
    }

    /**
     * Only enabled, write-authorized OAuth connections may transfer or use files.
     *
     * @param authenticated_identity $identity Current user.
     * @return void
     */
    private static function check_identity(authenticated_identity $identity): void {
        if ($identity->type !== 'oauth' || !$identity->has_scope(scope::WRITE)
                || !$identity->clientid || !$identity->connectionid) {
            throw new api_exception('insufficient_scope', 403);
        }
    }
}
