# MCP cohort tools

The `mcptool_cohort` subplugin integrates the native Moodle cohort API with `local_mcp`. Installed under `local/mcp/tool/cohort`, it is automatically discovered after Moodle upgrade and refreshing the MCP connection. No parent plugin version change is necessary.

**READ tools:** `cohort_search_cohorts`, `cohort_get_cohort`, `cohort_get_cohort_members`, `cohort_get_cohort_courses`.

**WRITE tools:** `cohort_create_cohort`, `cohort_update_cohort`, `cohort_delete_cohort`, `cohort_add_cohort_members`, `cohort_remove_cohort_members`, `cohort_enrol_cohort`.

Search accepts `contextid` (omit for system), `search`, `limit` and `offset`. It searches one system or category context at a time, under `moodle/cohort:view`. The system context is the default; searching all categories requires separate context-scoped requests. Cohort details return `revision` and `members_sha256` for optimistic concurrency, including safe deletion; member listing also returns `members_sha256` for membership changes. READ member names require `moodle/cohort:assign`. Cohort course links show only courses where the connected user can manage enrolment configuration.

Changes use native `cohort_add_cohort()`, `cohort_update_cohort()`, `cohort_delete_cohort()`, `cohort_add_member()`, `cohort_remove_member()`, and the `enrol_cohort` plugin's `add_instance()`. Existing component-managed cohorts are read-only for cohort edits and member changes. Delete refuses any linked enrolment instances, including disabled ones. All WRITEs require preview and explicit approval. Membership changes are limited to 50 users per call and validate a fingerprint of the *whole* cohort membership, not just a page. Deleting or removing memberships can affect course enrolments, even if no enrolment is deleted directly by this subplugin.

**Example sequence:** `cohort_create_cohort` with `name` and `contextid`; `cohort_get_cohort_members` with `cohortid` to obtain `members_sha256`; `cohort_add_cohort_members` with `userids` and the fingerprint; `cohort_enrol_cohort` with `courseid`, `cohortid` and an assignable `roleid`. Connecting a cohort to a course requires both `moodle/course:enrolconfig` and `enrol/cohort:config`, an assignable role, an available cohort in that course context and enabled cohort enrolment. Native synchronisation is triggered when the enrolment instance is created.

The subplugin does not implement cohort imports, external cohort provisioning or editing enrolment instances already linked to a course.
