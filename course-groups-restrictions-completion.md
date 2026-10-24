# Course groups, access restrictions and activity completion MCP tools

This suite extends the core `local_mcp` READ and WRITE registries, so no new
subplugin install is needed. Moodle 4.5 LTS and Moodle 5.3 are supported.

## READ operations

- `get_course_groups(courseid, limit?, offset?)`: list group IDs, names and member counts.
- `get_group_members(courseid, groupid, limit?, offset?)`: paginated member IDs and display
  names, with `members_sha256` representing the complete group's current membership.
- `get_activity_restrictions(cmid)`: current Moodle availability JSON, group mode and
  `availability_sha256` (SHA-256 of stored JSON or the empty string).
- `get_activity_completion(cmid)`: completion mode, view/grade rules and number of
  existing student completion records.

## Confirmed WRITE operations

- `create_group(courseid, name)`
- `add_group_members(courseid, groupid, userids, expected_members_sha256)`
- `remove_group_members(courseid, groupid, userids, expected_members_sha256)`
- `set_activity_groupmode(cmid, groupmode, expected_groupmode)` — 0 none, 1 separate, 2 visible.
- `set_activity_restrictions(cmid, mode, expected_availability_sha256, conditions?)`
- `set_activity_completion(cmid, completion, expected_completion, requireview?, requiregrade?)`

All WRITE calls use existing MCP single-use confirmation. The server rechecks the
same permissions and prerequisites during actual execution, not only preview.
Group mutation requires `moodle/course:managegroups`, active student enrolment
for additions, and a fresh membership fingerprint. External group membership
components may forbid deletion. Group-mode, restrictions, and completion require
`moodle/course:manageactivities` in the module context.

## Availability conditions

`set_activity_restrictions` replaces the entire prior restriction tree with a
single logical AND containing between 1 and 20 conditions. `mode=clear`
removes all restrictions and ignores `conditions`. It does not merge or partially
modify existing rules. Users must review the full before/after preview.

Supported condition shapes:

```json
{
  "cmid": 17,
  "mode": "replace",
  "expected_availability_sha256": "<sha256 from get_activity_restrictions>",
  "conditions": [
    {"type": "group", "groupid": 11},
    {"type": "date", "direction": "from", "timestamp": 1790000000},
    {"type": "completion", "cmid": 16, "state": "complete"},
    {"type": "grade", "gradeitemid": 54, "min": 60}
  ]
}
```

Date conditions support `from` (inclusive) or `until` (exclusive).
Completion supports `complete`, `incomplete`, `passed`, `failed`.
Grade min/max are Moodle's percentage bounds 0–100, with max exclusive.
Each referenced group/activity/grade item is checked against the same course.
Completion rules reject self-references and disabled course completion.
Only enabled Moodle availability plugins are accepted, and the resulting
restriction tree is validated by Moodle core.

## Completion

The completion tool supports off (0), manual (1), and automatic (2).
Automatic completion requires `requireview=true`, `requiregrade=true`, or both;
grade completion is allowed only when the activity has a grade item. It does
not expose module-specific completion rules. Existing completion records lock
this tool, preventing silent destruction/recalculation of student histories.
The course and site must already have completion enabled. Changes only update
core course-module flags, not the activity-specific form fields.

Refresh Moodle caches and MCP discovery after installation. No existing
completion data or course grades are deleted or changed by these operations.
