# Changelog

## Unreleased - 2026-10-20

- Reverted PR #2: removed the `assign_create_activity` and `delete_course_module` MCP WRITE tools and their dedicated tests. Other plugin features and the current version remain unchanged.

## 1.3.22 - 2026-10-13

- Added `assign_create_activity` for Moodle Assignment activities, supporting file submissions, online text, grading and optional deadlines.
- Added `delete_course_module` with explicit course, section, CMID, module type and exact-name verification, both at preview time and immediately before execution.
- Registered the new write tools with MCP confirmation requirements and Moodle capability checks.
- Added regression tests and documentation in `docs/course-module-maintenance.md`.
- Refactored assignment submission/date checks to reduce method complexity.

The change was introduced by [PR #2](https://github.com/EduardoKrausME/moodle-local_mcp/pull/2).

**Deployment:** updating this repository does not install the plugin on a Moodle site. Deploy the files, run Moodle's plugin upgrade and refresh MCP tool discovery before using the new APIs.
