# Changelog

## Unreleased - 2026-10-24

- Added `assign_create_activity` for Moodle Assignment activities with configurable submissions, grades, and deadlines, including option validation and duplicate-name protection.
- Added `delete_course_module` for permanently removing an explicitly identified course module through Moodle's core API, with permission checks, a reviewed preview, and mandatory destructive-operation confirmation.
- Added regression tests and documentation for both tools and their safety checks.

- Added a persistent ordered workflow controller for multi-step Moodle changes, with status, cancellation, step-by-step progress and an optional Next UI; advancing never authorizes or performs the next WRITE.
- Fixed HTML introduction and description persistence in activity tools by synchronizing Moodle editor fields and consistently storing HTML format; added regression tests.
- Fixed Forum creation defaults for Moodle 4.5 and 5.3, including grade item ID numbers and forum grading settings.

**Deployment:** updating this repository does not install the plugin on a Moodle site. Deploy the files, run Moodle's plugin upgrade, and refresh MCP tool discovery before using the new APIs.

## 1.4.0 - 2026-10-23

- Added permission-scoped Assignment activity management, submissions, grading, feedback and progress tools; protected anonymous and group-scoped reporting.
- Added Quiz management, question-version operations, response analytics and protected single-attempt regrading, with safeguards against stale question-slot layouts.
- Added group membership administration and activity availability, group-mode and completion management tools with integration tests.
- Added Feedback activity and item management with anonymity-aware aggregate reporting and reviewed write operations.
- Added Lesson activity and page administration, including conditional navigation and jump configuration.
- Added gradebook configuration and grade history queries, course and activity completion criteria, and permission-scoped progress reports.
- Extended tests and permissions for hidden grades, student identities and supported Moodle module workflows.

## 1.3.27 - 2026-10-21

- Enabled per-user OAuth consent for authenticated non-guest users while enforcing active-account eligibility, course visibility, ownership and inherited capabilities.
- Strengthened OAuth client administration, grant revocation, rate limiting and authorization-code replay protection.
- Added JSON-schema validation of MCP tool arguments before context resolution, preserving original JSON types and supporting nested schemas.
- Improved Privacy API coverage for external clients and credentials and added revocation and schema-validation tests.
- Hardened handling of untrusted MCP read content, and made PHP error logging dependent on explicit MCP debugging.
- Improved localization, streamed OAuth client discovery and Moodle coding-style compliance.

## 1.3.26 - 2026-10-19

- Covered confirmation-token expiration in regression tests and added a 30-minute fallback lifetime for confirmation tokens.
