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
 * list_chapters.php
 *
 * @package   mcptool_book
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_book\read;

use context;
use context_module;
use local_mcp\exception\api_exception;
use local_mcp\read\tool\base_tool;
use local_mcp\security\authenticated_identity;
use moodle_url;

/**
 * List visible chapters in a Moodle Book activity.
 */
final class list_chapters extends base_tool {
    /** @return string */
    public function get_name(): string {
        return 'book_list_chapters';
    }

    /** @return string */
    public function get_title(): string {
        return 'Book: list chapters';
    }

    /** @return string */
    public function get_description(): string {
        return 'List visible chapters in a Moodle Book activity.';
    }

    /** @return string */
    public function get_required_capability(): string {
        return 'mod/book:read';
    }

    /** @return array */
    public function get_input_schema(): array {
        return $this->object_schema(['cmid' => ['type' => 'integer', 'minimum' => 1]], ['cmid']);
    }

    /**
     * @param array $arguments Tool input.
     * @return context
     */
    public function resolve_context(array $arguments): context {
        return context_module::instance((int)$arguments['cmid'], MUST_EXIST);
    }

    /**
     * @param array $arguments Tool input.
     * @param authenticated_identity $identity OAuth identity.
     * @return array
     */
    public function execute(array $arguments,
            authenticated_identity $identity): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/book/locallib.php');
        $cm = get_coursemodule_from_id('book', (int)$arguments['cmid'], 0, false, MUST_EXIST);
        $book = $DB->get_record('book', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = context_module::instance($cm->id);
        if (!get_fast_modinfo($cm->course, $identity->userid)->get_cm($cm->id)->uservisible) {
            throw new api_exception('permission_denied', 403);
        }
        $canedit = has_capability('mod/book:viewhiddenchapters', $context, $identity->userid);
        $rows = [];
        foreach (book_preload_chapters($book) as $chapter) {
            if ($chapter->hidden && !$canedit) {
                continue;
            }
            $rows[] = [
                'id' => (int)$chapter->id, 'title' => $chapter->title,
                'pagenum' => (int)$chapter->pagenum, 'subchapter' => (bool)$chapter->subchapter,
                'url' => (new moodle_url('/mod/book/view.php',
                    ['id' => $cm->id, 'chapterid' => $chapter->id]))->out(false),
            ];
        }
        return ['cmid' => (int)$cm->id, 'bookid' => (int)$book->id, 'chapters' => $rows,
            'revision' => (int)$book->revision];

    }
}
