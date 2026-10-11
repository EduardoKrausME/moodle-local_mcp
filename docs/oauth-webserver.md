# OAuth automatic discovery for remote MCP clients

Moodle's local plugin directory cannot automatically expose site-root `/.well-known/`
paths. MCP clients discover authorization-server metadata using RFC 8414 from
the issuer `https://moodle.example.org/local/mcp`, and therefore request
`https://moodle.example.org/.well-known/oauth-authorization-server/local/mcp`.
The PHP endpoint already exists at
`https://moodle.example.org/local/mcp/.well-known/oauth-authorization-server.php`.

Configure ONE server-side internal rewrite for the canonical URL. This keeps the
issuer constant and lets PKCE clients such as ChatGPT discover OAuth automatically.

## Nginx

Inside the HTTPS server block for a Moodle installed at the web root:

```nginx
location = /.well-known/oauth-authorization-server/local/mcp {
    rewrite ^ /local/mcp/.well-known/oauth-authorization-server.php last;
}
```

Your existing PHP handler must process the rewritten file. Reload Nginx after
validating its configuration. For a Moodle deployed in a subdirectory, include
that subdirectory in both location and rewritten target, e.g.
`/moodle/.well-known/oauth-authorization-server/local/mcp`.

## Apache

In the Moodle document-root `.htaccess` (with mod_rewrite and AllowOverride enabled),
before Moodle fallback rules:

```apache
RewriteEngine On
RewriteRule ^\.well-known/oauth-authorization-server/local/mcp/?$ local/mcp/.well-known/oauth-authorization-server.php [L,QSA]
```

Adapt the deployment prefix if Moodle is hosted under a subdirectory. Apache
RewriteRules in a VirtualHost use leading slashes and different path handling.

## Verification

1. GET the canonical discovery URL and confirm HTTP 200 with JSON containing
   `issuer`, `authorization_endpoint`, `token_endpoint`,
   `code_challenge_methods_supported: ["S256"]`, and
   `registration_endpoint` when dynamic registration is enabled.
2. Confirm the `issuer` value exactly matches `https://moodle.example.org/local/mcp`.
3. POST to `/local/mcp/server.php` without credentials; confirm HTTP 401 and a
   `WWW-Authenticate` header with `resource_metadata`.
4. GET that resource metadata URL, confirm its `authorization_servers`
   contains the same issuer and its `resource` is the canonical server URL.
5. Sign in with a Moodle site administrator and authorize the requested scopes.

When reverse-proxy rules cannot be changed, use manual Bearer tokens on
`server.php` as before, or configure the OAuth endpoint URLs explicitly if
the client permits manual OAuth setup. **Do not** replace the issuer with the
path of the PHP metadata file; that violates issuer matching and breaks
OAuth server identification.

For clients supporting OAuth, the endpoint URLs are:

- Authorization: `/local/mcp/oauth/authorize.php`
- Token: `/local/mcp/oauth/token.php`
- Dynamic registration: `/local/mcp/oauth/register.php`
- Protected resource: `/local/mcp/.well-known/oauth-protected-resource.php?side=server`

OAuth access tokens must be sent via `Authorization: Bearer`, not as query
parameters. Legacy query tokens are accepted only by combined `server.php`.
