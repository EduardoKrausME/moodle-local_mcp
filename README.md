# Moodle MCP

Moodle MCP turns a Moodle installation into an extensible Model Context Protocol server with a deliberately separated
READ and WRITE architecture.

This is a **local plugin**: `local_mcp`. It does not use Moodle's traditional `wstoken` mechanism as its primary
authentication layer.

## Architecture

```
MCP Client
      |
      +------------ OAuth ----------------+
      |                                   |
      |                            Moodle Login
      |                                   |
      |                           Admin validation
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
a diagnostic reference and logs the PHP exception class and source location
with that reference. No bearer tokens or tool arguments are logged.

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

## MCP endpoints

- Combined READ + WRITE: `/local/mcp/server.php` (recommended for ChatGPT)
- READ: `/local/mcp/read.php`
- WRITE: `/local/mcp/write.php`
- Plugin discovery: `/local/mcp/discovery.php`

The combined endpoint merges tool listings but retains separate READ/WRITE registries, scopes, rate limits and permission checks.

The READ endpoint publishes only `local_mcp\read\registry`. The WRITE endpoint publishes
only `local_mcp\write\registry`. There is no combined registry followed by a risk filter.

## OAuth 2.1

The authorization server uses Authorization Code + PKCE S256.

- Authorization: `/local/mcp/oauth/authorize.php`
- Token: `/local/mcp/oauth/token.php`
- Dynamic registration: `/local/mcp/oauth/register.php`
- Authorization Server Metadata: `/local/mcp/.well-known/oauth-authorization-server.php`
- Protected Resource Metadata: `/local/mcp/.well-known/oauth-protected-resource.php?side=server` (or `side=read`/`side=write`)

OAuth clients must send the RFC 8707 `resource` parameter in both authorization-code authorization and token requests. The value must exactly match the MCP endpoint URL (`server.php`, `read.php`, or `write.php`). OAuth tokens are audience-bound to the selected endpoint, including when refresh tokens rotate. Tokens issued before audience binding was introduced must be reauthorized.

OAuth access tokens are short-lived opaque secrets. Refresh tokens rotate. Authorization codes are short-lived and
single-use. Database records store SHA-256 hashes, never the complete secret.

The consent page deliberately uses:

```php
$context = context_system::instance();

if (!has_capability('moodle/site:config', $context)) {
    // Render a normal Moodle denial page.
}
```

It does **not** use `require_capability()` for the authorization experience, because a normal non-admin user must
receive an understandable Moodle page rather than an uncaught authorization exception.

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

## Manual tokens

Site administrators can create manual tokens for Claude Desktop, IDEs, scripts, internal agents and test clients.

Manual tokens start with `mcp_`. The complete secret is returned once. Only a prefix and hash are stored.

Manual tokens and OAuth tokens have separate storage and lifecycle, but both resolve to `authenticated_identity`.

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

## Audit

The audit schema stores operation and security metadata, not ChatGPT conversations or complete model prompts/responses.

Sensitive secrets are explicitly excluded from audit metadata.

## Security notes

- OAuth remote use should be HTTPS in production.
- Redirect URIs use exact matching.
- OAuth `state` is preserved but never replaces Moodle `sesskey`.
- Consent authorization is POST + `sesskey`.
- External client text is escaped before presentation.
- Access, refresh, manual, authorization and confirmation secrets are hashed at rest.
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


### Temporary ChatGPT connection without OAuth

The first-run screen at `/local/mcp/index.php` now contains only a link to
`/local/mcp/admin/connections.php?new=1`. On the Connections page, choose
**New connection** and enter a name. READ is preselected; enable WRITE explicitly
if you need to modify Moodle. The administrator's Moodle account owns the token.

Creating the connection uses the existing manual-token service. The result is
a complete URL such as:

```text
https://YOUR-MOODLE/local/mcp/server.php?toke=mcp_RANDOM_SECRET
```

Copy this value into ChatGPT's **MCP Server URL** field and select
**No authentication**. Do not configure OAuth for this temporary test connection.
`?token=...` is accepted as an alias, but the interface always generates `?toke=...`.

After a test connection is created, **Connections → Edit permissions** can change
its READ and WRITE scopes without creating a new token or changing the MCP
server URL. Only active, non-expired test connections can be edited, and at
least one permission must remain selected. Saving immediately changes which
tools the token can access and clears outstanding WRITE confirmation tokens.
ChatGPT may need to reload or reconnect the plugin to refresh its tool listing.

The URL is displayed once, immediately after token creation, and is not saved
in plaintext. The token is SHA-256 hashed in Moodle's database, has a 7-day
expiration, can be revoked on the Connections page, and inherits the permissions
of the Moodle administrator who created it. READ/WRITE capabilities and
the confirmation requirement for WRITE tools are unchanged.

**Important:** a query-string token is a bearer credential. Web servers,
reverse proxies, browser history and monitoring tools may log the complete URL.
Anyone with that URL can use the token until it expires or is revoked. Use HTTPS,
avoid sharing the URL, limit log access, and switch to OAuth for production.
The query-string mechanism is only enabled on the combined `server.php` endpoint;
OAuth access tokens are rejected in query strings. The existing OAuth
implementation remains available for integrations that require it.

## Development

Run Moodle PHPUnit tests for `local_mcp` and the repository CI. GitHub Actions validates PHP syntax and the Moodle
plugin package.

## Connect ChatGPT as a custom MCP plugin

1. Enable **Dynamic client registration** in the Moodle MCP administration settings.
2. Make the Moodle site reachable over HTTPS and check the OAuth well-known configuration below.
3. In ChatGPT on the web, use **Plugins → Add custom MCP server** and set the server URL to
   `https://YOUR-MOODLE/local/mcp/server.php`, with OAuth and DCR registration.
4. Complete the normal Moodle sign-in and administrator consent.
5. Use the connection with `mcp:read` and optionally `mcp:write`. The combined server
   lists only the tools allowed by the access token.

ChatGPT can also connect to `read.php` and `write.php` independently.
The Moodle consent screen currently restricts OAuth authorizations to site administrators
(`moodle/site:config`), even when an individual tool has more granular capabilities.
This is a deliberate access policy and is **not** an end-user permission system.

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
need a second call. The first call returns a preview and a single-use token; after the
user has approved the preview, the client repeats the **same** tool with identical
arguments and the returned `confirmation_token`. The token is checked against the
user, client, resource-bound access token, tool, context and argument hash.
A client should never autonomously treat receiving a preview as user approval.

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

The create-activity operations use Moodle's standard `prepare_new_moduleinfo_data()` / `add_moduleinfo()` APIs and check both course-management and activity-creation capabilities. The remaining tools work with **existing** Moodle activities. The forum integration
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

`get_course_image(courseid, include_image=true)` returns the current course cover image and metadata from the native `course/overviewfiles` area. When its size is up to 2 MiB and it is PNG, JPEG or WebP, it also returns MCP image content for visual inspection. Access requires `mcp:read` and `moodle/course:view`.

`set_course_image(courseid, image_base64|image_url, expected_contenthash?)` replaces existing cover images through Moodle File API, preserving non-image files. It accepts Base64 or an HTTPS image URL, allows up to 5 MiB and 40 megapixels, requires `mcp:write`, `moodle/course:update`, and `moodle/course:changesummary`, and uses the normal two-call confirmation handshake. The optional expected hash prevents overwriting another update. The server rejects local hosts and direct IPs; Moodle's configured cURL security rules also apply.

To upload a generated image, the client must provide its bytes or a genuinely accessible HTTPS download URL. ChatGPT-private image URLs or conversation references cannot be fetched by Moodle.

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
