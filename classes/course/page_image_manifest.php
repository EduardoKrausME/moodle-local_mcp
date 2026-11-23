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
 * Read image references from a Page and resolve authorised local Moodle files.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\course;

use context;
use context_course;
use context_module;
use DOMDocument;
use local_mcp\security\capability_guard;
use moodle_url;
use stdClass;
use stored_file;

/**
 * An HTML image URL is only a reference, never authority to access a stored file.
 */
final class page_image_manifest {
    /** Only explicitly supported, course-scoped file areas can be copied. */
    private const ALLOWED_AREAS = [
        'mod_page' => ['content', 'intro'],
        'mod_scorm' => ['content'],
        'mod_resource' => ['content'],
        'mod_folder' => ['content'],
        'course' => ['summary', 'overviewfiles', 'section'],
    ];

    /** Avoid unbounded HTML parsing or filesystem lookups. */
    private const MAX_HTML_BYTES = 2097152;
    private const MAX_REFERENCES = 250;

    /**
     * Enumerate src attributes without fetching images or executing HTML.
     *
     * @param int $sourcecmid Page course-module ID.
     * @param int $userid Authenticated Moodle user.
     * @return array Image manifest with statuses and authorised stored-file metadata.
     */
    public static function inspect(int $sourcecmid, int $userid): array {
        global $DB;
        $cm = get_coursemodule_from_id('page', $sourcecmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id, MUST_EXIST);
        capability_guard::check('mod/page:view', $context, $userid);
        $page = $DB->get_record('page', ['id' => $cm->instance], 'id,content', MUST_EXIST);
        return self::collect($cm, $context, (string)$page->content, $userid)['images'];
    }

    /**
     * Return both a safe, serialisable manifest and internal stored_file handles.
     * Callers must not serialise the 'files' member to an MCP response.
     *
     * @param stdClass $sourcecm Page course module.
     * @param context_module $sourcecontext Page context.
     * @param string $html Original Page HTML.
     * @param int $userid Authenticated Moodle user.
     * @return array Images and resolved files.
     */
    public static function collect(stdClass $sourcecm, context_module $sourcecontext,
                                   string $html, int $userid): array {
        $images = [];
        $files = [];
        if ($html === '' || strlen($html) > self::MAX_HTML_BYTES) {
            return ['images' => [], 'files' => []];
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        try {
            $loaded = $dom->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $html,
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            if (!$loaded) {
                return ['images' => [], 'files' => []];
            }
            $seen = [];
            foreach ($dom->getElementsByTagName('img') as $image) {
                $src = trim(html_entity_decode($image->getAttribute('src'), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($src === '' || isset($seen[$src])) {
                    continue;
                }
                $seen[$src] = true;
                if (count($images) >= self::MAX_REFERENCES) {
                    break;
                }
                $entry = ['src' => mb_substr($src, 0, 2048), 'status' => 'unsupported_reference'];
                if (strlen($src) <= 2048) {
                    $file = self::resolve($src, $sourcecm, $sourcecontext, $userid);
                    if ($file) {
                        $entry['status'] = 'resolved';
                        $entry['fileid'] = (int)$file->get_id();
                        $entry['contextid'] = (int)$file->get_contextid();
                        $entry['component'] = $file->get_component();
                        $entry['filearea'] = $file->get_filearea();
                        $entry['itemid'] = (int)$file->get_itemid();
                        $entry['filepath'] = $file->get_filepath();
                        $entry['filename'] = $file->get_filename();
                        $entry['source_url'] = moodle_url::make_pluginfile_url(
                            $file->get_contextid(), $file->get_component(), $file->get_filearea(),
                            $file->get_itemid(), $file->get_filepath(), $file->get_filename())->out(false);
                        $id = (int)$file->get_id();
                        if (!isset($files[$id])) {
                            $files[$id] = ['file' => $file, 'references' => []];
                        }
                        $files[$id]['references'][] = $src;
                    }
                }
                $images[] = $entry;
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        return ['images' => $images, 'files' => array_values($files)];
    }

    /**
     * Resolve only local pluginfile references in the Page's own course.
     * External URLs, data URIs, user files and unapproved file areas are never fetched.
     */
    private static function resolve(string $src, stdClass $sourcecm,
                                    context_module $sourcecontext, int $userid): ?stored_file {
        global $CFG, $DB;

        $storage = get_file_storage();
        if (str_starts_with($src, '@@PLUGINFILE@@/')) {
            $path = self::safe_parts(substr($src, strlen('@@PLUGINFILE@@/')));
            if (!$path) {
                return null;
            }
            $filename = array_pop($path);
            $filepath = '/' . ($path ? implode('/', $path) . '/' : '');
            foreach (['content', 'intro'] as $area) {
                $file = $storage->get_file($sourcecontext->id, 'mod_page', $area, 0, $filepath, $filename);
                if ($file && !$file->is_directory()) {
                    return $file;
                }
            }
            return null;
        }

        $url = parse_url($src);
        $site = parse_url($CFG->wwwroot);
        if ($url === false || $site === false || !isset($url['path']) ||
            isset($url['user']) || isset($url['pass'])) {
            return null;
        }
        if (isset($url['host']) &&
            (strcasecmp($url['host'], $site['host'] ?? '') !== 0 ||
                strtolower($url['scheme'] ?? '') !== strtolower($site['scheme'] ?? '') ||
                ($url['port'] ?? null) !== ($site['port'] ?? null))) {
            return null;
        }
        if (!isset($url['host']) && !str_starts_with($src, '/')) {
            return null;
        }
        $basepath = rtrim($site['path'] ?? '', '/');
        $prefix = $basepath . '/pluginfile.php/';
        if (!str_starts_with($url['path'], $prefix)) {
            return null;
        }
        $parts = self::safe_parts(substr($url['path'], strlen($prefix)));
        if (!$parts || count($parts) < 4 || !ctype_digit($parts[0])) {
            return null;
        }
        $contextid = (int)array_shift($parts);
        $component = array_shift($parts);
        $area = array_shift($parts);
        if (!isset(self::ALLOWED_AREAS[$component]) ||
            !in_array($area, self::ALLOWED_AREAS[$component], true)) {
            return null;
        }

        $context = context::instance_by_id($contextid, IGNORE_MISSING);
        if (!$context) {
            return null;
        }

        if ($component === 'course') {
            if ($context->contextlevel !== CONTEXT_COURSE || (int)$context->instanceid !== (int)$sourcecm->course) {
                return null;
            }
            if (!has_capability('moodle/course:view', $context, $userid)) {
                return null;
            }
            if (in_array($area, ['summary', 'overviewfiles'], true)) {
                $itemid = 0;
                // Older editors sometimes include itemid=0 even though Moodle's
                // canonical course file URLs omit this segment.
                if (count($parts) > 1 && $parts[0] === '0') {
                    array_shift($parts);
                }
            } else {
                $item = array_shift($parts);
                if ($item === null || !ctype_digit($item)) {
                    return null;
                }
                $itemid = (int)$item;
                if (!$DB->record_exists('course_sections', ['id' => $itemid, 'course' => $sourcecm->course])) {
                    return null;
                }
            }
        } else {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                return null;
            }
            $modname = substr($component, 4);
            $cm = get_coursemodule_from_id($modname, (int)$context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm || (int)$cm->course !== (int)$sourcecm->course ||
                !has_capability("mod/{$modname}:view", $context, $userid)) {
                return null;
            }
            // Other modules' file areas require management permission in that context.
            if ((int)$cm->id !== (int)$sourcecm->id &&
                !has_capability('moodle/course:manageactivities', $context, $userid)) {
                return null;
            }
            $item = array_shift($parts);
            if ($item === null || !ctype_digit($item)) {
                return null;
            }
            $itemid = (int)$item;
        }

        if (!$parts) {
            return null;
        }
        $filename = array_pop($parts);
        $filepath = '/' . ($parts ? implode('/', $parts) . '/' : '');
        $file = $storage->get_file($contextid, $component, $area, $itemid, $filepath, $filename);
        return $file && !$file->is_directory() ? $file : null;
    }

    /**
     * Decode a URL's individual segments once; forbid encoded separators and traversal.
     *
     * @param string $path URL path without the pluginfile prefix.
     * @return array|null Validated decoded path parts.
     */
    private static function safe_parts(string $path): ?array {
        $parts = explode('/', $path);
        foreach ($parts as &$part) {
            $part = rawurldecode($part);
            if ($part === '' || $part === '.' || $part === '..' ||
                str_contains($part, '/') || str_contains($part, '\\') ||
                preg_match('/[\x00-\x1f\x7f]/', $part) ||
                clean_param($part, PARAM_FILE) !== $part) {
                return null;
            }
        }
        unset($part);
        return $parts;
    }
}
