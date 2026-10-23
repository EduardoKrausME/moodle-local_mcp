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
 * Feedback item administration and privacy-preserving reporting.
 *
 * @package   mcptool_feedback
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_feedback;

use context_module;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\security\untrusted_content;

/**
 * Use Moodle item handlers and enforce anonymity at the access boundary.
 */
final class service {
    /**
     * @return array Module, instance, context.
     */
    public static function target(int $cmid, authenticated_identity $identity, string $capability): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/mod/feedback/lib.php");
        $cm = get_coursemodule_from_id("feedback", $cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cmid);
        if (!has_capability($capability, $context, $identity->userid)) {
            throw new api_exception("permission_denied", 403);
        }
        $feedback = $DB->get_record("feedback", ["id" => $cm->instance], "*", MUST_EXIST);
        return [$cm, $feedback, $context];
    }

    /**
     * @return array Question records and current revision digest.
     */
    public static function questions(array $a, authenticated_identity $identity): array {
        global $DB;
        [$cm, $feedback] = self::target((int)$a["cmid"], $identity, "mod/feedback:edititems");
        $items = $DB->get_records("feedback_item", ["feedback" => $feedback->id], "position ASC, id ASC");
        $rows = [];
        foreach ($items as $item) {
            $rows[] = ["id" => (int)$item->id, "name" => untrusted_content::text($item->name),
                "type" => $item->typ, "required" => (bool)$item->required,
                "position" => (int)$item->position,
                "options" => $item->typ === "multichoice" ? self::choice_options($item) : []];
        }
        return ["cmid" => (int)$cm->id, "items" => $rows,
            "items_sha256" => self::items_hash($feedback->id)];
    }

    /**
     * Revision of questions and choices, never of submitted answers.
     */
    private static function items_hash(int $feedbackid): string {
        global $DB;
        $records = $DB->get_records("feedback_item", ["feedback" => $feedbackid],
            "position ASC, id ASC", "id,typ,name,presentation,position,required");
        $rows = [];
        foreach ($records as $record) {
            $rows[] = [(int)$record->id, $record->typ, $record->name,
                $record->presentation, (int)$record->position, (int)$record->required];
        }
        return hash("sha256", json_encode($rows));
    }

    /**
     * @return array Safe labels for a choice-type item.
     */
    private static function choice_options(\stdClass $item): array {
        $presentation = explode(">>>>>", $item->presentation, 2);
        $options = explode("<<<<<", $presentation[1] ?? "", 2);
        return array_map(static function($label) {
            return untrusted_content::text($label);
        }, array_filter(explode("|", $options[0]), static function($v) {
            return $v !== "";
        }));
    }

    /**
     * @return array Metadata-only preview, excluding free text response payloads.
     */
    public static function question_preview(array $a, authenticated_identity $identity, bool $updating): array {
        global $DB;
        [$cm, $feedback] = self::target((int)$a["cmid"], $identity, "mod/feedback:edititems");
        if (!hash_equals(self::items_hash($feedback->id), (string)$a["expected_items_sha256"])) {
            throw new api_exception("feedback_items_changed", 409);
        }
        if ($updating) {
            $item = $DB->get_record("feedback_item", ["id" => (int)$a["itemid"],
                "feedback" => $feedback->id], "*", MUST_EXIST);
            if ($DB->record_exists("feedback_value", ["item" => $item->id]) ||
                    $DB->record_exists("feedback_valuetmp", ["item" => $item->id])) {
                throw new api_exception("question_has_answers", 409);
            }
        }
        $type = (string)$a["type"];
        if (!in_array($type, ["multichoice", "textarea"], true)) {
            throw new api_exception("unsupported_feedback_question_type", 400);
        }
        $title = trim(clean_param((string)$a["name"], PARAM_TEXT));
        if ($title === "" || mb_strlen($title) > 255) {
            throw new api_exception("invalid_feedback_question_title", 400);
        }
        $options = $a["options"] ?? [];
        if ($type === "multichoice" && (count($options) < 2 || count($options) > 20)) {
            throw new api_exception("invalid_feedback_choices", 400);
        }
        if ($type === "textarea" && $options) {
            throw new api_exception("feedback_text_question_has_choices", 400);
        }
        $clean = [];
        foreach ($options as $option) {
            $value = trim(clean_param((string)$option, PARAM_TEXT));
            if ($value === "" || strpbrk($value, "|><") !== false || mb_strlen($value) > 255) {
                throw new api_exception("invalid_feedback_choice", 400);
            }
            $clean[] = $value;
        }
        if (count(array_unique($clean)) !== count($clean)) {
            throw new api_exception("duplicate_feedback_choice", 400);
        }
        return ["cmid" => (int)$cm->id, "feedbackid" => (int)$feedback->id,
            "itemid" => $updating ? (int)$item->id : null,
            "type" => $type, "name" => $title, "required" => !empty($a["required"]),
            "options" => $clean, "editing" => $updating];
    }

    /**
     * @return array Question saved via its native feedback item implementation.
     */
    public static function save_question(array $a, authenticated_identity $identity, bool $updating): array {
        global $DB;
        $preview = self::question_preview($a, $identity, $updating);
        $feedbackid = $preview["feedbackid"];
        $item = $updating
            ? $DB->get_record("feedback_item", ["id" => $preview["itemid"]], "*", MUST_EXIST)
            : (object)["id" => 0, "feedback" => $feedbackid, "template" => 0,
                "position" => $DB->count_records("feedback_item", ["feedback" => $feedbackid]) + 1,
                "label" => "", "dependitem" => 0, "dependvalue" => "", "options" => ""];
        if ($updating && $item->typ !== $preview["type"]) {
            throw new api_exception("feedback_question_type_change_unsupported", 409);
        }
        $item->typ = $preview["type"];
        $item->name = $preview["name"];
        $item->required = (int)$preview["required"];
        $item->hasvalue = 1;
        $item->presentation = $item->typ === "multichoice"
            ? "r>>>>>" . implode("|", $preview["options"]) : "40|6";
        if ($item->typ === "multichoice") {
            $item->ignoreempty = true;
            $item->hidenoselect = true;
        }
        $handler = feedback_get_item_class($item->typ);
        $handler->set_data($item);
        $saved = $handler->save_item();
        if (!$saved) {
            throw new api_exception("feedback_item_save_failed", 409);
        }
        return ["saved" => true, "itemid" => (int)$saved->id, "cmid" => (int)$a["cmid"],
            "items_sha256" => self::items_hash($feedbackid)];
    }

    /**
     * Reports are deliberately restricted to a full-group-viewing teacher.
     */
    private static function reporting(array $a, authenticated_identity $identity): array {
        global $CFG;
        [$cm, $feedback, $context] = self::target((int)$a["cmid"], $identity, "mod/feedback:viewreports");
        require_once($CFG->libdir . "/grouplib.php");
        if (groups_get_activity_groupmode($cm) === SEPARATEGROUPS &&
                !has_capability("moodle/site:accessallgroups", $context, $identity->userid)) {
            throw new api_exception("group_scoped_feedback_report_unavailable", 403);
        }
        return [$cm, $feedback, $context];
    }

    /**
     * @return array Counts only, with small anonymous cohorts suppressed.
     */
    public static function summary(array $a, authenticated_identity $identity): array {
        global $DB;
        [$cm, $feedback] = self::reporting($a, $identity);
        $count = $DB->count_records("feedback_completed", ["feedback" => $feedback->id]);
        $anonymous = (int)$feedback->anonymous === FEEDBACK_ANONYMOUS_YES;
        $suppressed = $anonymous && $count < 5;
        return ["cmid" => (int)$cm->id, "anonymous" => $anonymous,
            "responses_total" => $suppressed ? null : $count,
            "suppressed_small_anonymous_cohort" => $suppressed,
            "question_count" => $DB->count_records("feedback_item", ["feedback" => $feedback->id])];
    }

    /**
     * @return array Per-question aggregated choice counts; anonymous free-text is never exported.
     */
    public static function responses(array $a, authenticated_identity $identity): array {
        global $DB;
        [$cm, $feedback] = self::reporting($a, $identity);
        $item = $DB->get_record("feedback_item", ["feedback" => $feedback->id,
            "id" => (int)$a["itemid"]], "*", MUST_EXIST);
        $count = $DB->count_records("feedback_completed", ["feedback" => $feedback->id]);
        $anonymous = (int)$feedback->anonymous === FEEDBACK_ANONYMOUS_YES;
        if ($anonymous && $count < 5) {
            return ["cmid" => (int)$cm->id, "itemid" => (int)$item->id,
                "anonymous" => true, "suppressed_small_cohort" => true, "responses" => []];
        }
        $values = feedback_get_group_values($item);
        if ($item->typ === "multichoice") {
            $options = self::choice_options($item);
            $tallies = array_fill(0, count($options), 0);
            foreach ($values as $value) {
                foreach (explode("|", $value->value) as $choice) {
                    $index = (int)$choice - 1;
                    if (ctype_digit(trim($choice)) && isset($tallies[$index])) {
                        $tallies[$index]++;
                    }
                }
            }
            $rows = [];
            foreach ($options as $i => $label) {
                $rows[] = ["option" => $label, "count" => $tallies[$i]];
            }
            return ["cmid" => (int)$cm->id, "itemid" => (int)$item->id,
                "anonymous" => $anonymous, "responses" => $rows, "aggregated" => true];
        }
        // Even for identified surveys, free text is sensitive. Return only the aggregate count.
        return ["cmid" => (int)$cm->id, "itemid" => (int)$item->id,
            "anonymous" => $anonymous, "aggregated" => true,
            "text_responses_count" => count($values), "responses" => [],
            "text_payloads_excluded" => true];
    }
}
