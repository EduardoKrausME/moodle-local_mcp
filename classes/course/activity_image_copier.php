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
 * Server-side Moodle activity image copying through the native File API.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\course;

use context_module;
use local_mcp\exception\api_exception;
use local_mcp\security\capability_guard;
use moodle_url;
use stored_file;

/**
 * Copy images from a Page into mod_mcp/assets without transferring bytes through an MCP client.
 *
 * Stored Page attachments and explicitly referenced images in supported areas of the
 * same course can be copied into mod_mcp/assets. Arbitrary URLs are never fetched.
 */
final class activity_image_copier {
    /** Keep the same per-file media size limit as mod_mcp's upload tool. */
    private const MAX_FILE_BYTES = 8388608;

    /** Bound the work done by one synchronous MCP request. */
    private const MAX_IMAGES = 100;

    /** File types accepted by mod_mcp's asset service. */
    private const MIME_EXTENSIONS = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
    ];

    /**
     * Check the real source and target modules and both authorization contexts.
     *
     * @param array $arguments Validated MCP tool arguments.
     * @param int $userid Acting Moodle user.
     * @return array Source module, destination module, source context, destination context.
     */
    private static function contexts(array $arguments, int $userid): array {
        $sourcecm = get_coursemodule_from_id('page', (int)$arguments['sourcecmid'], 0, false, MUST_EXIST);
        $destinationcm = get_coursemodule_from_id('mcp', (int)$arguments['destinationcmid'], 0, false, MUST_EXIST);
        $sourcecontext = context_module::instance((int)$sourcecm->id, MUST_EXIST);
        $destinationcontext = context_module::instance((int)$destinationcm->id, MUST_EXIST);

        // The caller must be permitted to read the source and administer both modules.
        capability_guard::check('mod/page:view', $sourcecontext, $userid);
        capability_guard::check('moodle/course:manageactivities', $sourcecontext, $userid);
        capability_guard::check('mod/mcp:manage', $destinationcontext, $userid);

        return [$sourcecm, $destinationcm, $sourcecontext, $destinationcontext];
    }

    /**
     * Generate a stable destination name, avoiding accidental replacement of different images.
     *
     * @param stored_file $source Original image.
     * @param \context_module $destinationcontext Destination context.
     * @param array $reserved Already planned destinations.
     * @return array Filename and action (copy or reuse).
     */
    private static function destination_name(stored_file $source, context_module $destinationcontext,
            array &$reserved): array {
        $storage = get_file_storage();
        $path = $source->get_filepath();
        $original = $source->get_filename();
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $basename = pathinfo($original, PATHINFO_FILENAME);
        $hash = $source->get_contenthash();

        // A filename conflict is resolved by suffixing the content hash, not by overwriting.
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $filename = $attempt === 0 ? $original :
                \core_text::substr($basename, 0, 150) . '-' . substr($hash, 0, min(8 + $attempt, 40)) . '.' . $extension;
            $key = $path . $filename;
            if (isset($reserved[$key])) {
                if (hash_equals($reserved[$key], $hash)) {
                    return [$filename, 'reuse'];
                }
                continue;
            }
            $existing = $storage->get_file($destinationcontext->id, 'mod_mcp', 'assets', 0, $path, $filename);
            if ($existing) {
                if (hash_equals($existing->get_contenthash(), $hash)) {
                    $reserved[$key] = $hash;
                    return [$filename, 'reuse'];
                }
                continue;
            }
            $reserved[$key] = $hash;
            return [$filename, 'copy'];
        }
        throw new api_exception('image_filename_conflict', 409);
    }

    /**
     * Create an authorization-checked manifest. This method never writes files.
     *
     * @param array $arguments Source and destination CMIDs and optional filters.
     * @param int $userid Acting Moodle user.
     * @return array Manifest with internal source-file identifiers.
     */
    private static function plan(array $arguments, int $userid): array {
        [, , $sourcecontext, $destinationcontext] = self::contexts($arguments, $userid);
        $area = $arguments['source_filearea'] ?? 'all';
        if (!in_array($area, ['all', 'content', 'intro'], true)) {
            throw new api_exception('invalid_source_filearea', 400);
        }
        $filenamefilter = $arguments['source_filename'] ?? null;
        if ($filenamefilter !== null && ($filenamefilter === '' ||
                clean_param($filenamefilter, PARAM_FILE) !== $filenamefilter)) {
            throw new api_exception('invalid_source_filename', 400);
        }

        global $DB;
        $areas = $area === 'all' ? ['content', 'intro'] : [$area];
        $entries = [];
        $skipped = [];
        $reserved = [];
        $foundmatchingname = false;
        $candidates = [];

        // Preserve the original behaviour for stored Page attachments.
        foreach ($areas as $filearea) {
            $stored = get_file_storage()->get_area_files($sourcecontext->id, 'mod_page',
                $filearea, 0, 'filepath ASC, filename ASC', false);
            foreach ($stored as $file) {
                $candidates[(int)$file->get_id()] = ['file' => $file, 'references' => []];
            }
        }

        // A Page may refer to another authorised Moodle file area without owning the bytes.
        // Resolve only image src attributes actually present in the stored Page HTML.
        $page = $DB->get_record('page', ['id' => $sourcecm->instance], 'id,content', MUST_EXIST);
        $manifest = page_image_manifest::collect($sourcecm, $sourcecontext, (string)$page->content, $userid);
        foreach ($manifest['files'] as $reference) {
            $file = $reference['file'];
            if ($area !== 'all' && !in_array($file->get_filearea(), $areas, true)) {
                continue;
            }
            $id = (int)$file->get_id();
            if (isset($candidates[$id])) {
                $candidates[$id]['references'] = $reference['references'];
            } else {
                $candidates[$id] = $reference;
            }
        }

        foreach ($candidates as $candidate) {
            $file = $candidate['file'];
            $filearea = $file->get_filearea();
            if ($filenamefilter !== null && $filenamefilter !== $file->get_filename()) {
                continue;
            }
            $foundmatchingname = true;
            $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
            $mime = $file->get_mimetype();
            $reason = null;
            if (!isset(self::MIME_EXTENSIONS[$mime]) ||
                    !in_array($extension, self::MIME_EXTENSIONS[$mime], true)) {
                $reason = 'unsupported_image_format';
            } else if ($file->get_filesize() < 1 || $file->get_filesize() > self::MAX_FILE_BYTES) {
                $reason = 'image_size_out_of_range';
            } else if (!$file->is_valid_image()) {
                $reason = 'invalid_image_data';
            }
            if ($reason !== null) {
                $skipped[] = [
                    'source_filearea' => $filearea,
                    'filepath' => $file->get_filepath(),
                    'filename' => $file->get_filename(),
                    'reason' => $reason,
                ];
                continue;
            }
            if (count($entries) >= self::MAX_IMAGES) {
                throw new api_exception('too_many_images', 413);
            }

            [$destfilename, $action] = self::destination_name($file, $destinationcontext, $reserved);
            $sourceurl = moodle_url::make_pluginfile_url($file->get_contextid(),
                $file->get_component(), $filearea, $file->get_itemid(),
                $file->get_filepath(), $file->get_filename())->out(false);
            $destinationurl = moodle_url::make_pluginfile_url($destinationcontext->id, 'mod_mcp',
                'assets', 0, $file->get_filepath(), $destfilename)->out(false);
            $encodedpath = implode('/', array_map('rawurlencode',
                array_filter(explode('/', $file->get_filepath()), 'strlen')));
            $encodedname = rawurlencode($file->get_filename());
            $placeholder = '@@PLUGINFILE@@/' . ($encodedpath === '' ? '' : $encodedpath . '/') . $encodedname;
            $entries[] = [
                'source_fileid' => (int)$file->get_id(),
                'source_contextid' => (int)$file->get_contextid(),
                'source_component' => $file->get_component(),
                'source_itemid' => (int)$file->get_itemid(),
                'source_filearea' => $filearea,
                'reference_srcs' => $candidate['references'],
                'source_filepath' => $file->get_filepath(),
                'source_filename' => $file->get_filename(),
                'source_url' => $sourceurl,
                'source_placeholder' => $file->get_component() === 'mod_page' &&
                    (int)$file->get_contextid() === (int)$sourcecontext->id ?
                    $placeholder : null,
                'destination_filepath' => $file->get_filepath(),
                'destination_filename' => $destfilename,
                'destination_url' => $destinationurl,
                'mimetype' => $mime,
                'size' => (int)$file->get_filesize(),
                'contenthash' => $file->get_contenthash(),
                'action' => $action,
            ];
        }
        if ($filenamefilter !== null && !$foundmatchingname) {
            throw new api_exception('source_image_not_found', 404);
        }

        // A caller can provide this hash on a follow-up WRITE to detect changes since preview.
        $fingerprintitems = array_map(static function(array $entry): array {
            return [
                $entry['source_fileid'], $entry['source_contextid'], $entry['source_component'],
                $entry['source_itemid'], $entry['source_filearea'], $entry['source_filepath'],
                $entry['source_filename'], $entry['reference_srcs'],
                $entry['contenthash'], $entry['destination_filepath'],
                $entry['destination_filename'], $entry['action'],
            ];
        }, $entries);
        $fingerprint = hash('sha256', json_encode([$fingerprintitems, $skipped]));
        return [
            'sourcecmid' => (int)$arguments['sourcecmid'],
            'destinationcmid' => (int)$arguments['destinationcmid'],
            'manifest_sha256' => $fingerprint,
            'images' => $entries,
            'skipped' => $skipped,
        ];
    }

    /**
     * Preview without changing either activity or exposing the image bytes.
     */
    public static function preview(array $arguments, int $userid): array {
        $plan = self::plan($arguments, $userid);
        foreach ($plan['images'] as &$item) {
            unset($item['source_fileid']);
        }
        unset($item);
        return $plan + [
            'copied' => 0,
            'would_copy' => count(array_filter($plan['images'], static function(array $item): bool {
                return $item['action'] === 'copy';
            })),
            'source_unchanged' => true,
            'destination_html_unchanged' => true,
            'requires_confirmation' => true,
        ];
    }

    /**
     * Copy stored files internally, rechecking permission, source hashes and destination state.
     */
    public static function copy(array $arguments, int $userid): array {
        global $DB;

        $plan = self::plan($arguments, $userid);
        if (isset($arguments['expected_manifest_sha256']) &&
                !hash_equals($plan['manifest_sha256'], $arguments['expected_manifest_sha256'])) {
            throw new api_exception('image_manifest_changed', 409);
        }
        $destinationcontext = context_module::instance((int)$arguments['destinationcmid'], MUST_EXIST);
        $storage = get_file_storage();
        $transaction = $DB->start_delegated_transaction();
        $copied = 0;
        $reused = 0;
        try {
            foreach ($plan['images'] as &$item) {
                $source = $storage->get_file_by_id($item['source_fileid']);
                if (!$source || !hash_equals($source->get_contenthash(), $item['contenthash'])) {
                    throw new api_exception('source_image_changed', 409);
                }
                $existing = $storage->get_file($destinationcontext->id, 'mod_mcp', 'assets',
                    0, $item['destination_filepath'], $item['destination_filename']);
                if ($existing) {
                    if (!hash_equals($existing->get_contenthash(), $source->get_contenthash())) {
                        throw new api_exception('destination_image_changed', 409);
                    }
                    $item['action'] = 'reuse';
                    $reused++;
                    continue;
                }
                $storage->create_file_from_storedfile([
                    'contextid' => $destinationcontext->id,
                    'component' => 'mod_mcp',
                    'filearea' => 'assets',
                    'itemid' => 0,
                    'filepath' => $item['destination_filepath'],
                    'filename' => $item['destination_filename'],
                    'userid' => $userid,
                    'mimetype' => $source->get_mimetype(),
                    'license' => $source->get_license(),
                    'author' => $source->get_author(),
                ], $source);
                $copied++;
            }
            unset($item);
            $transaction->allow_commit();
        } catch (\Throwable $exception) {
            $transaction->rollback($exception);
        }

        foreach ($plan['images'] as &$item) {
            unset($item['source_fileid']);
        }
        unset($item);
        return $plan + [
            'copied' => $copied,
            'reused' => $reused,
            'source_unchanged' => true,
            'destination_html_unchanged' => true,
        ];
    }
}
