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
 * Validate, preview and atomically import a GIFT file into a selected question category.
 *
 * A target category can already exist or be created within the selected context.
 * The confirmed target and complete GIFT payload are checked before any writes.
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

        if (!isset($arguments["contextid"]) || !is_string($arguments["gift"] ?? null)) {
            throw new api_exception("invalid_gift_import", 400);
        }
        $existingcategory = null;
        if (array_key_exists("categoryid", $arguments)) {
            // Existing targets cannot also request category creation or metadata updates.
            if (!is_int($arguments["categoryid"]) || $arguments["categoryid"] < 1 ||
                isset($arguments["name"]) || isset($arguments["parentid"]) ||
                isset($arguments["info"]) || isset($arguments["idnumber"])) {
                throw new api_exception("invalid_gift_import", 400);
            }
            $existingcategory = $DB->get_record("question_categories",
                ["id" => $arguments["categoryid"]], "id, contextid, parent, name, idnumber");
            if (!$existingcategory ||
                (int)$existingcategory->contextid !== (int)$arguments["contextid"]) {
                throw new api_exception("invalid_gift_category_context", 400);
            }
            $contextid = (int)$existingcategory->contextid;
            $name = (string)$existingcategory->name;
            $parentid = (int)$existingcategory->parent;
            $idnumber = (string)$existingcategory->idnumber;
        } else {
            if (!isset($arguments["name"]) || !is_string($arguments["name"])) {
                throw new api_exception("invalid_gift_import", 400);
            }
            [$contextid, $name, $parentid, $idnumber] =
                bank_service::validate_category($arguments, $identity);
        }

        $ctx = context::instance_by_id($contextid, MUST_EXIST);
        // Keep the legacy restriction for NEW categories on Moodle 5+.
        // An existing category is valid even when its context is course-backed.
        if ($existingcategory === null && (int)$CFG->version >= 2025041400 &&
            $ctx->contextlevel !== CONTEXT_MODULE) {
            throw new api_exception("gift_requires_module_context", 400,
                "Use a module question bank contextid from list_question_banks.");
        }
        capability_guard::check("moodle/question:add", $ctx, $identity->userid);

        // Creating the same category twice is almost always a retried import.
        if ($existingcategory === null && $DB->record_exists("question_categories", [
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
        $allnames = [];
        foreach ($questions as $question) {
            // Embedded category directives could redirect questions to other categories.
            // Always keep questions inside the explicitly approved target category.
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
            $questionname = trim(clean_param((string)$question->name, PARAM_TEXT));
            if ($questionname === "" || isset($allnames[$questionname])) {
                throw new api_exception("duplicate_gift_question_name", 409);
            }
            $allnames[$questionname] = true;
            if ($existingcategory !== null && $DB->record_exists_sql(
                "SELECT 1
                   FROM {question} q
                   JOIN {question_versions} v ON v.questionid = q.id
                   JOIN {question_bank_entries} e ON e.id = v.questionbankentryid
                  WHERE e.questioncategoryid = :categoryid AND q.name = :name",
                ["categoryid" => (int)$existingcategory->id, "name" => $questionname]
            )) {
                // Check all versions and statuses, including hidden and draft entries.
                throw new api_exception("duplicate_gift_question_name", 409);
            }
            $types[$question->qtype] = ($types[$question->qtype] ?? 0) + 1;
            if (count($names) < 10) {
                $names[] = $questionname;
            }
        }
        ksort($types);

        return [
            "mode" => $existingcategory === null ? "create" : "existing",
            "categoryid" => $existingcategory === null ? null : (int)$existingcategory->id,
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
     * Reuse or create a category and import all questions inside one DB transaction.
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
                if ($plan["mode"] === "existing") {
                    $categoryrecord = $DB->get_record("question_categories",
                        ["id" => $plan["categoryid"], "contextid" => $plan["contextid"]],
                        "*", MUST_EXIST);
                    $category = [
                        "id" => (int)$categoryrecord->id,
                        "contextid" => (int)$categoryrecord->contextid,
                        "parentid" => (int)$categoryrecord->parent,
                        "name" => $categoryrecord->name,
                    ];
                } else {
                    $category = bank_service::create_category($arguments, $identity);
                    $categoryrecord = $DB->get_record("question_categories",
                        ["id" => $category["id"]], "*", MUST_EXIST);
                }
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
                    "mode" => $plan["mode"],
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
