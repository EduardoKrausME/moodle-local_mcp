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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Question bank MCP integration.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\question;

use context;
use context_course;
use context_module;
use core_question\category_manager;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\security\capability_guard;

/**
 * Read question bank records and write using Moodle's versioned question APIs.
 */
final class bank_service {
    private const WRITABLE_TYPES = ['multichoice', 'truefalse', 'shortanswer', 'essay'];

    /** Get the owning context for a question category. */
    public static function category_context(int $categoryid): context {
        global $DB;
        $id = $DB->get_field('question_categories', 'contextid', ['id' => $categoryid], MUST_EXIST);
        return context::instance_by_id((int)$id, MUST_EXIST);
    }

    /** Return question, version and bank entry in one record. */
    public static function row(int $questionid): \stdClass {
        global $DB;
        return $DB->get_record_sql(
            "SELECT q.*, v.version, v.status, v.questionbankentryid,
                    e.questioncategoryid, e.idnumber, e.ownerid
               FROM {question} q
               JOIN {question_versions} v ON v.questionid = q.id
               JOIN {question_bank_entries} e ON e.id = v.questionbankentryid
              WHERE q.id = :id",
            ['id' => $questionid], MUST_EXIST);
    }

    /** Return context for an individual version. */
    public static function question_context(int $id): context {
        return self::category_context((int)self::row($id)->questioncategoryid);
    }

    /** Authorisation for own/all questions, including answers. */
    public static function authorize(\stdClass $q, authenticated_identity $identity, bool $edit = false): void {
        $ctx = self::category_context((int)$q->questioncategoryid);
        $all = $edit ? 'moodle/question:editall' : 'moodle/question:viewall';
        $mine = $edit ? 'moodle/question:editmine' : 'moodle/question:viewmine';
        if (!has_capability($all, $ctx, $identity->userid) &&
            ((int)$q->ownerid !== $identity->userid ||
            !has_capability($mine, $ctx, $identity->userid))) {
            throw new api_exception('permission_denied', 403);
        }
    }

    /** Discover the legacy course context and the quiz/qbank module contexts. */
    public static function banks(int $courseid, authenticated_identity $identity): array {
        global $DB;
        $banks = [];
        $ctx = context_course::instance($courseid, MUST_EXIST);
        if (has_any_capability(['moodle/question:viewmine', 'moodle/question:viewall'],
                $ctx, $identity->userid)) {
            $banks[] = ['contextid' => (int)$ctx->id, 'courseid' => $courseid,
                'cmid' => null, 'type' => 'course', 'name' => get_course($courseid)->fullname];
        }
        $sql = "SELECT cm.id, cm.instance, m.name AS modname
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.course = :courseid AND m.name IN ('qbank', 'quiz')
              ORDER BY cm.id";
        foreach ($DB->get_records_sql($sql, ['courseid' => $courseid]) as $cm) {
            $context = context_module::instance((int)$cm->id, IGNORE_MISSING);
            if (!$context || !has_any_capability(['moodle/question:viewmine',
                'moodle/question:viewall'], $context, $identity->userid)) {
                continue;
            }
            $instance = $DB->get_record($cm->modname, ['id' => $cm->instance], 'id, name');
            $banks[] = ['contextid' => (int)$context->id, 'courseid' => $courseid,
                'cmid' => (int)$cm->id, 'type' => $cm->modname,
                'name' => $instance ? $instance->name : $cm->modname];
        }
        return ['count' => count($banks), 'banks' => $banks];
    }

    /** List categories for exactly one context (bank). */
    public static function categories(int $contextid): array {
        global $DB;
        $rows = $DB->get_records('question_categories', ['contextid' => $contextid],
            'sortorder ASC, id ASC', 'id, contextid, parent, name, info, infoformat, idnumber, sortorder');
        $out = [];
        foreach ($rows as $c) {
            $out[] = ['id' => (int)$c->id, 'contextid' => (int)$c->contextid,
                'parentid' => (int)$c->parent, 'name' => $c->name, 'info' => $c->info,
                'infoformat' => (int)$c->infoformat, 'idnumber' => $c->idnumber,
                'sortorder' => (int)$c->sortorder];
        }
        return ['count' => count($out), 'categories' => $out];
    }

    /**
     * Search latest question versions with pagination and ownership filtering.
     * Defaults to ready-only, excluding drafts and hidden versions.
     */
    public static function search(array $a, authenticated_identity $identity): array {
        global $DB;
        $categoryid = (int)$a['categoryid'];
        $ctx = self::category_context($categoryid);
        $where = ['e.questioncategoryid = :categoryid'];
        $params = ['categoryid' => $categoryid];
        if (!has_capability('moodle/question:viewall', $ctx, $identity->userid)) {
            $where[] = 'e.ownerid = :ownerid';
            $params['ownerid'] = $identity->userid;
        }
        if (empty($a['includehidden'])) {
            $where[] = "v.status = 'ready'";
        }
        if (!empty($a['qtype'])) {
            $where[] = 'q.qtype = :qtype';
            $params['qtype'] = (string)$a['qtype'];
        }
        $query = trim((string)($a['query'] ?? ''));
        if ($query !== '') {
            $where[] = '(' . $DB->sql_like('q.name', ':namematch', false) . ' OR '
                . $DB->sql_like('q.questiontext', ':textmatch', false) . ')';
            $params['namematch'] = '%' . $DB->sql_like_escape($query) . '%';
            $params['textmatch'] = '%' . $DB->sql_like_escape($query) . '%';
        }
        $sql = "SELECT q.id, q.name, q.qtype, q.defaultmark, v.version, v.status,
                       e.id AS bankentryid, e.idnumber
                  FROM {question_bank_entries} e
                  JOIN {question_versions} v ON v.questionbankentryid = e.id
                  JOIN {question} q ON q.id = v.questionid
                 WHERE " . implode(' AND ', $where) . "
                   AND v.version = (SELECT MAX(v2.version) FROM {question_versions} v2
                                     WHERE v2.questionbankentryid = e.id)
              ORDER BY e.id ASC";
        $limit = min(100, max(1, (int)($a['limit'] ?? 50)));
        $offset = max(0, (int)($a['offset'] ?? 0));
        $rs = $DB->get_recordset_sql($sql, $params, $offset, $limit + 1);
        $out = [];
        try {
            foreach ($rs as $q) {
                if (count($out) === $limit) {
                    break;
                }
                $out[] = ['questionid' => (int)$q->id, 'bankentryid' => (int)$q->bankentryid,
                    'name' => $q->name, 'qtype' => $q->qtype,
                    'version' => (int)$q->version, 'status' => $q->status,
                    'defaultmark' => (float)$q->defaultmark, 'idnumber' => $q->idnumber];
            }
        } finally {
            $rs->close();
        }
        // Use a limit+1 SQL page to detect more without returning unbounded results.
        $hasmore = count($DB->get_records_sql($sql, $params, $offset + $limit, 1)) > 0;
        return ['categoryid' => $categoryid, 'offset' => $offset,
            'count' => count($out), 'hasmore' => $hasmore, 'questions' => $out];
    }

    /** Return question authoring data including answers, guarded by question permissions. */
    public static function details(int $id, authenticated_identity $identity): array {
        global $DB;
        $q = self::row($id);
        self::authorize($q, $identity);
        $options = [];
        $optiontables = ['multichoice' => ['qtype_multichoice_options', 'questionid'],
            'shortanswer' => ['qtype_shortanswer_options', 'questionid'],
            'essay' => ['qtype_essay_options', 'questionid']];
        if (isset($optiontables[$q->qtype])) {
            [$table, $column] = $optiontables[$q->qtype];
            $r = $DB->get_record($table, [$column => $id]);
            if ($r) {
                if ($q->qtype === 'multichoice') {
                    $options = ['single' => (bool)$r->single, 'shuffleanswers' => (bool)$r->shuffleanswers];
                } else if ($q->qtype === 'shortanswer') {
                    $options = ['usecase' => (bool)$r->usecase];
                } else {
                    $options = ['responseformat' => $r->responseformat];
                }
            }
        }
        $answers = [];
        foreach ($DB->get_records('question_answers', ['question' => $id], 'id ASC') as $answer) {
            $answers[] = ['id' => (int)$answer->id, 'text' => $answer->answer,
                'fraction' => (float)$answer->fraction, 'feedback' => $answer->feedback];
        }
        return ['questionid' => (int)$q->id, 'bankentryid' => (int)$q->questionbankentryid,
            'categoryid' => (int)$q->questioncategoryid,
            'contextid' => (int)self::category_context((int)$q->questioncategoryid)->id,
            'version' => (int)$q->version, 'status' => $q->status,
            'qtype' => $q->qtype, 'name' => $q->name, 'idnumber' => $q->idnumber,
            'questiontext' => $q->questiontext, 'questiontextformat' => (int)$q->questiontextformat,
            'generalfeedback' => $q->generalfeedback, 'defaultmark' => (float)$q->defaultmark,
            'answers' => $answers, 'options' => $options];
    }

    /** Validate an entire question payload before saving; never store arbitrary qtype data. */
    public static function form(array $a, string $qtype): \stdClass {
        if (!in_array($qtype, self::WRITABLE_TYPES, true)) {
            throw new api_exception('unsupported_question_type', 400);
        }
        $name = trim(clean_param((string)($a['name'] ?? ''), PARAM_TEXT));
        $text = trim((string)($a['questiontext'] ?? ''));
        $mark = (float)($a['defaultmark'] ?? 1);
        $status = (string)($a['status'] ?? 'ready');
        if ($name === '' || $text === '' || !is_finite($mark) || $mark <= 0 ||
                !in_array($status, ['ready', 'draft'], true)) {
            throw new api_exception('invalid_question', 400);
        }
        $f = (object)['name' => $name, 'defaultmark' => $mark, 'status' => $status,
            'penalty' => 0, 'idnumber' => clean_param((string)($a['idnumber'] ?? ''), PARAM_TEXT),
            'questiontext' => ['text' => clean_param($text, PARAM_CLEANHTML), 'format' => FORMAT_HTML],
            'generalfeedback' => ['text' => clean_param((string)($a['generalfeedback'] ?? ''),
                PARAM_CLEANHTML), 'format' => FORMAT_HTML]];
        if ($qtype === 'truefalse') {
            if (!isset($a['correctanswer']) || !is_bool($a['correctanswer'])) {
                throw new api_exception('correctanswer_required', 400);
            }
            $f->correctanswer = (int)$a['correctanswer'];
            $f->feedbacktrue = ['text' => '', 'format' => FORMAT_HTML];
            $f->feedbackfalse = ['text' => '', 'format' => FORMAT_HTML];
            $f->showstandardinstruction = 1;
        } else if ($qtype === 'multichoice' || $qtype === 'shortanswer') {
            $answers = $a['answers'] ?? [];
            if (!is_array($answers) || count($answers) < ($qtype === 'multichoice' ? 2 : 1) ||
                count($answers) > 50) {
                throw new api_exception('invalid_answers', 400);
            }
            $f->answer = [];
            $f->fraction = [];
            $f->feedback = [];
            $correct = 0;
            $sum = 0.0;
            foreach ($answers as $answer) {
                if (!is_array($answer) || !is_string($answer['text'] ?? null) ||
                    trim($answer['text']) === '' || !is_numeric($answer['fraction'] ?? null)) {
                    throw new api_exception('invalid_answers', 400);
                }
                $fraction = (float)$answer['fraction'];
                if (!is_finite($fraction) || $fraction < -1 || $fraction > 1) {
                    throw new api_exception('invalid_answer_fraction', 400);
                }
                $correct += $fraction >= 0.999999 ? 1 : 0;
                $sum += max(0, $fraction);
                $value = clean_param($answer['text'],
                    $qtype === 'multichoice' ? PARAM_CLEANHTML : PARAM_TEXT);
                $f->answer[] = $qtype === 'multichoice' ? ['text' => $value, 'format' => FORMAT_HTML] : $value;
                $f->fraction[] = $fraction;
                $f->feedback[] = ['text' => clean_param((string)($answer['feedback'] ?? ''),
                    PARAM_CLEANHTML), 'format' => FORMAT_HTML];
            }
            if ($qtype === 'multichoice') {
                $f->single = (int)($a['single'] ?? true);
                $f->shuffleanswers = (int)($a['shuffleanswers'] ?? true);
                $f->answernumbering = 'abc';
                $f->showstandardinstruction = 1;
                $f->correctfeedback = ['text' => '', 'format' => FORMAT_HTML];
                $f->partiallycorrectfeedback = ['text' => '', 'format' => FORMAT_HTML];
                $f->incorrectfeedback = ['text' => '', 'format' => FORMAT_HTML];
                if ($f->single && $correct !== 1) {
                    throw new api_exception('single_choice_requires_one_correct_answer', 400);
                }
                if (!$f->single && abs($sum - 1.0) > 0.00001) {
                    throw new api_exception('multiple_choice_fractions_must_total_one', 400);
                }
            } else {
                $f->usecase = (int)($a['usecase'] ?? false);
                if (!$correct) {
                    throw new api_exception('shortanswer_requires_full_credit_answer', 400);
                }
            }
        } else {
            $f->responseformat = 'editor';
            $f->responserequired = 1;
            $f->responsefieldlines = 15;
            $f->attachments = 0;
            $f->attachmentsrequired = 0;
            $f->graderinfo = ['text' => '', 'format' => FORMAT_HTML];
            $f->responsetemplate = ['text' => '', 'format' => FORMAT_HTML];
        }
        return $f;
    }

    /**
     * Core qtype save_question creates new version records and triggers Moodle events.
     * Updates must be based on the current version (optimistic locking).
     */
    public static function save(array $a, authenticated_identity $identity, ?int $oldid = null): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/questionlib.php');
        $old = $oldid ? self::row($oldid) : null;
        if ($old) {
            self::authorize($old, $identity, true);
            $latest = $DB->get_field_sql("SELECT MAX(version) FROM {question_versions}
                WHERE questionbankentryid = :entryid", ['entryid' => $old->questionbankentryid]);
            if ((int)$old->version !== (int)$latest ||
                (int)($a['expectedversion'] ?? 0) !== (int)$latest) {
                throw new api_exception('question_version_conflict', 409);
            }
            $categoryid = (int)$old->questioncategoryid;
            $qtype = $old->qtype;
            $a['idnumber'] = $a['idnumber'] ?? $old->idnumber ?? '';
            $a['defaultmark'] = $a['defaultmark'] ?? $old->defaultmark;
        } else {
            $categoryid = (int)$a['categoryid'];
            $qtype = (string)$a['qtype'];
        }
        $ctx = self::category_context($categoryid);
        if (!$old) {
            capability_guard::check('moodle/question:add', $ctx, $identity->userid);
        }
        $form = self::form($a, $qtype);
        $form->category = $categoryid . ',' . $ctx->id;
        $base = $old ?: (object)['qtype' => $qtype, 'category' => $categoryid];
        $result = \question_bank::get_qtype($qtype)->save_question($base, $form);
        $new = self::row((int)$result->id);
        return ['questionid' => (int)$new->id, 'bankentryid' => (int)$new->questionbankentryid,
            'categoryid' => $categoryid, 'version' => (int)$new->version,
            'status' => $new->status, 'qtype' => $qtype, 'updated' => (bool)$old];
    }

    /** Validate question category creation prior to preview or execution. */
    public static function validate_category(array $a, authenticated_identity $identity): array {
        global $DB;
        $ctx = context::instance_by_id((int)$a['contextid'], MUST_EXIST);
        if (!in_array((int)$ctx->contextlevel,
            [CONTEXT_SYSTEM, CONTEXT_COURSECAT, CONTEXT_COURSE, CONTEXT_MODULE], true)) {
            throw new api_exception('invalid_question_context', 400);
        }
        capability_guard::check('moodle/question:managecategory', $ctx, $identity->userid);
        $name = trim(clean_param((string)$a['name'], PARAM_TEXT));
        $parent = (int)($a['parentid'] ?? 0);
        $idnumber = clean_param((string)($a['idnumber'] ?? ''), PARAM_TEXT);
        $info = clean_param((string)($a['info'] ?? ''), PARAM_CLEANHTML);
        if ($name === '') {
            throw new api_exception('invalid_category', 400);
        }
        if ($parent > 0 && (int)$DB->get_field('question_categories', 'contextid',
            ['id' => $parent], MUST_EXIST) !== (int)$ctx->id) {
            throw new api_exception('invalid_category_parent_context', 400);
        }
        $mgr = new category_manager();
        if (!$mgr->idnumber_is_unique_in_context($idnumber, (int)$ctx->id)) {
            throw new api_exception('duplicate_category_idnumber', 409);
        }
        return [(int)$ctx->id, $name, $parent, $idnumber, $info];
    }

    /** Create a question category through the core API and events. */
    public static function create_category(array $a, authenticated_identity $identity): array {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        [$ctxid, $name, $parent, $idnumber, $info] = self::validate_category($a, $identity);
        $manager = new category_manager();
        $id = $manager->add_category($parent . ',' . $ctxid, $name, $info,
            (string)FORMAT_HTML, $idnumber ?: null);
        return ['id' => $id, 'contextid' => $ctxid, 'parentid' => $parent, 'name' => $name];
    }
}
