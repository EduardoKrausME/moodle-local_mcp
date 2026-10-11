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
 * get_chapter.php
 *
 * @package   mcptool_book
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_book\read;

use coding_exception;
use context;
use context_module;
use dml_exception;
use local_mcp\exception\api_exception;
use local_mcp\read\tool\base_tool;
use local_mcp\security\authenticated_identity;
use moodle_exception;

/**
 * Read the HTML content of one visible book chapter.
 */
final class get_chapter extends base_tool {
    /**
     * get_name
     *
     * @return string
     */
    public function get_name(): string {
        return 'book_get_chapter';
    }

    /**
     * get_title
     *
     * @return string
     */
    public function get_title(): string {
        return 'Book: read chapter';
    }

    /**
     * get_description
     *
     * @return string
     */
    public function get_description(): string {
        return 'Read the HTML content of one visible book chapter.';
    }

    /**
     * get_required_capability
     *
     * @return string
     */
    public function get_required_capability(): string {
        return 'mod/book:read';
    }

    /**
     * get_input_schema
     *
     * @return array
     */
    public function get_input_schema(): array {
        return $this->object_schema(['chapterid' => ['type' => 'integer', 'minimum' => 1]], ['chapterid']);
    }

    /**
     * resolve_context
     *
     * @param array $arguments
     * @return context
     * @throws coding_exception
     * @throws dml_exception
     */
    public function resolve_context(array $arguments): context {
        global $DB;
        $chapter = $DB->get_record('book_chapters', ['id' => (int)$arguments['chapterid']], '*', MUST_EXIST);
        $book = $DB->get_record('book', ['id' => $chapter->bookid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('book', $book->id, $book->course, false, MUST_EXIST);
        return context_module::instance($cm->id);
    }

    /**
     * execute
     *
     * @param array $arguments
     * @param authenticated_identity $identity
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws api_exception
     */
    public function execute(array                  $arguments,
                            authenticated_identity $identity): array {
        global $DB;
        $chapter = $DB->get_record('book_chapters', ['id' => (int)$arguments['chapterid']], '*', MUST_EXIST);
        $book = $DB->get_record('book', ['id' => $chapter->bookid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('book', $book->id, $book->course, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        if (!get_fast_modinfo($cm->course, $identity->userid)->get_cm($cm->id)->uservisible
            || ($chapter->hidden && !has_capability('mod/book:viewhiddenchapters',
                    $context, $identity->userid))) {
            throw new api_exception('permission_denied', 403);
        }
        return ['id' => (int)$chapter->id, 'bookid' => (int)$book->id,
            'title' => $chapter->title, 'pagenum' => (int)$chapter->pagenum,
            'content' => format_text($chapter->content, $chapter->contentformat,
                ['context' => $context, 'filter' => true])];
    }
}
