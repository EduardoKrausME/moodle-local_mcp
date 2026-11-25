# Changelog

- Standardize activity creation descriptions as sanitized HTML with a private format and course-page display enabled by default; accept explicit opt-out and keep the legacy label content alias.

- Audit MCP Apps host capability negotiation, reject unknown bridge versions, support text-only results, preserve cancellation during in-flight messaging, and redact metadata inside embedded JSON arrays. Extend JavaScript and logger regression tests.

- Modernize the workflow MCP App with the standard JSON-RPC host bridge, legacy ChatGPT fallback, accessible localized states, dark mode and safe recovery actions. Bind widget navigation to the expected workflow step and generation, restrict WRITE tool visibility to the model, and omit host metadata and app HTML from protocol logs.

- Bind the completion replacement confirmation to reviewed content and evidence counts; verify the published revision integrity and course-page visibility. Test stale content and unaggregated completion evidence.

- Audit activity replacement: bind source ordering to move previews, verify both section sequences, serialize confirmation token consumption, retain executing workflows, correlate failed stages with diagnostic references, reject malformed dependencies and report completed student counts.

- Hardened module positioning with course-specific locking, optimistic position hashes and legacy module metadata. Record failed workflow stages and permit a fresh confirmed retry only for idempotent moves. Added conservative view-only activity criterion replacement preserving historical records and aggregation, with dependency checks and explicit operator review.

- Preserve exact HTML, CSS and JavaScript source-code arguments during WRITE typography normalization, including nested values; ordinary text and credential handling remain unchanged. Added byte-integrity regression coverage.

- Isolated missing or broken built-in READ and WRITE tool classes during discovery, preserving healthy tools and reserving unavailable built-in names against extension takeover.

- Fixed the public error fallback to use the localized generic message directly, including HTTP dispatch errors, so Moodle debug-mode exception messages cannot expose private diagnostics.

## Unreleased - 2026-10-24

- Isolated failing tool providers and malformed definitions, excluded ambiguous names, preserved fail-closed access policies and cached the registry per request.
- Prevented registry reloads during failure auditing, added references to unexpected HTTP errors and exposed the running build in discovery and diagnostic logs.
- Isolated optional message tracing during initialization, request parsing and notification responses; reject missing WRITE scopes before invoking tool metadata and cover both paths with regression tests.

- Added confirmed native move_activity, hide_activity and show_activity tools, with a reusable position service, same-course validation, permission checks and regression tests.

- Added allowlisted native Moodle error mapping and opt-in public API messages without exposing debug details, preserving confirmation and unexpected-error handling.
- Clarified tool rediscovery after plugin upgrades or missing tools, without bypassing permissions.

- Added `assign_create_activity` for Moodle Assignment activities with configurable submissions, grades, and deadlines, including option validation and duplicate-name protection.
- Added `delete_course_module` for permanently removing an explicitly identified course module through Moodle's core API, with permission checks, a reviewed preview, and mandatory destructive-operation confirmation.
- Added regression tests and documentation for both tools and their safety checks.

- Added a persistent ordered workflow controller for multi-step Moodle changes, with status, cancellation, step-by-step progress and an optional Next UI; advancing never authorizes or performs the next WRITE.
- Fixed HTML introduction and description persistence in activity tools by synchronizing Moodle editor fields and consistently storing HTML format; added regression tests.
- Fixed Forum creation defaults for Moodle 4.5 and 5.3, including grade item ID numbers and forum grading settings.

**Deployment:** updating this repository does not install the plugin on a Moodle site. Deploy the files, run Moodle's plugin upgrade, and refresh MCP tool discovery before using the new APIs.

## 1.4.8 - 2026-11-24

- Normalized blank-line spacing in the extension manager, Assignment tool, documentation and test files to match Moodle coding standards.
- Updated release metadata without adding new functional tools in this tag.

**Comparison:** [1.4.7...1.4.8](https://github.com/EduardoKrausME/moodle-local_mcp/compare/1.4.7...1.4.8)

## 1.4.7 - 2026-11-23

- Extended the Page content READ tool to expose original HTML, formatting and a manifest of embedded images.
- Added a permission-checked `copy_activity_images` WRITE tool to copy authorized Moodle files into target activities, with preview, conflict detection, idempotency checks and transaction rollback.
- Added documentation and integration tests for Page-to-MCP content and image migration.

**Comparison:** [1.4.6...1.4.7](https://github.com/EduardoKrausME/moodle-local_mcp/compare/1.4.6...1.4.7)

## 1.4.6 - 2026-11-21

- Added Moodle 3.10+ compatibility adapters for question-bank operations and extended legacy compatibility CI coverage.
- Added a self-service MCP connection guide for Moodle users, with related administration and language-string updates.
- Reworked PHP 8-only syntax in shared security and protocol classes for PHP 7.4 compatibility and normalized coding style.
- Added a repository security reporting policy.

**Comparison:** [1.4.4...1.4.6](https://github.com/EduardoKrausME/moodle-local_mcp/compare/1.4.4...1.4.6)

## 1.4.4 - 2026-11-09

- Advertised the combined `server.php` endpoint as the OAuth issuer while retaining legacy issuer discovery compatibility.
- Removed the dynamic client registration toggle and its registration-endpoint check.
- Redesigned the connection guide with ChatGPT, Claude and other AI tabs, direct OAuth metadata links and updated onboarding instructions.
- Moved diagnostic logging configuration into Moodle administration settings and added a settings shortcut to the dashboard.
- Added translations for Arabic, German, Spanish, French, Italian, Dutch, Russian and Simplified Chinese, including regional language overrides.
- Removed the callback URL display and unverified-client notice from the consent template.

**Full comparison:** [1.4.2...1.4.4](https://github.com/EduardoKrausME/moodle-local_mcp/compare/1.4.2...1.4.4)

## 1.4.2 - 2026-11-02

- Revised English and Brazilian Portuguese administration labels and README documentation to introduce ChatGPT and Claude before MCP terminology.
- Added PHP documentation blocks and normalized source headers across activity, grading, cohort and other tools.
- Updated bundled tool dependency and release metadata.

**Full comparison:** [1.4.1...1.4.2](https://github.com/EduardoKrausME/moodle-local_mcp/compare/1.4.1...1.4.2)

## 1.4.1 - 2026-10-31

- Added persistent, sequential Moodle workflows with step validation, cancellation, status tracking, optional MCP UI resources and explicit confirmation for each write step.
- Added native Moodle badge management, including transparent PNG handling, badge criteria, issuance controls and course-completion integration.
- Added cohort management and enrolment tools with membership-aware safeguards for destructive operations.
- Exposed Moodle password-policy requirements and validated account passwords while preserving opaque credentials and redacting secrets from diagnostics.
- Fixed OAuth resource-column migrations and rejected credentials lacking the required resource; improved bounded pagination for OAuth clients and course categories.
- Improved typed MCP errors, H5P archive validation during previews and input checks before write confirmations.
- Corrected course overview image URLs and normalized legacy file URLs in MCP-submitted HTML.
- Fixed assignment creation, activity deletion, HTML editor persistence and Forum defaults for Moodle 4.5 and 5.3; added optional Kopere Proctoring status discovery.

**Full comparison:** [1.4.0...1.4.1](https://github.com/EduardoKrausME/moodle-local_mcp/compare/1.4.0...1.4.1)

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
