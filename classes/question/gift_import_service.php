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
 * Import a complete GIFT question bank through Moodle's native question APIs.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\question;

use context;
use context_course;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\security\capability_guard;
use Throwable;

/**
 * Validate, preview and atomically import a GIFT file into a new question category.
 *
 * The question bank is identified by its existing Moodle context; this service
 * creates one child question category and imports every question into that category.
 */
final class gift_import_service {
    /** Maximum UTF-8 payload size (2 MiB). */
    private const MAX_BYTES = 2097152;

    /** Maximum number of questions accepted in one MCP import. */
    private const MAX_QUESTIONS = 500;

    /**
     * Parse and validate the complete request without modifying Moodle.
     *
     * @param array $arguments MCP arguments.
     * @param authenticated_identity $identity Connected Moodle user.
     * @return array Safe summary and normalized category metadata.
     */
    public static function inspect(array $arguments, authenticated_identity $identity): array {
        global $CFG, $DB;

        if (!isset($arguments["contextid"], $arguments["name"]) ||
            !is_string($arguments["name"]) || !is_string($arguments["gift"] ?? null)) {
            throw new api_exception("invalid_gift_import", 400);
        }
        [$contextid, $name, $parentid, $idnumber] = bank_service::validate_category($arguments, $identity);
        $ctx = context::instance_by_id($contextid, MUST_EXIST);
        // Moodle 5+ stores question banks in module contexts. Reject legacy
        // course contexts during preview rather than creating an unusable category.
        if ((int)$CFG->version >= 2025041400 && $ctx->contextlevel !== CONTEXT_MODULE) {
            throw new api_exception("gift_requires_module_context", 400,
                "Use a module question bank contextid from list_question_banks.");
        }
        capability_guard::check("moodle/question:add", $ctx, $identity->userid);

        // An identical name in the same parent is most often a retried import.
        // Never append another batch silently to a newly duplicated category.
        if ($DB->record_exists("question_categories", [
            "contextid" => $contextid, "parent" => $parentid, "name" => $name,
        ])) {
            throw new api_exception("duplicate_question_category", 409);
        }

        $gift = $arguments["gift"];
        if (trim($gift) === "" || strlen($gift) > self::MAX_BYTES ||
            str_contains($gift, "\0") || !mb_check_encoding($gift, "UTF-8")) {
            throw new api_exception("invalid_gift_content", 400);
        }

        require_once($CFG->libdir . "/questionlib.php");
        require_once($CFG->dirroot . "/question/format.php");
        require_once($CFG->dirroot . "/question/format/gift/format.php");
        $format = new gift_format();
        $previouslevel = ob_get_level();
        ob_start();
        try {
            $questions = $format->inspect_content($gift);
        } finally {
            while (ob_get_level() > $previouslevel) {
                ob_end_clean();
            }
        }
        if ($format->importerrors || !$questions || count($questions) > self::MAX_QUESTIONS) {
            throw new api_exception("invalid_gift_questions", 400);
        }

        $types = [];
        $names = [];
        foreach ($questions as $question) {
            // Embedded category directives could redirect questions to other categories.
            // This tool deliberately imports only into its explicitly approved new category.
            if (!is_object($question) || empty($question->qtype) ||
                $question->qtype === "category" || trim((string)($question->name ?? "")) === "") {
                throw new api_exception("invalid_gift_questions", 400);
            }
            if (!\question_bank::get_qtype($question->qtype, false)) {
                throw new api_exception("unsupported_gift_question_type", 400);
            }
            if (!empty($question->fraction) && is_array($question->fraction)) {
                foreach ($question->fraction as $fraction) {
                    if (match_grade_options(\question_bank::fraction_options_full(),
                        $fraction, "error") === false) {
                        throw new api_exception("invalid_gift_grade", 400);
                    }
                }
            }
            $types[$question->qtype] = ($types[$question->qtype] ?? 0) + 1;
            if (count($names) < 10) {
                $names[] = (string)$question->name;
            }
        }
        ksort($types);

        return [
            "contextid" => $contextid,
            "name" => $name,
            "parentid" => $parentid,
            "idnumber" => $idnumber,
            "question_count" => count($questions),
            "question_types" => $types,
            "sample_names" => $names,
            "gift_sha256" => hash("sha256", $gift),
            "gift_bytes" => strlen($gift),
        ];
    }

    /**
     * Create one category and import all questions inside a single DB transaction.
     *
     * @param array $arguments Confirmed MCP arguments.
     * @param authenticated_identity $identity Connected Moodle user.
     * @return array Created category and question IDs.
     */
    public static function execute(array $arguments, authenticated_identity $identity): array {
        global $DB;

        // Repeat preview validation after the confirmation token was issued.
        $plan = self::inspect($arguments, $identity);
        $directory = make_temp_directory("local_mcp");
        $filename = tempnam($directory, "gift_");
        if ($filename === false) {
            throw new api_exception("gift_tempfile_failed", 500);
        }
        try {
            $gift = $arguments["gift"];
            if (file_put_contents($filename, $gift) !== strlen($gift)) {
                throw new api_exception("gift_tempfile_failed", 500);
            }

            $transaction = $DB->start_delegated_transaction();
            try {
                $category = bank_service::create_category($arguments, $identity);
                $categoryrecord = $DB->get_record("question_categories",
                    ["id" => $category["id"]], "*", MUST_EXIST);
                $ctx = context::instance_by_id($plan["contextid"], MUST_EXIST);
                $format = new gift_format();
                $format->setCategory($categoryrecord);
                $format->setContexts([$ctx]);
                $format->setFilename($filename);
                $format->setRealfilename("questions.gift");
                $format->setCatfromfile(false);
                $format->setContextfromfile(false);
                $format->setMatchgrades("error");
                $format->setStoponerror(true);
                $format->set_display_progress(false);
                $coursectx = $ctx->get_course_context(false);
                if ($coursectx instanceof context_course) {
                    $format->setCourse(get_course((int)$coursectx->instanceid));
                } else {
                    $format->setCourse(get_site());
                }

                // Core may echo import warnings even with progress disabled.
                // Never let those messages corrupt an MCP JSON-RPC response.
                $previouslevel = ob_get_level();
                ob_start();
                try {
                    $successful = $format->importpreprocess() &&
                        $format->importprocess() && $format->importpostprocess();
                } finally {
                    while (ob_get_level() > $previouslevel) {
                        ob_end_clean();
                    }
                }
                if (!$successful || $format->importerrors ||
                    count($format->questionids) !== $plan["question_count"]) {
                    throw new api_exception("gift_import_failed", 422);
                }
                $ids = array_map("intval", $format->questionids);
                $transaction->allow_commit();
                return [
                    "categoryid" => (int)$category["id"],
                    "contextid" => (int)$category["contextid"],
                    "parentid" => (int)$category["parentid"],
                    "name" => $category["name"],
                    "question_count" => count($ids),
                    "question_ids" => $ids,
                    "question_types" => $plan["question_types"],
                    "gift_sha256" => $plan["gift_sha256"],
                ];
            } catch (Throwable $e) {
                $transaction->rollback($e);
            }
        } finally {
            @unlink($filename);
        }
    }
}
