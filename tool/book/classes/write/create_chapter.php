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
 * create_chapter.php
 *
 * @package   mcptool_book
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_book\write;

use context;
use context_module;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\write\tool\base_tool;
use mod_book\event\chapter_created;

/**
 * Append a new chapter to an existing Book; requires confirmation.
 */
final class create_chapter extends base_tool {
    /** @return string */
    public function get_name(): string {
        return 'book_create_chapter';
    }

    /** @return string */
    public function get_title(): string {
        return 'Book: create chapter';
    }

    /** @return string */
    public function get_description(): string {
        return 'Append a new chapter to an existing Book; requires confirmation.';
    }

    /** @return string */
    public function get_required_capability(): string {
        return 'mod/book:edit';
    }

    /** @return array */
    public function get_input_schema(): array {
        return $this->object_schema(['cmid' => ['type' => 'integer', 'minimum' => 1], 'title' => ['type' => 'string', 'minLength' => 1], 'content' => ['type' => 'string'], 'subchapter' => ['type' => 'boolean']], ['cmid', 'title', 'content']);
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
        global $DB;
        $cm = get_coursemodule_from_id('book', (int)$arguments['cmid'], 0, false, MUST_EXIST);
        $book = $DB->get_record('book', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = context_module::instance($cm->id);
        $transaction = $DB->start_delegated_transaction();
        $lastpage = (int)$DB->get_field_sql('SELECT MAX(pagenum) FROM {book_chapters} WHERE bookid = ?',
            [$book->id]);
        if (!$lastpage && !empty($arguments['subchapter'])) {
            throw new api_exception('first_chapter_cannot_be_subchapter', 400);
        }
        $chapter = (object)[
            'bookid' => $book->id, 'pagenum' => $lastpage + 1,
            'title' => clean_param($arguments['title'], PARAM_TEXT),
            'content' => clean_text($arguments['content'], FORMAT_HTML),
            'contentformat' => FORMAT_HTML, 'subchapter' => !empty($arguments['subchapter']) ? 1 : 0,
            'hidden' => 0, 'timecreated' => time(), 'timemodified' => time(), 'importsrc' => '',
        ];
        $chapter->id = $DB->insert_record('book_chapters', $chapter);
        $DB->set_field('book', 'revision', $book->revision + 1, ['id' => $book->id]);
        chapter_created::create_from_chapter($book, $context, $chapter)->trigger();
        $transaction->allow_commit();
        return ['chapterid' => (int)$chapter->id, 'bookid' => (int)$book->id];

    }
}
