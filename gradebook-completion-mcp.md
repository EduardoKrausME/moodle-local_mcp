# Gradebook and completion MCP tools (stage 5)

These tools extend the built-in `local_mcp` registries. Existing
`get_grades`, `get_progress`, `get_activity_completion` and
`set_activity_completion` remain unchanged.

## READ operations

- `get_gradebook_structure(courseid, limit?, offset?)`: up to 100 items per
  page, categories, ordering, ranges, passing grade, visibility and lock status.
  Requires `moodle/grade:manage`. Returns a per-item `item_sha256` token
  for safe subsequent edits. No student grades are returned.
- `get_grade_history(courseid, userid, itemid?, limit?, offset?)`: latest
  gradebook history snapshots for a currently enrolled participant.
  Requires `moodle/grade:viewall`; hidden grades also require
  `moodle/grade:viewhidden`, otherwise those records are filtered.
  Refuses cross-group requests when the
  course uses separate groups and the actor shares no group with the participant.
  Returns grade values but **never grading feedback, comments, IP addresses,
  grader identities or other personal metadata**. Up to 50 rows per call.
- `get_course_completion_criteria(courseid)`: completion criteria and
  aggregation from native `completion_info`.
- `get_course_completion_status(courseid, userid)`: status of each criterion,
  recorded course completion date and user-visible activity states with
  timestamps. Students can read their **own** data; viewing another enrollee
  requires `moodle/grade:viewall` and respects separate groups.
- `get_course_progress_report(courseid, groupid?, limit?, offset?)`:
  paginated progress for actively enrolled users, at most 25 at a time.
  Requires `moodle/grade:viewall`. When separate groups are active and
  the actor lacks `moodle/site:accessallgroups`, selecting a group is mandatory.
  A selected group must belong to this course, and the actor must belong to
  it unless permitted to access all groups. Returns user ID and aggregate
  progress only, not names, emails, raw grades, or private submissions.

All progress reports use native `completion_info::get_data()` to respect
activity rules and user-visible course modules. The course completion status
is distinct from activity progress percentage; 100% activity progress alone
does not imply meeting all course completion criteria.

## WRITE operation

`set_grade_item_visibility(courseid, itemid, hidden, expected_item_sha256)`
hides or reveals a **module grade item**. The editor needs both
`moodle/grade:hide` and `moodle/grade:manage`.
The operation cannot change a course-total/category item or an item awaiting
deletion. The preview reports old/new visibility, confirms that cascading is
disabled and that grade values remain unchanged. It checks the hash
provided by `get_gradebook_structure` again when executing after MCP's
single-use user confirmation. The actual update uses
`grade_item::set_hidden(..., false)` rather than direct table writes.

This operation changes grade visibility to learners and is not a replacement
for releasing assessment grades through a module's grading workflow.

## Validation

Supported: Moodle 4.5 LTS and 5.3, with MariaDB and PostgreSQL CI.
No schema, new personal-data storage, UI renderer or root version change.
