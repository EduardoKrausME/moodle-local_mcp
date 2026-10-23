# Assignment tools (mcptool_assign)

Install/upgrade this bundled subplugin with Moodle's normal upgrade process and
refresh the MCP connection. The existing local_mcp assignment feedback tools remain available.

READ: `assign_get_submission_history`, `assign_get_grading_progress`,
`assign_get_extensions`, `assign_get_grading_guide`,
`assign_get_submission_document`.

WRITE (explicit preview and one-use confirmation): `assign_create_activity`,
`assign_update_activity`, `assign_set_extension`, `assign_reopen_submission`,
`assign_save_grade_and_feedback`, `assign_apply_rubric_draft`,
`assign_set_marking_workflow`, `assign_release_grades`.

Only assigned graders can see learner-specific data. Group membership and
blind marking restrictions are enforced for these individual-submission tools;
team and blind-marking workflows must use their dedicated Moodle UI.
The progress tool is paginated, with a maximum of 50 records at once.

Document text extraction is limited to 5 MB and 100,000 characters.
Plain UTF-8 text and DOCX (when ZipArchive/DOM are available) are supported.
PDF text extraction requires an already installed Smalot PDF Parser; when no
converter is installed, PDF contents are not guessed or silently sent to an LLM.
Other binary types return an explicit unsupported-converter error.

Grade-and-feedback writes require an existing final submission, current
submission timestamp, current feedback SHA-256, simple grading and an unlocked
gradebook. The operation calls Moodle's `assign::save_grade()` API and
suppresses student notifications. Without marking workflow Moodle can still
show the resulting grade according to its regular visibility rules.

Rubric drafts require an active rubric **and** marking workflow; they persist
a reviewer-owned advanced grading form instance but **do not submit the grade
or publish feedback**. The reviewer must finalize the grade in Moodle.
Marking workflow changes require stage-appropriate capabilities; release is
an independent, individually confirmed operation.

`assign_reopen_submission` reverts an existing submitted attempt to draft;
it does not create a new numbered attempt. Existing attempts remain accessible.
