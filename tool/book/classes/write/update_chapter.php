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
 * update_chapter.php
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
use mod_book\event\chapter_updated;

/**
 * Update a Book chapter with revision conflict checking and confirmation.
 */
final class update_chapter extends base_tool {
    /** @return string */
    public function get_name(): string {
        return 'book_update_chapter';
    }

    /** @return string */
    public function get_title(): string {
        return 'Book: update chapter';
    }

    /** @return string */
    public function get_description(): string {
        return 'Update a Book chapter with revision conflict checking and confirmation.';
    }

    /** @return string */
    public function get_required_capability(): string {
        return 'mod/book:edit';
    }

    /** @return array */
    public function get_input_schema(): array {
        return $this->object_schema(['chapterid' => ['type' => 'integer', 'minimum' => 1], 'title' => ['type' => 'string'], 'content' => ['type' => 'string'], 'expected_revision' => ['type' => 'integer', 'minimum' => 0]], ['chapterid', 'content', 'expected_revision']);
    }

    /**
     * @param array $arguments Tool input.
     * @return context
     */
    public function resolve_context(array $arguments): context {
        global $DB;
        $chapter = $DB->get_record('book_chapters', ['id' => (int)$arguments['chapterid']], '*', MUST_EXIST);
        $book = $DB->get_record('book', ['id' => $chapter->bookid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('book', $book->id, $book->course, false, MUST_EXIST);
        return context_module::instance($cm->id);
    }

    /**
     * @param array $arguments Tool input.
     * @param authenticated_identity $identity OAuth identity.
     * @return array
     */
    public function execute(array $arguments,
            authenticated_identity $identity): array {
        global $DB;
        $chapter = $DB->get_record('book_chapters', ['id' => (int)$arguments['chapterid']], '*', MUST_EXIST);
        $book = $DB->get_record('book', ['id' => $chapter->bookid], '*', MUST_EXIST);
        if ((int)$book->revision !== (int)$arguments['expected_revision']) {
            throw new api_exception('revision_conflict', 409);
        }
        $context = $this->resolve_context($arguments);
        if (array_key_exists('title', $arguments)) {
            $chapter->title = clean_param($arguments['title'], PARAM_TEXT);
        }
        $chapter->content = clean_text($arguments['content'], FORMAT_HTML);
        $chapter->contentformat = FORMAT_HTML;
        $chapter->timemodified = time();
        $transaction = $DB->start_delegated_transaction();
        $DB->update_record('book_chapters', $chapter);
        $DB->set_field('book', 'revision', $book->revision + 1, ['id' => $book->id]);
        chapter_updated::create_from_chapter($book, $context, $chapter)->trigger();
        $transaction->allow_commit();
        return ['updated' => true, 'chapterid' => (int)$chapter->id, 'revision' => (int)$book->revision + 1];

    }
}
