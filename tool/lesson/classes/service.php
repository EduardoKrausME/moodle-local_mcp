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
 * Native Lesson pages and branching navigation service.
 *
 * @package   mcptool_lesson
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_lesson;

use context_module;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\security\untrusted_content;

/**
 * Manage lesson page content, navigation and choices with native lesson page APIs.
 */
final class service {
    /**
     * @return array Course module, lesson object, module context.
     */
    public static function target(int $cmid, authenticated_identity $identity): array {
        global $CFG;
        require_once($CFG->dirroot . "/mod/lesson/locallib.php");
        $cm = get_coursemodule_from_id("lesson", $cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        if (!has_capability("mod/lesson:edit", $context, $identity->userid)) {
            throw new api_exception("permission_denied", 403);
        }
        return [$cm, \lesson::load($cm->instance), $context];
    }

    /**
     * Return the persisted page and route definitions, not student progress.
     */
    private static function layout(int $lessonid): array {
        global $DB;
        $pages = $DB->get_records("lesson_pages", ["lessonid" => $lessonid], "id ASC");
        $answers = $DB->get_records("lesson_answers", ["lessonid" => $lessonid], "id ASC");
        $snapshot = [];
        foreach ($pages as $page) {
            $snapshot[] = ["id" => (int)$page->id,
                "prev" => (int)$page->prevpageid, "next" => (int)$page->nextpageid,
                "title" => $page->title, "contents" => $page->contents,
                "qtype" => (int)$page->qtype, "modified" => (int)$page->timemodified];
        }
        foreach ($answers as $answer) {
            $snapshot[] = ["answerid" => (int)$answer->id, "pageid" => (int)$answer->pageid,
                "jumpto" => (int)$answer->jumpto, "text" => $answer->answer,
                "response" => $answer->response, "score" => (int)$answer->score];
        }
        return [$pages, $answers, hash("sha256", json_encode($snapshot))];
    }

    /**
     * Read up to 100 linked pages and their outgoing jumps, with a global revision fingerprint.
     */
    public static function navigation(array $a, authenticated_identity $identity): array {
        [$cm, $lesson] = self::target((int)$a["cmid"], $identity);
        [$pages, $answers, $hash] = self::layout($lesson->id);
        $children = [];
        foreach ($answers as $answer) {
            $children[$answer->pageid][] = ["answerid" => (int)$answer->id,
                "text" => untrusted_content::text(mb_substr((string)$answer->answer, 0, 300)),
                "jumpto" => (int)$answer->jumpto, "score" => (int)$answer->score];
        }
        $ordered = [];
        $first = null;
        foreach ($pages as $page) {
            if (!(int)$page->prevpageid) {
                $first = (int)$page->id;
                break;
            }
        }
        $visited = [];
        $current = $first;
        while ($current && isset($pages[$current]) && count($ordered) < 100 && !isset($visited[$current])) {
            $visited[$current] = true;
            $page = $pages[$current];
            $ordered[] = ["pageid" => (int)$page->id,
                "title" => untrusted_content::text($page->title),
                "typeid" => (int)$page->qtype, "previous" => (int)$page->prevpageid,
                "next" => (int)$page->nextpageid, "choices" => $children[$current] ?? []];
            $current = (int)$page->nextpageid;
        }
        return ["cmid" => (int)$cm->id, "lessonid" => (int)$lesson->id,
            "pages" => $ordered, "total_pages" => count($pages), "truncated" => count($ordered) < count($pages),
            "navigation_sha256" => $hash];
    }

    /**
     * @return array A bounded page body and its teacher-only answer definitions.
     */
    public static function page(array $a, authenticated_identity $identity): array {
        global $DB;
        [$cm, $lesson] = self::target((int)$a["cmid"], $identity);
        $page = $DB->get_record("lesson_pages", ["id" => (int)$a["pageid"],
            "lessonid" => $lesson->id], "*", MUST_EXIST);
        $answers = $DB->get_records("lesson_answers", ["lessonid" => $lesson->id,
            "pageid" => $page->id], "id ASC", "*", 0, 25);
        $choices = [];
        foreach ($answers as $ans) {
            $choices[] = ["answerid" => (int)$ans->id,
                "text" => untrusted_content::text(mb_substr((string)$ans->answer, 0, 3000)),
                "response" => untrusted_content::text(mb_substr((string)$ans->response, 0, 3000)),
                "jumpto" => (int)$ans->jumpto, "score" => (int)$ans->score];
        }
        return ["cmid" => (int)$cm->id, "pageid" => (int)$page->id,
            "title" => untrusted_content::text($page->title),
            "content" => untrusted_content::text(mb_substr((string)$page->contents, 0, 30000)),
            "content_truncated" => mb_strlen((string)$page->contents) > 30000,
            "answers" => $choices, "navigation_sha256" => self::layout($lesson->id)[2]];
    }

    /**
     * Strictly validate every jump against existing pages in the same lesson.
     */
    private static function jump($value, int $lessonid): int {
        global $DB;
        if ($value === "next") {
            return LESSON_NEXTPAGE;
        }
        if ($value === "previous") {
            return LESSON_PREVIOUSPAGE;
        }
        if ($value === "end") {
            return LESSON_EOL;
        }
        if (!is_int($value) || $value < 1 ||
                !$DB->record_exists("lesson_pages", ["lessonid" => $lessonid, "id" => $value])) {
            throw new api_exception("invalid_lesson_jump", 400);
        }
        return $value;
    }

    /**
     * Reject modifications of the navigation graph after learner attempts.
     */
    private static function modifiable(int $lessonid): void {
        global $DB;
        if ($DB->record_exists("lesson_attempts", ["lessonid" => $lessonid]) ||
                $DB->record_exists("lesson_branch", ["lessonid" => $lessonid])) {
            throw new api_exception("lesson_has_student_attempts", 409);
        }
    }

    /**
     * @return array Validated page creation preview without large HTML.
     */
    public static function create_preview(array $a, authenticated_identity $identity): array {
        global $DB;
        [$cm, $lesson] = self::target((int)$a["cmid"], $identity);
        self::modifiable($lesson->id);
        [$pages, $answers, $hash] = self::layout($lesson->id);
        if (!hash_equals($hash, (string)$a["expected_navigation_sha256"])) {
            throw new api_exception("lesson_navigation_changed", 409);
        }
        $title = trim(clean_param((string)$a["title"], PARAM_TEXT));
        if ($title === "" || mb_strlen($title) > 255) {
            throw new api_exception("invalid_lesson_title", 400);
        }
        $kind = (string)$a["type"];
        if (!in_array($kind, ["content", "multichoice"], true)) {
            throw new api_exception("invalid_lesson_page_type", 400);
        }
        $choices = $a["choices"] ?? [];
        $min = $kind === "multichoice" ? 2 : 1;
        if (count($choices) < $min || count($choices) > min(10, (int)$lesson->maxanswers)) {
            throw new api_exception("invalid_lesson_choices", 400);
        }
        $prepared = [];
        foreach ($choices as $choice) {
            $text = trim(clean_param((string)$choice["text"], PARAM_TEXT));
            if ($text === "" || mb_strlen($text) > 255) {
                throw new api_exception("invalid_lesson_choice_text", 400);
            }
            $prepared[] = ["text" => $text,
                "response" => clean_text((string)($choice["response"] ?? ""), FORMAT_HTML),
                "jump" => self::jump($choice["jump"], $lesson->id),
                "score" => max(0, min(100, (int)($choice["score"] ?? 0)))];
        }
        $end = $pages ? array_values(array_filter($pages, static function($p) {
            return !(int)$p->nextpageid;
        })) : [];
        if (count($end) !== ($pages ? 1 : 0)) {
            throw new api_exception("lesson_page_chain_invalid", 409);
        }
        return ["cmid" => (int)$cm->id, "lessonid" => (int)$lesson->id,
            "title" => $title, "type" => $kind,
            "after_pageid" => $end ? (int)$end[0]->id : 0,
            "choices" => $prepared, "choices_count" => count($prepared),
            "content_sha256" => hash("sha256", (string)$a["content"])];
    }

    /**
     * @return array Page saved by lesson_page::create with Moodle events.
     */
    public static function create(array $a, authenticated_identity $identity): array {
        global $DB, $PAGE;
        $preview = self::create_preview($a, $identity);
        [$cm, $lesson, $context] = self::target((int)$a["cmid"], $identity);
        // The native page writer reads $PAGE->course->maxbytes for answer draft areas.
        $PAGE->set_course(get_course($cm->course));
        $PAGE->set_cm($cm);
        \lesson_page_type_manager::get($lesson);
        $properties = (object)["title" => $preview["title"],
            "contents_editor" => ["text" => clean_text((string)$a["content"], FORMAT_HTML),
                "format" => FORMAT_HTML, "itemid" => 0],
            "qtype" => $preview["type"] === "content" ? LESSON_PAGE_BRANCHTABLE : LESSON_PAGE_MULTICHOICE,
            "pageid" => $preview["after_pageid"], "layout" => 1, "display" => 1,
            "answer_editor" => [], "response_editor" => [], "jumpto" => [], "score" => []];
        foreach ($preview["choices"] as $i => $choice) {
            $properties->answer_editor[$i] = ["text" => $choice["text"], "format" => FORMAT_HTML,
                "itemid" => 0];
            $properties->response_editor[$i] = ["text" => $choice["response"], "format" => FORMAT_HTML,
                "itemid" => 0];
            $properties->jumpto[$i] = $choice["jump"];
            $properties->score[$i] = $choice["score"];
        }
        $transaction = $DB->start_delegated_transaction();
        $page = \lesson_page::create($properties, $lesson, $context, (int)get_course($cm->course)->maxbytes);
        $transaction->allow_commit();
        return ["created" => true, "cmid" => (int)$cm->id,
            "pageid" => (int)$page->id, "title" => $preview["title"]];
    }

    /**
     * @return array Safe page content update preview.
     */
    public static function update_preview(array $a, authenticated_identity $identity): array {
        global $DB;
        [$cm, $lesson] = self::target((int)$a["cmid"], $identity);
        self::modifiable($lesson->id);
        $page = $DB->get_record("lesson_pages", ["id" => (int)$a["pageid"],
            "lessonid" => $lesson->id], "*", MUST_EXIST);
        if (!hash_equals(self::layout($lesson->id)[2], (string)$a["expected_navigation_sha256"])) {
            throw new api_exception("lesson_navigation_changed", 409);
        }
        $title = trim(clean_param((string)$a["title"], PARAM_TEXT));
        if ($title === "" || mb_strlen($title) > 255) {
            throw new api_exception("invalid_lesson_title", 400);
        }
        return ["cmid" => (int)$cm->id, "pageid" => (int)$page->id,
            "current_title" => $page->title, "new_title" => $title,
            "content_sha256" => hash("sha256", (string)$a["content"])];
    }

    /**
     * @return array Persist page body and Moodle's native page_updated event.
     */
    public static function update(array $a, authenticated_identity $identity): array {
        global $DB, $CFG;
        $preview = self::update_preview($a, $identity);
        [$cm, $lesson, $context] = self::target((int)$a["cmid"], $identity);
        $page = $DB->get_record("lesson_pages", ["id" => $preview["pageid"]], "*", MUST_EXIST);
        $editor = (object)["id" => $page->id,
            "title" => $preview["new_title"],
            "contents_editor" => ["text" => clean_text((string)$a["content"], FORMAT_HTML),
                "format" => FORMAT_HTML, "itemid" => 0]];
        $editor = file_postupdate_standard_editor($editor, "contents",
            ["noclean" => true, "maxfiles" => EDITOR_UNLIMITED_FILES,
                "maxbytes" => (int)get_course($cm->course)->maxbytes],
            $context, "mod_lesson", "page_contents", $page->id);
        $editor->contentsformat = FORMAT_HTML;
        $editor->timemodified = time();
        $DB->update_record("lesson_pages", $editor);
        \lesson_page_type_manager::get($lesson);
        $updated = $DB->get_record("lesson_pages", ["id" => $page->id], "*", MUST_EXIST);
        $lessonpage = \lesson_page::load($updated, $lesson);
        \mod_lesson\event\page_updated::create_from_lesson_page($lessonpage, $context)->trigger();
        return ["updated" => true, "pageid" => (int)$page->id, "cmid" => (int)$cm->id];
    }

    /**
     * @return array Validate destinations of existing answers before changing the route graph.
     */
    public static function jumps_preview(array $a, authenticated_identity $identity): array {
        global $DB;
        [$cm, $lesson] = self::target((int)$a["cmid"], $identity);
        self::modifiable($lesson->id);
        if (!hash_equals(self::layout($lesson->id)[2], (string)$a["expected_navigation_sha256"])) {
            throw new api_exception("lesson_navigation_changed", 409);
        }
        $page = $DB->get_record("lesson_pages", ["lessonid" => $lesson->id,
            "id" => (int)$a["pageid"]], "id", MUST_EXIST);
        $routes = $a["routes"];
        if (!$routes || count($routes) > 10) {
            throw new api_exception("invalid_lesson_routes", 400);
        }
        $prepared = [];
        foreach ($routes as $route) {
            $answer = $DB->get_record("lesson_answers", [
                "id" => (int)$route["answerid"], "pageid" => $page->id,
                "lessonid" => $lesson->id], "id,jumpto", MUST_EXIST);
            if (isset($prepared[$answer->id])) {
                throw new api_exception("duplicate_lesson_route", 400);
            }
            $prepared[$answer->id] = ["answerid" => (int)$answer->id,
                "old_jump" => (int)$answer->jumpto,
                "new_jump" => self::jump($route["jump"], $lesson->id)];
        }
        return ["cmid" => (int)$cm->id, "pageid" => (int)$page->id,
            "routes" => array_values($prepared)];
    }

    /**
     * @return array Update branches with the page_updated event.
     */
    public static function set_jumps(array $a, authenticated_identity $identity): array {
        global $DB;
        $preview = self::jumps_preview($a, $identity);
        [$cm, $lesson, $context] = self::target((int)$a["cmid"], $identity);
        $transaction = $DB->start_delegated_transaction();
        foreach ($preview["routes"] as $route) {
            $DB->set_field("lesson_answers", "jumpto", $route["new_jump"], ["id" => $route["answerid"]]);
        }
        \lesson_page_type_manager::get($lesson);
        $lessonpage = \lesson_page::load((int)$a["pageid"], $lesson);
        \mod_lesson\event\page_updated::create_from_lesson_page($lessonpage, $context)->trigger();
        $transaction->allow_commit();
        return ["updated" => true, "pageid" => (int)$a["pageid"],
            "routes_updated" => count($preview["routes"])];
    }
}
