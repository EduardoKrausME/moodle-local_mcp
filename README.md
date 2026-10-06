# Moodle MCP

Moodle MCP turns a Moodle installation into an extensible Model Context Protocol server with a deliberately separated READ and WRITE architecture.

This is a **local plugin**: `local_mcp`. It does not use Moodle's traditional `wstoken` mechanism as its primary authentication layer.

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

## MCP endpoints

- READ: `/local/mcp/read.php`
- WRITE: `/local/mcp/write.php`
- Plugin discovery: `/local/mcp/discovery.php`

The READ endpoint publishes only `local_mcp\read\registry`. The WRITE endpoint publishes only `local_mcp\write\registry`. There is no combined registry followed by a risk filter.

## OAuth 2.1

The authorization server uses Authorization Code + PKCE S256.

- Authorization: `/local/mcp/oauth/authorize.php`
- Token: `/local/mcp/oauth/token.php`
- Dynamic registration: `/local/mcp/oauth/register.php`
- Authorization Server Metadata: `/local/mcp/.well-known/oauth-authorization-server.php`
- Protected Resource Metadata: `/local/mcp/.well-known/oauth-protected-resource.php`

OAuth access tokens are short-lived opaque secrets. Refresh tokens rotate. Authorization codes are short-lived and single-use. Database records store SHA-256 hashes, never the complete secret.

The consent page deliberately uses:

```php
$context = context_system::instance();

if (!has_capability('moodle/site:config', $context)) {
    // Render a normal Moodle denial page.
}
```

It does **not** use `require_capability()` for the authorization experience, because a normal non-admin user must receive an understandable Moodle page rather than an uncaught authorization exception.

## Scopes and Moodle capabilities

Initial scopes are independent:

- `mcp:read`
- `mcp:write`

`mcp:write` does not imply `mcp:read`.

A scope is only the outer protocol permission. Every tool still resolves the Moodle user and Moodle context and checks the required Moodle capability before execution.

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

Confirmation is not a boolean such as `confirmed=true`. The server issues a short-lived, single-use confirmation token bound to the connected user, client/connection, access token, tool, resolved context and a canonical SHA-256 hash of the exact arguments.

Changing a course id, user id or any other argument after preview invalidates the confirmation.

Destructive tools always require this flow.

## Included READ tools

`search_courses`, `get_course`, `get_course_contents`, `get_course_sections`,
`get_course_activities`, `get_course_participants`, `search_users`, `get_user`,
`get_user_courses`, `get_progress`, `get_grades`, `get_assignments`,
`get_assignment`, `get_submissions`, `get_quizzes`, `get_quiz`,
`get_attempts`, and `get_calendar_events`.

## Included WRITE tools

`create_course`, `update_course`, `create_section`, `update_section`,
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

The provider implements `local_mcp\extension\read_provider_interface` and returns only `local_mcp\read\tool_interface` instances.

WRITE providers use `local_mcp\extension\write_provider_interface`. A provider cannot accidentally register a WRITE tool on the READ side.

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

The plugin contains no ChatGPT-specific authentication code. ChatGPT is expected to behave like any other standards-based MCP/OAuth client.

The future flow is:

```
Moodle URL
-> Moodle MCP discovery
-> OAuth discovery
-> Moodle login
-> site administrator capability check
-> consent
-> authorization code
-> access + refresh token
-> READ/WRITE MCP endpoints
```

## Development

Run Moodle PHPUnit tests for `local_mcp` and the repository CI. GitHub Actions validates PHP syntax and the Moodle plugin package.
