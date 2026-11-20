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
 * Question storage for Moodle 3.10 and 3.11.
 *
 * @package local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_mcp\question;

use context;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\security\capability_guard;
use question_bank;
use stdClass;

final class legacy_question_bank {
    public static function category_context(int $categoryid): context {
        return versioned_question_bank::category_context($categoryid);
    }

    /**
     * In Moodle 3.x a question belongs directly to a category, without bank entries or version rows.
     * Its legacy version hash is converted to an integer concurrency token for the MCP API.
     */
    public static function row(int $questionid): stdClass {
        global $DB;
        $q = $DB->get_record('question', ['id' => $questionid], '*', IGNORE_MISSING);
        if (!$q) {
            throw new api_exception('question_not_found', 404);
        }
        $q->questioncategoryid = (int)$q->category;
        $q->questionbankentryid = (int)$q->id;
        $q->ownerid = (int)$q->createdby;
        $q->status = empty($q->hidden) ? 'ready' : 'hidden';
        $q->version = self::revision($q);
        return $q;
    }

    /**
     * Moodle 3.x does not have question_versions. This token is not a native version number.
     */
    private static function revision(stdClass $q): int {
        return (int)hexdec(substr(hash('sha256', (string)$q->version), 0, 8));
    }

    public static function question_context(int $id): context {
        return self::category_context((int)self::row($id)->questioncategoryid);
    }

    public static function authorize(stdClass $q, authenticated_identity $identity, bool $edit = false): void {
        versioned_question_bank::authorize($q, $identity, $edit);
    }

    public static function banks(int $courseid, authenticated_identity $identity): array {
        return versioned_question_bank::banks($courseid, $identity);
    }

    public static function categories(int $contextid): array {
        return versioned_question_bank::categories($contextid);
    }

    public static function search(array $a, authenticated_identity $identity): array {
        global $DB;
        $categoryid = (int)$a['categoryid'];
        $context = self::category_context($categoryid);
        $where = ['q.category = :categoryid', 'q.parent = 0'];
        $params = ['categoryid' => $categoryid];
        if (!has_capability('moodle/question:viewall', $context, $identity->userid)) {
            if (!has_capability('moodle/question:viewmine', $context, $identity->userid)) {
                throw new api_exception('permission_denied', 403);
            }
            $where[] = 'q.createdby = :ownerid';
            $params['ownerid'] = $identity->userid;
        }
        if (empty($a['includehidden'])) {
            $where[] = 'q.hidden = 0';
        }
        if (!empty($a['qtype'])) {
            $where[] = 'q.qtype = :qtype';
            $params['qtype'] = (string)$a['qtype'];
        }
        $query = trim((string)($a['query'] ?? ''));
        if ($query !== '') {
            $where[] = '(' . $DB->sql_like('q.name', ':namematch', false) . ' OR ' .
                $DB->sql_like('q.questiontext', ':textmatch', false) . ')';
            $params['namematch'] = '%' . $DB->sql_like_escape($query) . '%';
            $params['textmatch'] = '%' . $DB->sql_like_escape($query) . '%';
        }
        $sql = 'SELECT q.* FROM {question} q WHERE ' . implode(' AND ', $where) . ' ORDER BY q.id ASC';
        $limit = min(100, max(1, (int)($a['limit'] ?? 50)));
        $offset = max(0, (int)($a['offset'] ?? 0));
        $records = $DB->get_records_sql($sql, $params, $offset, $limit + 1);
        $out = [];
        $hasmore = false;
        foreach ($records as $q) {
            if (count($out) >= $limit) {
                $hasmore = true;
                break;
            }
            $out[] = [
                'questionid' => (int)$q->id,
                'bankentryid' => (int)$q->id,
                'name' => $q->name,
                'qtype' => $q->qtype,
                'version' => self::revision($q),
                'status' => empty($q->hidden) ? 'ready' : 'hidden',
                'defaultmark' => (float)$q->defaultmark,
                'idnumber' => $q->idnumber,
            ];
        }
        return ['categoryid' => $categoryid, 'offset' => $offset,
            'count' => count($out), 'hasmore' => $hasmore, 'questions' => $out];
    }

    public static function details(int $id, authenticated_identity $identity): array {
        global $DB;
        $q = self::row($id);
        self::authorize($q, $identity);
        $options = [];
        $optiontables = [
            'multichoice' => ['qtype_multichoice_options', 'questionid'],
            'shortanswer' => ['qtype_shortanswer_options', 'questionid'],
            'essay' => ['qtype_essay_options', 'questionid'],
        ];
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
        return [
            'questionid' => (int)$q->id, 'bankentryid' => (int)$q->id,
            'categoryid' => (int)$q->category,
            'contextid' => (int)self::category_context((int)$q->category)->id,
            'version' => (int)$q->version, 'status' => $q->status,
            'qtype' => $q->qtype, 'name' => $q->name, 'idnumber' => $q->idnumber,
            'questiontext' => $q->questiontext, 'questiontextformat' => (int)$q->questiontextformat,
            'generalfeedback' => $q->generalfeedback, 'defaultmark' => (float)$q->defaultmark,
            'answers' => $answers, 'options' => $options,
        ];
    }

    public static function form(array $a, string $qtype): stdClass {
        return versioned_question_bank::form($a, $qtype);
    }

    public static function save(array $a, authenticated_identity $identity, ?int $oldid = null): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/questionlib.php');
        $old = $oldid ? self::row($oldid) : null;
        if ($old) {
            self::authorize($old, $identity, true);
            if ((int)($a['expectedversion'] ?? 0) !== (int)$old->version) {
                throw new api_exception('question_version_conflict', 409);
            }
            $categoryid = (int)$old->category;
            $qtype = (string)$old->qtype;
            $a['defaultmark'] = $a['defaultmark'] ?? $old->defaultmark;
            $a['idnumber'] = $a['idnumber'] ?? $old->idnumber ?? '';
        } else {
            $categoryid = (int)$a['categoryid'];
            $qtype = (string)$a['qtype'];
            capability_guard::check('moodle/question:add',
                self::category_context($categoryid), $identity->userid);
        }
        $form = self::form($a, $qtype);
        $context = self::category_context($categoryid);
        $form->category = $categoryid . ',' . $context->id;
        // Moodle 3.x updates the legacy question in place through its question type API.
        $base = $old ?: (object)['qtype' => $qtype, 'category' => $categoryid];
        $saved = question_bank::get_qtype($qtype)->save_question($base, $form);
        // Legacy hidden questions are the closest supported representation of a draft.
        $hidden = ($a['status'] ?? 'ready') === 'draft';
        $DB->set_field('question', 'hidden', $hidden ? 1 : 0, ['id' => $saved->id]);
        $new = self::row((int)$saved->id);
        return [
            'questionid' => (int)$new->id, 'bankentryid' => (int)$new->id,
            'categoryid' => (int)$new->category, 'version' => (int)$new->version,
            'status' => $new->status, 'qtype' => $qtype, 'updated' => (bool)$old,
        ];
    }

    public static function name_exists(int $categoryid, string $name): bool {
        global $DB;
        return $DB->record_exists('question', ['category' => $categoryid, 'name' => $name]);
    }

    public static function validate_category(array $a, authenticated_identity $identity): array {
        global $DB;
        $context = context::instance_by_id((int)$a['contextid'], MUST_EXIST);
        if (!in_array((int)$context->contextlevel,
                [CONTEXT_SYSTEM, CONTEXT_COURSECAT, CONTEXT_COURSE, CONTEXT_MODULE], true)) {
            throw new api_exception('invalid_question_context', 400);
        }
        capability_guard::check('moodle/question:managecategory', $context, $identity->userid);
        $name = trim(clean_param((string)$a['name'], PARAM_TEXT));
        $parentid = (int)($a['parentid'] ?? 0);
        $idnumber = clean_param((string)($a['idnumber'] ?? ''), PARAM_TEXT);
        $info = clean_param((string)($a['info'] ?? ''), PARAM_CLEANHTML);
        if ($name === '') {
            throw new api_exception('invalid_category', 400);
        }
        if ($parentid > 0 && (int)$DB->get_field('question_categories', 'contextid',
                ['id' => $parentid], MUST_EXIST) !== (int)$context->id) {
            throw new api_exception('invalid_question_category_parent', 400);
        }
        if ($idnumber !== '' && $DB->record_exists('question_categories',
                ['idnumber' => $idnumber, 'contextid' => $context->id])) {
            throw new api_exception('duplicate_category_idnumber', 409);
        }
        return [(int)$context->id, $name, $parentid, $idnumber, $info];
    }

    public static function create_category(array $a, authenticated_identity $identity): array {
        global $DB;
        [$contextid, $name, $parentid, $idnumber, $info] =
            self::validate_category($a, $identity);
        $category = (object)[
            'name' => $name, 'contextid' => $contextid, 'info' => $info,
            'infoformat' => FORMAT_HTML, 'stamp' => make_unique_id_code(),
            'parent' => $parentid, 'sortorder' => 999, 'idnumber' => $idnumber ?: null,
        ];
        $id = $DB->insert_record('question_categories', $category);
        $event = \core\event\question_category_created::create_from_question_category_instance(
            (object)['id' => $id, 'contextid' => $contextid]);
        $event->trigger();
        return ['id' => $id, 'contextid' => $contextid, 'parentid' => $parentid, 'name' => $name];
    }
}
