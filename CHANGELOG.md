# Changelog

## Unreleased - 2026-10-24

- Added `assign_create_activity` for Moodle Assignment activities with configurable submissions, grades, and deadlines, including option validation and duplicate-name protection.
- Added `delete_course_module` for permanently removing an explicitly identified course module through Moodle's core API, with permission checks, a reviewed preview, and mandatory destructive-operation confirmation.
- Added regression tests and documentation for both tools and their safety checks.

**Deployment:** updating this repository does not install the plugin on a Moodle site. Deploy the files, run Moodle's plugin upgrade, and refresh MCP tool discovery before using the new APIs.
