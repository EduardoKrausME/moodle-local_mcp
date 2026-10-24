# Feedback and Lesson integrations (stage 4)

These bundled `mcptool_*` subplugins add Moodle-native Feedback and Lesson
tools, with separate READ / WRITE discovery through `local_mcp`.

## Feedback (`mcptool_feedback`)

READ: `feedback_get_questions`, `feedback_get_summary`,
`feedback_get_responses`.

WRITE: `feedback_create_activity`, `feedback_add_question`,
`feedback_update_question`.

Surveys may be created anonymous or identified. Item creation and updates use
Moodle's `feedback_get_item_class()->save_item()` handlers, rather than
writing arbitrary choice records. Supported questions are radio-button
`multichoice` (2–20 labelled answers) and `textarea`. The exact
`expected_items_sha256` value from `feedback_get_questions` is required
for every change. Existing submitted or in-progress question responses
prevent modification of that question.

`feedback_get_summary` and `feedback_get_responses` need
`mod/feedback:viewreports`. When group mode is **Separate groups**, users
without `moodle/site:accessallgroups` cannot request global reports.
Anonymous responses are not exposed individually. Aggregation for an
anonymous survey with fewer than five submissions is suppressed.
Choice questions yield aggregate counts, never submission IDs or user IDs.
Text responses are returned as counts only, including in identified surveys.
This prevents accidental disclosure of identifiable free-text answers.

## Lesson (`mcptool_lesson`)

READ: `lesson_get_navigation`, `lesson_get_page`.

WRITE: `lesson_create_activity`, `lesson_create_page`,
`lesson_update_page`, `lesson_set_jumps`.

All page operations require `mod/lesson:edit`.
Activities use Moodle's `add_moduleinfo()`, with max answer count 2–10.
Pages use Moodle's `lesson_page::create()` and editor file management.
Supported pages are content branching pages and single-answer multichoice
pages. Each page has choices with their own destinations:
`next`, `previous`, `end` or an existing positive page ID belonging
to the same lesson. This enables branching navigation without duplicating
questions or modifying unrelated lessons.

Read the `navigation_sha256` from `lesson_get_navigation` before any
page or destination change. Every WRITE uses the MCP preview and one-time
confirmation process and rechecks the hash and all destination IDs.
Changes to pages or navigation are rejected if students have attempted the
lesson, to avoid rewriting history.

No student attempts, responses, grades, or private data are published by the
Lesson READ tools: they expose teacher-only authored content and navigation.

## Deployment

Install the new bundled subplugins using Moodle's standard upgrade page or
CLI, then refresh MCP tool discovery. Both subplugin versions use Moodle 4.5
as minimum, and the parent plugin's root `version.php` remains untouched.
