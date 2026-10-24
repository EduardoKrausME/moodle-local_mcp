# MCP tools for assignments and safe module deletion

After deploying the updated plugin to the Moodle server and completing the plugin upgrade, refresh MCP tool discovery and verify that `assign_create_activity` and `delete_course_module` are listed.

## Create an assignment

`assign_create_activity` accepts `courseid`, `sectionnum`, `name`, optional HTML `intro`, `grade`, `file_enabled`, `onlinetext_enabled`, `maxfiles`, and optional Unix timestamps for assignment dates. By default, it enables file submission and grading without a due date. It rejects configurations without any submission method, inconsistent dates, and duplicate assignment names within the same course section. The tool requires `moodle/course:manageactivities`, `mod/assign:addinstance`, and explicit MCP confirmation.

## Permanently delete a course module

`delete_course_module` requires `courseid`, `cmid`, `expectedsectionnum`, `expectedmodname`, and `expectedname`. It verifies the exact target and `moodle/course:manageactivities` permissions during preview and again immediately before execution. The operation is flagged destructive, requires a separate one-time confirmation token, deletes synchronously through Moodle's core API, and confirms that the course-module record no longer exists. Always review the full target identity before authorizing deletion.

## Deployment

A GitHub commit does not install code on a Moodle site. Deploy plugin files, run the Moodle plugin upgrade, purge caches when needed, and refresh the MCP connection's tools list. Do not attempt a deletion until the server actually advertises the tool and the preview identifies the correct module.
