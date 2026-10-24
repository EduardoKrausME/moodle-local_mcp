# Sequential Moodle operations

The MCP server can plan **2–30** Moodle WRITE operations for a single course and
guide the client through one confirmation at a time. This behavior is delivered by
the Moodle plugin's discovery instructions and an optional MCP Apps **Next** button,
not by ChatGPT memory or custom instructions.

## Usage

1. Call `workflow_sequence` with `action=start`, `courseid` and ordered
   `items` containing only `title` and the existing WRITE `tool` name.
2. The response returns an opaque `workflow.id` and the first step. Generate the
   arguments **only for that step**. Call the original tool, including
   `workflow_id` and `workflow_step=1`.
3. Show the preview and obtain explicit user approval. Reinvoke the *same* tool
   with its identical arguments plus `confirmation_token`. The plugin rejects
   steps other than the current one and prevents parallel claims.
4. Verify the created content with the relevant READ tool when available. The
   successful WRITE result contains workflow progress and an MCP App with a
   **Next** button. Clicking it sends a follow-up; it does not authorize a WRITE.
5. For the next step, call `workflow_sequence` with `action=next`, `courseid`
   and `workflow_id`. Prepare a new preview, wait for approval, and repeat.
   Use `action=status` to resume or `action=cancel` to discard a plan.

Example:

```json
{
  "action": "start",
  "courseid": 33,
  "items": [
    {"title": "Discussion forum", "tool": "forum_create_activity"},
    {"title": "Assignment", "tool": "assign_create_activity"}
  ]
}
```

The specified tools must be installed and exposed by the selected Moodle site;
names above are illustrative. The plan stores **labels and tool names only**,
not HTML, arguments, students' data or confirmation tokens.

## Safety and interoperability

The same user, client and OAuth connection must access the workflow. The plugin
checks the tool name, step number, course context and Moodle capability on each
WRITE. An approved WRITE claims a step before execution; a second call cannot
claim it. After a failure, the stage deliberately stays `executing` so a
partially completed Moodle operation is not repeated automatically. Inspect
Moodle, then cancel and replan if recovery is required.

Workflows expire after seven days. Persistence uses Moodle's existing
`config_plugins` API so no DB migration or `version.php` change is needed.

MCP Apps-aware clients fetch `ui://local-mcp/workflow-next` via
`resources/read` and render the Next button after a tracked WRITE succeeds.
Clients without UI support still receive structured workflow data and must
offer a text-based Next action. The button depends on the MCP client supporting
the extension; the server cannot force a native ChatGPT button.

Untracked operations work as before. The plugin does not infer a batch from
arbitrary ChatGPT prose: the client must call `workflow_sequence(action=start)`
to activate a server-enforced plan.
