# Moodle MCP

**Documentation:** [Moodle MCP website](https://eduardokrausme.github.io/moodle-local_mcp/)

Moodle MCP turns a Moodle installation into an extensible Model Context Protocol server with a deliberately separated
READ and WRITE architecture.

This is a **local plugin**: `local_mcp`. It does not use Moodle's traditional `wstoken` mechanism as its primary
authentication layer.


## Extend Moodle MCP with other Moodle plugins

Moodle MCP automatically discovers MCP providers from compatible plugins installed on the same site. These three integrations are ready to use with ChatGPT: install the corresponding plugin, refresh the MCP connection, and use the READ/WRITE tools permitted to your Moodle account. Each image opens a practical guide in Brazilian Portuguese, with an English version available on the documentation website.

| Kopere BI | Helpdesk | Kopere Proctoring |
| --- | --- | --- |
| [<img src="https://raw.githubusercontent.com/EduardoKrausME/moodle-local_mcp/docs/print/02-local_kopere_bi.png" alt="Kopere BI integrated with ChatGPT using Moodle MCP" width="280">](https://eduardokrausme.github.io/moodle-local_mcp/pt_br/recursos/kopere-bi.html) | [<img src="https://raw.githubusercontent.com/EduardoKrausME/moodle-local_mcp/docs/print/03-local_helpdesk.png" alt="Moodle Helpdesk integrated with ChatGPT using Moodle MCP" width="280">](https://eduardokrausme.github.io/moodle-local_mcp/pt_br/recursos/helpdesk.html) | [<img src="https://raw.githubusercontent.com/EduardoKrausME/moodle-local_mcp/docs/print/04-local_kopere_proctoring.png" alt="Kopere Proctoring integrated with ChatGPT using Moodle MCP" width="280">](https://eduardokrausme.github.io/moodle-local_mcp/pt_br/recursos/kopere-proctoring.html) |
| [How to use / Como usar](https://eduardokrausme.github.io/moodle-local_mcp/pt_br/recursos/kopere-bi.html) · [Moodle Marketplace](https://marketplace.moodle.com/plugins/local_kopere_bi) | [How to use / Como usar](https://eduardokrausme.github.io/moodle-local_mcp/pt_br/recursos/helpdesk.html) · [Moodle Marketplace](https://marketplace.moodle.com/plugins/local_helpdesk) | [How to use / Como usar](https://eduardokrausme.github.io/moodle-local_mcp/pt_br/recursos/kopere-proctoring.html) · [Moodle Marketplace](https://marketplace.moodle.com/plugins/local_kopere_proctoring) |

**Kopere BI** publishes `kopere_bi_list_reports` plus one READ tool per accessible report, named `kopere_bi_report_<refkey>`. Ask ChatGPT which reports are available, then analyze their structured data by course, user or cohort as permissions allow. [Documentation (English)](https://eduardokrausme.github.io/moodle-local_mcp/en/recursos/kopere-bi.html).

**Helpdesk** supports reading tickets, replies, knowledge-base articles and statistics, plus confirmable tools for creating, replying to and updating tickets and creating articles. For example, ask ChatGPT to identify urgent tickets and draft a response for approval. [Documentation (English)](https://eduardokrausme.github.io/moodle-local_mcp/en/recursos/helpdesk.html).

**Kopere Proctoring** supports reviewing quiz settings, monitored attempts and recorded events, and a confirmable WRITE action to enable or disable monitoring on a quiz. Monitoring events must be reviewed by a person and are not proof of cheating. [Documentation (English)](https://eduardokrausme.github.io/moodle-local_mcp/en/recursos/kopere-proctoring.html).

Every integration remains subject to Moodle capabilities and MCP scopes. READ and WRITE are separate, and WRITE calls require an explicit preview/confirmation flow. After installing or updating a plugin, refresh the ChatGPT connector to discover its tools.

## Site diagnostics and safe Moodle configuration

The `get_site_status` READ tool uses Moodle's own `\core\check\manager::get_checks()` Check API,
returning the same checks shown in *Site administration > Reports > Performance overview*.
The optional `type` input accepts `performance` (default), `status` or `security`.
Each result contains its reference, name, status (`ok`, `warning`, `error`, `critical`,
`unknown` etc.), summary, overall status and counts. Set `include_details=true` for potentially
expensive details, or select one previously returned `checkref`.

The `get_site_config` READ tool reports approved core configuration values as both their database
value (`stored`) and current runtime value (`effective`), helping identify settings
overridden by `config.php`. Omit `names` to inspect all approved non-secret values.

The `set_site_config` WRITE tool accepts an atomic batch of approved `mdl_config` changes:

```json
{
  "settings": [
    {"name": "enabledashboard", "value": "0"},
    {"name": "enablemycourses", "value": "1"},
    {"name": "defaulthomepage", "value": "3"}
  ]
}
```

Other supported keys include `debug` (use `32767` for DEVELOPER), `debugdisplay`,
`debugpageinfo`, `themedesignermode`, `cachejs`, `enablemyhome` and
`allowguestmymoodle`. The input `value` is always a string; booleans use `0` / `1`.
Only existing, explicitly reviewed core keys in `classes/config/site_config.php` can be changed;
secrets, authentication internals, arbitrary keys and plugin settings are intentionally excluded.

Both tools require an actual Moodle site administrator and `moodle/site:config`.
Writes are verified and previewed before changes, require a second call with a one-time
confirmation token, and use Moodle's `set_config()` rather than SQL.
An audit entry records the names of modified settings without logging values.
When the Moodle server is updated, refresh the custom MCP connection to discover the new tools.

## MCP JSON request/response diagnostics

PHP diagnostic logging is disabled by default, including full JSON-RPC request
and response bodies. To follow MCP calls and check whether they completed or
failed, explicitly enable the server-side debugger in Moodle's `config.php`:

```php
$CFG->local_mcp_debugger = true;
```

Add this after the Moodle `$CFG` object is initialized and before
`require_once(__DIR__ . '/lib/setup.php');`. This enables **metadata-only**
events (request ID, tool name, outcome, duration and safe error information)
for `server.php`, `read.php` and `write.php`; it does not require
Moodle developer debugging or expose tool arguments by default.

For unusually difficult protocol problems, additionally enable the
**Log MCP JSON messages to PHP error_log** setting in the plugin's administration
settings. This separate checkbox defaults to off and is effective only while
`$CFG->local_mcp_debugger` is true. It traces incoming and outgoing JSON-RPC
bodies, with numbered fragments for large messages and best-effort redaction
of recognized secrets and binaries. **Other personal Moodle data can still
appear in these logs**, so restrict PHP log access and retention, use body
tracing only for a limited troubleshooting session, then disable the checkbox
and remove (or set to `false`) the `config.php` flag. OAuth token endpoints
are never body-traced.

## Architecture

```
MCP Client
      |
      +------------ OAuth ----------------+
      |                                   |
      |                            Moodle Login
      |                                   |
      |                    Authenticated, non-guest user
      |                                   |
      |                             Consent page
      |                                   |
      |                           Authorization Code
      |                                   |
      +---------- Access Token <----------+
                     |
             +-------+--------+
             |                |
        MCP READ          MCP WRITE
             |                |
      Read Registry      Write Registry
             |                |
      has_capability     has_capability
             |                |
          Query          Validate/Preview
                              |
                         Confirmation
                              |
                            Write
```

## Course inspection and administrative permissions

list_categories supports includehidden and includecoursecount (both boolean). The response
includes id, name, parent, visible and direct coursecount, with existing tree metadata.
The connected user must have the relevant Moodle category-management capability.

search_courses reads course records without letting the regular course browsing
function hide invisible courses first. It accepts shortname for an exact lookup,
query for substring lookup, and includehidden, limit and offset. It returns
id, fullname, shortname, idnumber, categoryid, original summary HTML,
summaryformat and visible for courses the user can administer.
The site course (SITEID, usually ID 1) is excluded from searches.

get_course supports the site course and reads the complete course record from
the database, avoiding partial SITE/COURSE globals. Both create_course and
update_course accept summaryformat=1 (HTML), and update_course accepts categoryid.
Course changes check Moodle capabilities on the course and destination category.

For unexpected tools/call exceptions, the server returns a generic error with
a diagnostic reference. Only when `$CFG->local_mcp_debugger` is enabled does it
also log the PHP exception class and sanitized source location under that
reference, allowing failed MCP calls to be correlated with the PHP log.
Metadata diagnostics never include bearer tokens or tool argument values.

After updating Moodle, refresh the custom MCP connector in ChatGPT so its
advertised input schemas and list of tools reflect the new plugin version.

## Create a batch of up to 30 courses

`bulk_create_courses` is a WRITE tool designed for importing complete Moodle courses
with one preview and one confirmation. It accepts the following structure:

```json
{
  "courses": [
    {
      "fullname": "Fundamentos de Cidades Inteligentes",
      "shortname": "FUN-001",
      "idnumber": "CITY-FUN-001",
      "categoryid": 2,
      "summary": "<p>Introdução às cidades inteligentes.</p>",
      "summaryformat": 1,
      "visible": false
    }
  ],
  "skip_existing": true,
  "excluded_categoryids": [1]
}
```

Every item requires a nonempty HTML `summary`, `fullname`, `shortname` and
`categoryid`; `summaryformat` is optional (HTML/1 is the only supported format).
`idnumber` is optional. An item may additionally include `image_base64` or
`image_url`, using the existing 5 MiB course image API. Very large image batches
may exceed the MCP client or PHP request-body limit; covers can also be uploaded
later with `set_course_image`.

The preview lists each requested course and whether it will be created or skipped,
without returning HTML bodies or Base64 image data. The operation rejects
duplicate identifiers *within* the batch. Existing courses matching `shortname`
or a nonempty `idnumber` are skipped by default and are never edited; optionally
set `skip_existing: false` to reject the request instead. If shortname and
idnumber refer to different existing courses, the request is rejected.

Use `list_categories` to identify the root category named `Teste` and pass its
ID in `excluded_categoryids`. Every category below an excluded root is also
blocked, so old test courses are not accidentally mixed with new ones.
The tool verifies `moodle/course:create` on every destination category and
revalidates the batch immediately before writing. Creation uses a delegated
database transaction; Moodle integrations may have external side effects that
transactions cannot undo.

**If ChatGPT still shows `create_course` with only four fields or does not show
`list_categories`, it is using an old connector schema. Deploy the updated
plugin in Moodle and refresh/reinstall the custom ChatGPT MCP connection to
fetch the new tool definitions. Changing repository files alone does not
update an already-installed Moodle server or a cached connector.**

## Creating complete courses and organizing categories

The combined MCP server exposes the following operations:

- `list_categories` (READ) returns category IDs, parent IDs, hierarchy and visibility.
- `create_category` (WRITE) creates a root category with `parentid: 0` or a nested category.
- `update_category` (WRITE) changes the name, parent or visibility. Moving a category preserves
  its child categories and the courses belonging to them; self/descendant moves are rejected.
- `create_course` (WRITE) accepts `fullname`, `shortname`, `categoryid`, `visible`,
  optional `idnumber`, and `summary` in HTML (stored as `FORMAT_HTML`).
  A course cover image can be included using `image_base64` or `image_url`. When an image
  is supplied, it is validated and stored in Moodle's course overviewfiles area.
- `update_course` (WRITE) accepts `summary` in HTML, `idnumber` and the usual course
  fields, allowing corrections without recreating the course.

All category and course WRITE calls require the connected token to have WRITE permission.
Moodle capabilities are checked in addition to MCP scopes; a preview/confirmation step
still applies. A read-only connection will not see these operations.

**Local images:** the remote Moodle server cannot open an image path from the ChatGPT
environment or from the user's computer. Read the file's bytes, encode the PNG/JPEG/WebP
as Base64, and send them as `image_base64` (or as a `data:image/png;base64,...` URI).
This does **not** require publishing the image URL. The limit is 5 MiB decoded per image.
Sending only a local filename or sandbox path does not upload an image. Base64 content is
not included in tool previews or application audit metadata.

## MCP 2026-07-28 discovery and response envelopes

New ChatGPT connectors may begin with `server/discover` (request protocol
`2026-07-28`) instead of the legacy `initialize` handshake. All three MCP
endpoints (`server.php`, `read.php`, `write.php`) accept `server/discover`
and return `resultType: complete`, supported protocol versions, accurate tools
capabilities and server identity using the installed plugin's release.

The effective `$CFG->wwwroot` is now included repeatedly so connected AI clients can
distinguish separate Moodle installations. It appears as `websiteUrl` in
`serverInfo`, in the instructions for both `server/discover` and `initialize`,
and as `moodle_wwwroot` in discovery metadata and the public discovery JSON.
Every `tools/list` response also includes it in `_meta` and in each tool
description. Each `tools/call` result, including READ results, WRITE previews,
successful writes and tool errors, includes `moodle_wwwroot` in both textual
and structured data, as well as response `_meta`. The server takes this value
from the current request's Moodle configuration and never trusts a client-supplied
URL. This does not alter operation permissions or confirmation requirements.

The discovery response uses `cacheScope: private` and `ttlMs: 60000` (one minute).
This prevents `server/discover` and `tools/list` from being treated as stale
immediately while isolating cached tool metadata to the same authorization
context. Tokens, scopes and Moodle permissions are still checked on each
request. Modern `tools/call` responses include `resultType: complete`. Existing clients using the
`2025-06-18` `initialize` handshake continue to receive the legacy layout.

By default the plugin writes neither MCP request parameters nor complete
JSON-RPC responses to PHP logs. With `$CFG->local_mcp_debugger` enabled,
sanitized metadata and exception references can be inspected; the separate
`local_mcp/logmessages` option is required to trace full message bodies
and should only be enabled briefly in a protected environment.

For security, pass OAuth access tokens only in the `Authorization: Bearer` header.
MCP query-string tokens (`?token=` and `?toke=`) are not supported.

## MCP endpoints

- Combined READ + WRITE: `/local/mcp/server.php` (recommended for ChatGPT)
- READ: `/local/mcp/read.php`
- WRITE: `/local/mcp/write.php`
- Plugin discovery: `/local/mcp/discovery.php`

The combined endpoint merges tool listings but retains separate READ/WRITE registries, scopes, rate limits and
permission checks.

The READ endpoint publishes only `local_mcp\read\registry`. The WRITE endpoint publishes
only `local_mcp\write\registry`. There is no combined registry followed by a risk filter.

## OAuth 2.1

The authorization server uses Authorization Code + PKCE S256.

- Authorization: `/local/mcp/oauth/authorize.php`
- Token: `/local/mcp/oauth/token.php`
- Dynamic registration: `/local/mcp/oauth/register.php`
- Authorization Server Metadata: `/local/mcp/.well-known/oauth-authorization-server.php`
- Protected Resource Metadata: `/local/mcp/.well-known/oauth-protected-resource.php?side=server` (
  or `side=read`/`side=write`)

OAuth clients must send the RFC 8707 `resource` parameter in both authorization-code authorization and token requests.
The value must exactly match the MCP endpoint URL (`server.php`, `read.php`, or `write.php`). OAuth tokens are
audience-bound to the selected endpoint, including when refresh tokens rotate. Tokens issued before audience binding was
introduced must be reauthorized.

OAuth access tokens are short-lived opaque secrets. Refresh tokens rotate. Authorization codes are short-lived and
single-use. Database records store SHA-256 hashes, never the complete secret.

The consent page accepts any active, authenticated Moodle user except guests. Connection authorization
does not grant Moodle capabilities: each tool call checks its required capability in the resolved
course, activity, category or system context, in addition to OAuth scopes. Site configuration
and administration tools still require administrator permissions.

## Scopes and Moodle capabilities

Initial scopes are independent:

- `mcp:read`
- `mcp:write`

`mcp:write` does not imply `mcp:read`.

A scope is only the outer protocol permission. Every tool still resolves the Moodle user and Moodle context and checks
the required Moodle capability before execution.

The intended decision chain is:

```
valid token
-> scope
-> side-specific registry
-> Moodle user
-> Moodle context
-> has_capability()
-> operation
```

## WRITE confirmation

WRITE tools declare additional security behavior:

- `supports_dry_run()`
- `requires_confirmation()`
- `is_destructive()`
- `preview()`

Confirmation is not a boolean such as `confirmed=true`. The server issues a short-lived, single-use confirmation token
bound to the connected user, client/connection, access token, tool, resolved context and a canonical SHA-256 hash of the
exact arguments.

Changing a course id, user id or any other argument after preview invalidates the confirmation.

Destructive tools always require this flow.

## Included READ tools

`search_courses`, `get_course`, `get_course_image`, `get_course_contents`, `get_course_sections`,
`get_course_activities`, `get_course_participants`, `search_users`, `get_user`,
`get_user_courses`, `get_progress`, `get_grades`, `get_assignments`,
`get_assignment`, `get_submissions`, `get_quizzes`, `get_quiz`,
`get_attempts`, and `get_calendar_events`.

## Included WRITE tools

`create_course`, `update_course`, `set_course_image`, `create_section`, `update_section`,
`enrol_user`, `unenrol_user`, `create_user`, `update_user`, `suspend_user`,
`send_message`, and `grade_submission`.

Business operations use Moodle APIs where available instead of directly updating Moodle business tables.

## Extension API

### Automatic MCP providers in any Moodle plugin

No change to `local_mcp` is required when adding tools to an existing
Moodle activity, local plugin, block or theme. For example, in
`theme/learnlingo/classes/mcp/provider.php`:

```php
<?php
namespace theme_learnlingo\mcp;

use local_mcp\extension\read_provider_interface;
use local_mcp\extension\write_provider_interface;

final class provider implements read_provider_interface, write_provider_interface {
    public function get_read_tools(): array {
        return [new get_homepage()];
    }

    public function get_write_tools(): array {
        return [new update_homepage()];
    }
}
```

Place the tool classes alongside it, e.g. `classes/mcp/get_homepage.php` and
`classes/mcp/update_homepage.php`. READ tools implement
`local_mcp\read\tool_interface`; WRITE tools implement
`local_mcp\write\tool_interface`. WRITE implementations may extend the
reusable `local_mcp\write\tool\base_tool` for default confirmation and
preview behavior. Implement whichever provider interface is needed:
READ-only and WRITE-only providers are valid; both are optional.

On `tools/list`, `local_mcp` discovers each installed/enabled plugin's
`classes/mcp/provider.php` via Moodle core component discovery, autoloads
`\<plugin_component>\mcp\provider` and registers the tools separately on
the READ and WRITE sides. Disabled plugins are ignored. Tool names must be
globally unique; namespace them, e.g. `learnlingo_update_homepage`, to
avoid collisions. Existing `lib.php` callback providers and `mcptool`
subplugins continue to work for backwards compatibility.

The protocol continues to enforce READ/WRITE scopes, the tool's
`get_required_capability()` in the `resolve_context()` Moodle context,
and the one-time confirmation flow for WRITE tools. The registered
provider does not bypass Moodle permissions or automatically expose
all plugin settings.

A concrete LearnLingo implementation is available in the
`moodle-theme_learnlingo` repository: `learnlingo_get_homepage` returns
mode, HTML, layout, SCSS and a revision hash; `learnlingo_update_homepage`
previews and updates those settings using the theme's existing validation,
requires `moodle/site:config`, and clears theme caches.

After installing/updating an external provider, refresh the ChatGPT MCP
plugin's tool definitions so the new commands become visible.

External Moodle plugins can explicitly provide one side only.

READ provider:

```php
function local_example_mcp_read_provider() {
    return new \local_example\mcp\read_provider();
}
```

The provider implements `local_mcp\extension\read_provider_interface` and returns only `local_mcp\read\tool_interface`
instances.

WRITE providers use `local_mcp\extension\write_provider_interface`. A provider cannot accidentally register a WRITE tool
on the READ side.

## Connections and revocation

An OAuth authorization creates a Connection tying together the Moodle user, OAuth client and granted scopes.

Revoking a Connection immediately invalidates:

- access tokens;
- refresh tokens;
- unused authorization codes for that user/client;
- unused confirmation tokens.

## Optional PHP error log diagnostics

When `$CFG->local_mcp_debugger = true;` is set in Moodle's `config.php`,
the MCP server records significant lifecycle events and errors through PHP
`error_log()`; when it is absent or false, none of these diagnostic entries
are emitted. Each line starts with `[local_mcp]` followed by a single-line
JSON object. The `request` field is a random 12-character correlation
identifier shared by the request's log entries.

Logged milestones include incoming requests (without their URL/query string),
authenticated Moodle user ID and MCP scopes, protocol methods, discovery,
tool counts, requested tool names and input **key names only**, resolved
context/capability, dry runs, confirmation issuance and acceptance, execution
success, and elapsed time. Authentication failures, permission denials, rate
limit errors, unexpected JSON-RPC methods and exceptions are recorded too.
Exceptions include their class, sanitized message, source file's basename,
line and protocol HTTP status.

**Metadata diagnostics avoid secrets:** without the separate message-body
tracing option, the logger does not serialize raw MCP requests, tool argument
values, authorization headers, URL query strings, confirmation tokens, HTML
content or uploaded Base64 images. Exception messages are length-limited and
sanitized. To identify outcomes, look for `tool_response_ready` with
`status: completed` (or `confirmation_pending` for previews), and
`tool_call_failed` / `exception` for errors, correlating their `request`
field. Explicit JSON-RPC body tracing is more sensitive and can include
personal data despite redaction. HTTP server access logs are managed separately
from PHP; ensure access logs do not reveal OAuth credentials.

Example:

```text
[local_mcp] {"request":"a1b2c3d4e5f6","level":"INFO","event":"protocol_method","side":"server","method":"tools/list","userid":2}
[local_mcp] {"request":"a1b2c3d4e5f6","level":"INFO","event":"tools_listed","side":"server","userid":2,"count":26}
```

To follow events on a typical Apache/PHP installation:

```bash
tail -f /var/log/httpd/php-error.log | grep --line-buffered '\[local_mcp\]'
```

## Audit

The audit schema stores operation and security metadata, not ChatGPT conversations or complete model prompts/responses.

Sensitive secrets are explicitly excluded from audit metadata.

## Security notes

- OAuth remote use should be HTTPS in production.
- Redirect URIs use exact matching.
- OAuth `state` is preserved but never replaces Moodle `sesskey`.
- Consent authorization is POST + `sesskey`.
- External client text is escaped before presentation.
- Access, refresh, authorization and confirmation secrets are hashed at rest.
- 401 responses advertise Protected Resource Metadata.
- READ and WRITE rate limits are independent.

## ChatGPT and other MCP clients

The plugin contains no ChatGPT-specific authentication code. ChatGPT is expected to behave like any other
standards-based MCP/OAuth client.

The integration flow is:

```
Moodle URL
-> Moodle MCP discovery
-> OAuth discovery
-> Moodle login
-> site administrator capability check
-> consent
-> authorization code
-> access + refresh token
-> `/local/mcp/server.php` (or the separate READ/WRITE endpoints)
```

### Connect ChatGPT through OAuth

Open `/local/mcp/admin/connections.php` for the illustrated setup guide.
Upload an optional PNG icon in ChatGPT, enter the connector name and description,
select **Server URL**, paste `https://YOUR-MOODLE/local/mcp/server.php`,
and select **OAuth**. Keep dynamic registration enabled in Moodle settings.
Accept ChatGPT's security notice and create the plugin, then complete Moodle
administrator login and READ/WRITE consent. Do not place tokens in URL parameters.

## Development

Run Moodle PHPUnit tests for `local_mcp` and the repository CI. GitHub Actions validates PHP syntax and the Moodle
plugin package.

## Connect ChatGPT as a custom MCP plugin

1. Enable **Dynamic client registration** in the Moodle MCP administration settings.
2. Make the Moodle site reachable over HTTPS and check the OAuth well-known configuration below.
3. In ChatGPT on the web, use **Plugins → Add custom MCP server** and set the server URL to
   `https://YOUR-MOODLE/local/mcp/server.php`, with OAuth and DCR registration.
4. Sign in with an active, non-guest Moodle account and approve the OAuth consent.
5. Use the connection with `mcp:read` and optionally `mcp:write`. The combined server
   lists only the tools allowed by the access token.

ChatGPT can also connect to `read.php` and `write.php` independently.
OAuth connections are available to authenticated, non-guest Moodle users. Tool execution
remains restricted by Moodle capabilities, checked against the context of each operation.
A connection does not confer administrative permissions.

### Configure OAuth authorization-server discovery

OAuth authorization servers with a path-based issuer need an RFC 8414 well-known URL.
The issuer is `https://YOUR-MOODLE/local/mcp`, so the ChatGPT discovery URL for a Moodle
installation at the domain root is:

```text
https://YOUR-MOODLE/.well-known/oauth-authorization-server/local/mcp
```

The Moodle plugin cannot register a URL outside its own directory. Add **one**
web-server rewrite before using OAuth clients. Examples for a root-installed Moodle:

Apache (in the Moodle site's existing root rewrite configuration):

```apache
RewriteRule ^\.well-known/oauth-authorization-server/local/mcp$ local/mcp/.well-known/oauth-authorization-server.php [L]
```

Nginx (inside the existing Moodle virtual host):

```nginx
location = /.well-known/oauth-authorization-server/local/mcp {
    rewrite ^ /local/mcp/.well-known/oauth-authorization-server.php last;
}
```

For a Moodle installed in a subdirectory, include the Moodle path in the left and right
sides of the rewrite. Do not redirect to another issuer or expose this file with a different
issuer URL. The JSON `issuer` value must remain identical to the discovery issuer.

Unauthenticated `POST` requests to the MCP endpoint return HTTP 401 with a
`WWW-Authenticate` header that points to the appropriate protected-resource metadata.
The `resource` value in those metadata is the exact endpoint URL.

### WRITE confirmation with MCP clients

The server publishes `confirmation_token` as an optional property for operations that
need a second call. The first call returns a successful MCP tool result
(`isError: false`) with `confirmation_required: true`, `status: confirmation_pending`,
a preview and a single-use token. **Nothing is written at this stage.** The client
must show the preview and obtain explicit user approval. Only after approval
may it repeat the **same** tool with identical original arguments plus the returned
`confirmation_token`. The token is checked against the user, client,
resource-bound access token, tool, context and argument hash. Receiving a preview
must never be treated as user approval.

The PHP error log records `confirmation_issued` and `tool_response_ready` with
`status: confirmation_pending`, then `confirmation_accepted` and
`tool_write_completed` after a valid second call. Actual tool failures log
`tool_call_failed` with safe error codes and durations. Raw arguments,
access credentials and confirmation tokens are never logged.

## Activity subplugins

The MCP activity integrations are **real Moodle subplugins**, installed under
`local/mcp/tool/<name>`, with component names `mcptool_<name>`. The parent plugin
defines a single `mcptool` plugintype in `db/subplugins.json`, using both
the Moodle 5.0+ `subplugintypes` and pre-5.0 `plugintypes` formats.

The parent `local_mcp\\extension\\manager` discovers providers using
`core_component::get_plugin_list('mcptool')`. Each provider class implements
`read_provider_interface` and `write_provider_interface`; the READ and WRITE
registries are still separate. No activity names, `switch` statements, or
activity-specific logic are present in the server controller or registries.

### Included activity integrations

- `mcptool_forum`: `forum_create_activity`, `forum_list_discussions`, `forum_get_posts`,
  `forum_create_discussion`, `forum_reply_to_post`.
- `mcptool_page`: `page_create_activity`, `page_get_content`, `page_update_content`.
- `mcptool_book`: `book_create_activity`, `book_list_chapters`, `book_get_chapter`,
  `book_create_chapter`, `book_update_chapter`.

The create-activity operations use Moodle's standard `prepare_new_moduleinfo_data()` / `add_moduleinfo()` APIs and check
both course-management and activity-creation capabilities. The remaining tools work with **existing** Moodle activities.
The forum integration
checks discussion, group and per-post visibility and delegates new postings to
the standard forum APIs. The page integration delegates updates to
`page_update_instance`; the book integration uses the book chapter schema,
revision tracking and core chapter lifecycle events. For safety, chapter
creation only appends, and none of the WRITE tools deletes existing content.
The page and book WRITE tools use revision preconditions where applicable.

The MCP server authenticates the bearer token without Moodle cookies, then
establishes that identity as the current Moodle `$USER` before calling tools,
so Moodle's built-in APIs can apply their normal access and audit behaviour.

All activity-specific logic lives under the corresponding
`tool/<name>/classes/` directory. New integrations only need a subplugin
`version.php`, a provider class, and their own READ/WRITE tool implementations;
no edits to the parent server controller are required.

All WRITE calls use the normal MCP two-step preview and confirmation mechanism,
and clients must obtain user approval for the second call. The MCP server
only sends schema, descriptions and results to ChatGPT: no separate OpenAPI
definition, prompt injection into the conversation, or ChatGPT-specific endpoint
is necessary. The ChatGPT MCP connector learns these tools via `tools/list`.

## Course cover images

`get_course_image(courseid, include_image=true)` returns the current course cover image and metadata from the
native `course/overviewfiles` area. When its size is up to 2 MiB and it is PNG, JPEG or WebP, it also returns MCP image
content for visual inspection. Access requires `mcp:read` and `moodle/course:view`.

`set_course_image(courseid, image_base64|image_url, expected_contenthash?)` replaces existing cover images through
Moodle File API, preserving non-image files. It accepts Base64 or an HTTPS image URL, allows up to 5 MiB and 40
megapixels, requires `mcp:write`, `moodle/course:update`, and `moodle/course:changesummary`, and uses the normal
two-call confirmation handshake. The optional expected hash prevents overwriting another update. The server rejects
local hosts and direct IPs; Moodle's configured cURL security rules also apply.

To upload a generated image, the client must provide its bytes or a genuinely accessible HTTPS download URL.
ChatGPT-private image URLs or conversation references cannot be fetched by Moodle.

## Concurrent requests and rate limiting

Default per-minute limits: **1200 READ**, **600 WRITE**, **120 OAuth**.
READ/WRITE limits are per access token, so independent connections have
independent quotas; OAuth requests share a per-IP quota. Moodle's lock
factory protects the cache counter increment for concurrent PHP workers.
If the locking backend cannot be reached within 10 seconds, the call returns
`rate_limit_busy` (503), rather than bypassing throttling.

The Moodle upgrade raises previous 120/30/30 settings when they match the old
defaults, preserving customized limits. After deployment, run the Moodle
upgrade and purge its caches before refreshing the ChatGPT connection.

The MCP initialize serverInfo version now comes from `version.php`; the
ChatGPT connector may display its own, independent version number.

## Cache purge WRITE tools

Two administrator-only tools are available with `mcp:write` scope and `moodle/site:config` capability:

- `purge_css_cache` uses `purge_caches(['theme' => true])` to invalidate generated CSS and theme assets without clearing
  other Moodle caches.
- `purge_all_caches` invokes Moodle core `purge_all_caches()` for a complete purge. Cached data will be rebuilt, which
  can temporarily slow the site.

Both support `dry_run` and require the existing two-call confirmation protocol before performing any cache changes.
Their preview responses never purge caches. The MCP audit log records successful writes and their duration.

## Assignment feedback tools (mod_assign)

The READ tools `get_assignment_feedback_queue` and
`get_assignment_feedback_context` expose eligible submissions, online text,
small UTF-8 text attachments, rubric criteria and existing teacher feedback
to a connected MCP assistant. Binary files are listed without extracting their
contents, so a teacher should review those attachments independently.

The READ tool `get_assignment_submission_image` can also return supported image
attachments as native MCP image content (up to 3 MB).

The WRITE tool `save_assignment_feedback` stores reviewed plain-text
comments through the native assignment feedback plugin, without changing
numeric grades. It requires explicit approval through the existing
confirmation-token workflow and rejects stale submissions or feedback,
anonymous marking and team submissions. Moodle grading capabilities and
separate-group restrictions are enforced.

See [Assignment feedback tools](docs/assignment-feedback-tools.md) for
arguments, safeguards and an example workflow.

## Question bank support

Question banks use Moodle's context model. In Moodle 5.0+, a course may have multiple
banks (shared `mod_qbank` modules and quiz banks); Moodle 4.5 also uses course
contexts. Always resolve the right `contextid` before adding categories:

1. READ `list_question_banks({"courseid": 42})` returns bank contexts and module IDs.
2. READ `list_question_categories({"contextid": 123})` lists category IDs.
3. READ `search_questions({"categoryid": 7, "query": "geometry"})` searches
   latest versions. READ `get_question({"questionid": 99})` returns the answer data.
4. WRITE `create_question_category` adds a category; WRITE `create_question`
   creates a versioned question; WRITE `update_question` creates a new version.

For example, create a single-choice question (each fraction ranges from -1 to 1):

```json
{
  "categoryid": 7,
  "qtype": "multichoice",
  "name": "City planning",
  "questiontext": "<p>Which solution reduces congestion?</p>",
  "single": true,
  "answers": [
    {"text": "Reliable public transport", "fraction": 1},
    {"text": "More isolated parking", "fraction": 0}
  ]
}
```

Supported writes: `multichoice`, `truefalse`, `shortanswer` and `essay`.
READ supports all installed question types; specialised options for other
question types must be managed through their Moodle editors. For `truefalse`,
pass `correctanswer` as a boolean, and for `shortanswer`, provide at least
one answer with `fraction: 1`. When updating, supply the complete replacement
question, its version-specific `questionid` and `expectedversion`. The core
question APIs create a new version without editing the old version.

READ and WRITE scopes are independent; Moodle's question capabilities are enforced
for the selected context, and own-question access does not grant access to others'
answers. All WRITE operations have preview and confirmation and do not log full
question text or answer payloads.

### Import a complete GIFT question bank in one operation

WRITE `import_gift_question_bank` receives UTF-8 GIFT text and imports up to
500 questions into **an existing category** or **a newly created category**.
Moodle's native GIFT parser and importer handle the content. Each batch requires
the usual MCP preview and one-time confirmation token; preview never writes.

Use `list_question_banks` to discover `contextid`. Choose one of these two modes:

- **Existing category:** pass `contextid`, `categoryid` and `gift`. The category must
  belong to that context. Do not pass `name`, `parentid`, `info` or `idnumber`.
  This mode supports already-existing course-context categories on Moodle 5.x
  as well as module-context banks, and requires `moodle/question:add`.
- **New category:** pass `contextid`, `name` and `gift` (optionally `parentid`,
  `info` and `idnumber`). This mode requires both
  `moodle/question:managecategory` and `moodle/question:add`. On Moodle 5.x,
  newly created banks require a module (`qbank` or `quiz`) context;
  Moodle 4.5 supports a course context.

Existing-category example:

```json
{
  "contextid": 496,
  "categoryid": 44,
  "gift": "::Capital::What is the capital of France? {=Paris ~Rome ~Berlin}\n\n::Earth::The Earth is round. {TRUE}"
}
```

New-category example:

```json
{
  "contextid": 123,
  "parentid": 0,
  "name": "Geography – October 2026",
  "idnumber": "GEO-OCT-2026",
  "gift": "::Capital::What is the capital of France? {=Paris ~Rome ~Berlin}\n\n::Earth::The Earth is round. {TRUE}"
}
```

Preview includes import mode, target category ID where applicable,
question totals, types, sample names and a SHA-256 fingerprint of the GIFT content.
Imports revalidate permissions and destinations, reject duplicate question names
(including names already present in the target category, even in draft/hidden
versions), and commit atomically or roll back the batch. Import into an existing
category never edits or replaces existing questions. Duplicate new category names
are also rejected. Embedded `$CATEGORY:` directives are rejected to keep
questions inside the expressly approved destination. Maximum payload size: 2 MiB.
The import response contains category ID, question IDs, types and content SHA-256.

## Additional course activity integrations (mcptool subplugins)

The `tool/` directory also contains independent WRITE providers for File, H5P, URL,
Text and media area (Label), Quiz and Glossary. Every activity is created using
Moodle's `prepare_new_moduleinfo_data()` / `add_moduleinfo()` lifecycle,
with both `moodle/course:manageactivities` and the module's
`mod/<module>:addinstance` capability checked for the connected user.
All mutations use the existing MCP preview/confirmation flow.

| MCP WRITE tool               | Moodle module       | Required input                                                        | Additional operations                                   |
|------------------------------|---------------------|-----------------------------------------------------------------------|---------------------------------------------------------|
| `resource_create_activity` | `mod_resource`    | `courseid`, `sectionnum`, `name`, `filename`, `file_base64` | Uploads one file                                        |
| `h5p_create_activity`      | `mod_h5pactivity` | `courseid`, `sectionnum`, `name`, `filename`, `file_base64` | Uploads one .h5p package                                |
| `url_create_activity`      | `mod_url`         | `courseid`, `sectionnum`, `name`, `externalurl`               | HTTP/HTTPS links                                        |
| `label_create_activity`    | `mod_label`       | `courseid`, `sectionnum`, `content`                             | Inline HTML                                             |
| `quiz_create_activity`     | `mod_quiz`        | `courseid`, `sectionnum`, `name`                                | `quiz_add_question` (existing question IDs)           |
| `glossary_create_activity` | `mod_glossary`    | `courseid`, `sectionnum`, `name`                                | `glossary_create_entry` (concept and HTML definition) |

An example file creation payload:

```json
{
"courseid": 12,
"sectionnum": 1,
"name": "Course handout",
"filename": "handout.pdf",
"file_base64": "<base64 of the PDF file>"
}
```

Uploads use the Moodle File API with a temporary draft owned by the authenticated
user, then the activity module moves the draft to its proper storage area.
`file_base64` can contain raw Base64 or a data URI, with a 40 MiB decoded limit;
neither the data nor HTML bodies are included in the activity-create preview.
H5P packages must be valid ZIP-based `.h5p` files and are created with the
built-in Moodle `mod_h5pactivity` module (not the third-party `mod_hvp`).
The MCP endpoint does not fetch arbitrary remote URLs for file uploads.

After installing the new subplugins, run Moodle's upgrade process and refresh
the MCP connector so these WRITE tools appear in the published tool schema.

## OAuth for ChatGPT (1.3.17)

OAuth Authorization Code + PKCE S256 is the sole client authentication
mechanism. The MCP server requires an `Authorization: Bearer` header with a
resource-bound OAuth access token. Manual tokens and URL query tokens are not
accepted. Access and refresh tokens are stored as hashes and can be revoked.

To connect, use `/local/mcp/server.php`, sign in to Moodle, review READ
and/or WRITE scopes, and authorize the requested access. Existing connection
scopes continue to limit issued access tokens.

**Deployment:** OAuth discovery needs server-side rewriting for site-root
`/.well-known/` URLs. See [OAuth discovery setup](https://github.com/EduardoKrausME/moodle-local_mcp/blob/docs/oauth-webserver.md).

## Auditing ChatGPT actions in Moodle

Site administration > Plugins > Local plugins > Moodle MCP > Audit provides
a filtered, paginated timeline of MCP activity (by ChatGPT OAuth client,
tool, status, READ/WRITE side and Moodle user). Administrators can export
filtered audit rows as CSV (up to 5,000 records). Retention is configurable
through the existing Audit retention days setting (180 days by default).

The audit distinguishes successful calls, dry-run previews, requests awaiting
explicit confirmation, and failed calls. Entries include the OAuth client and
connection IDs, Moodle actor, tool name, operation status, context, duration
and a request ID matching the PHP diagnostic log. The metadata stores only
requested field names, numeric Moodle entity IDs and operation counts. For
example, `create_course` can identify the new course ID and
`set_site_config` can list affected setting names, never their values.

The audit covers only requests processed through this MCP server, not
ChatGPT's conversation transcript or actions performed elsewhere. It does
not include field-level before/after diffs, OAuth credentials, confirmation
tokens, prompts, user-entered HTML, images or raw tool payloads.


## Troubleshooting OAuth and MCP tool discovery

An OAuth consent screen can complete successfully while the following MCP `POST`
still fails with HTTP 401. A successful authorization-code exchange is not proof
that the MCP endpoint received the access token. Inspect the PHP error log and
correlate records by their `request` identifier:

- `request_received.auth_source=none` means PHP did not see an Authorization header.
- `auth_rejected.phase=bearer_extraction` and `reason=missing_authorization_header`
  mean the request did not carry a header accessible to PHP.
- `reason=malformed_authorization_header` means a header was present but did not
  contain `Bearer <access-token>`.
- `auth_rejected.phase=token_validation` identifies an invalid, revoked, expired,
  audience-mismatched or disconnected OAuth access token.
- `bearer_received`, followed by `authenticated`, means bearer validation succeeded;
  `initialize_completed` and `tools_listed` then trace MCP action discovery.

An `exception` log now contains up to eight safe `trace` frames (file basenames,
line numbers and function names). No access tokens, request payloads, exception
arguments or absolute filesystem paths are included in the trace. The response
still returns the generic OAuth error rather than internal diagnostics.

On Apache, enable `mod_rewrite` and allow the plugin's `.htaccess` to be applied,
so `Authorization` reaches PHP as `HTTP_AUTHORIZATION`. On Nginx with PHP-FPM,
pass the header explicitly within the PHP FastCGI configuration:

```nginx
fastcgi_param HTTP_AUTHORIZATION $http_authorization;
```

For reverse proxies, ensure that `Authorization` is passed unmodified to the
origin. The plugin never accepts OAuth access tokens in URL query parameters.

## Assignment management subplugin

The bundled `mcptool_assign` provides 13 additional READ/WRITE tools for managing assignments,
reviewing submission history, extracting authorized text attachments, granting extensions,
drafting rubric assessments and managing release workflows. Install/upgrade the
subplugin and refresh MCP discovery. See [assignment management documentation](docs/mcptool-assign.md).
