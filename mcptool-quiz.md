# Quiz and question-bank MCP tools

The bundled `mcptool_quiz` extends the existing `quiz_create_activity`
and `quiz_add_question` with new tools.

READ:
- `quiz_get_question_slots`: positions, page numbers, references and marks.
- `quiz_get_attempt_responses`: submitted response variables for one finished attempt.
- `quiz_get_attempt_statistics`: sampled finished-attempt statistics.
- `quiz_get_question_statistics`: sampled mean score fraction and raw correlation.
- `quiz_get_question_versions`: available question bank versions for a fixed slot.
- `quiz_get_question_usage`: other authorized uses in quizzes within the same course.

WRITE, with the normal MCP preview and one-use confirmation:
- `quiz_update_activity`: selected quiz settings only, retaining unrelated fields.
- `quiz_remove_question`: remove a slot, but only before real attempts.
- `quiz_reorder_questions`: move a slot within a valid page, only before real attempts.
- `quiz_regrade_attempt`: regrade one finished attempt and update the Moodle gradebook.

The quiz layout tools use Moodle's native `mod_quiz\structure`. They do not
permit structural changes after real student attempts. Both require the
`expected_layout_sha256` value obtained from `quiz_get_question_slots`,
and reject stale layout signatures. The regrade operation
requires `mod/quiz:regrade`, a finished non-preview attempt, authorization for
the student's separate group, and matching `expected_timemodified` and
`expected_sumgrades`. Teachers should read a fresh `quiz_get_attempt_responses` record (including
`timemodified` and `sumgrades`) before requesting regrading. Each operation is individually confirmed; there is no
bulk or automatic regrading. Version compatibility is checked by Moodle's
overview report regrade engine. The final mark is recomputed by Moodle's native
quiz grade calculator, not written directly into the gradebook.

Read tools never return answer keys. Attempt response inspection additionally
requires `mod/quiz:grade`; reports respect activity group visibility and
active enrolment. Response values are bounded and marked untrusted. Statistical
reports use the first 500 finished attempts, exclude previews, suppress
results with fewer than five eligible attempts, and explicitly label their
sampled descriptive indicators. These are **not** a substitute for Moodle's
official, cached quiz statistics report (e.g. corrected discrimination index).

Question versions and usage queries require quiz management rights. Cross-course
usage and inaccessible activities are excluded.
