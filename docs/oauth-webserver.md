# OAuth discovery for Moodle MCP behind Nginx or Apache

MCP clients do not authenticate by reading `server.php` as a web page. They POST
JSON-RPC to `/local/mcp/server.php`. Before the user signs in, the server returns
`401` and a `WWW-Authenticate: Bearer resource_metadata="..."` challenge. Clients
read the protected-resource metadata, then fetch authorization-server metadata.

The PHP plugin already implements OAuth 2.1, including PKCE S256, but installing
`local_mcp` cannot install the RFC 8414 site-root `/.well-known/` paths for you.
Nginx and Apache must forward these paths to the plugin, without changing the
public URL in the client's request. The plugin derives the issuer from the
**original request path** and keeps it equal to the issuer the client probed.

## Nginx (Moodle at the HTTPS host root)

Put these locations in your existing Moodle `server {}` block, **before a generic
hidden-file deny regex**, and retain the existing PHP-FPM location. Do not put a
`proxy_pass` or `fastcgi_pass` in these rules: internal rewrite to the PHP entrypoint
allows your usual PHP location to handle the request.

```nginx
# OAuth 2.0 / RFC 8414: clients try root, /local/mcp and /local/mcp/server.php.
location = /.well-known/oauth-authorization-server {
    rewrite ^ /local/mcp/oauth-metadata.php last;
}
location = /.well-known/openid-configuration {
    rewrite ^ /local/mcp/oauth-metadata.php last;
}
location ~ ^/\.well-known/(?:oauth-authorization-server|openid-configuration)/local/mcp(?:/(?:server|read|write)\.php)?/?$ {
    rewrite ^ /local/mcp/oauth-metadata.php last;
}

# OAuth 2.0 protected-resource metadata / RFC 9728.
location = /.well-known/oauth-protected-resource {
    rewrite ^ /local/mcp/oauth-resource.php last;
}
location ~ ^/\.well-known/oauth-protected-resource/local/mcp/(?:server|read|write)\.php/?$ {
    rewrite ^ /local/mcp/oauth-resource.php last;
}
```

**Critical:** if a separate `location ~ /\.` block forbids `.well-known` paths,
ensure the MCP regex locations appear before that block. The targets
`oauth-metadata.php` and `oauth-resource.php` have no hidden path components.
Do not route unrelated `/.well-known/` paths (such as ACME challenges) to MCP.

For a Moodle installed under `/moodle`, prefix the matches and targets with
`/moodle`. The PHP endpoints use `$CFG->wwwroot` and support a Moodle prefix.

After updating the Moodle plugin and Nginx config, run:

```bash
sudo nginx -t && sudo systemctl reload nginx
```

## Apache

For a Moodle installed at the document root, add these rules to `.htaccess`
*before* generic fallback rules (mod_rewrite and AllowOverride required):

```apache
RewriteEngine On
RewriteRule ^\.well-known/(oauth-authorization-server|openid-configuration)(/local/mcp(/(server|read|write)\.php)?)?/?$ local/mcp/oauth-metadata.php [L,QSA]
RewriteRule ^\.well-known/oauth-protected-resource(/local/mcp/(server|read|write)\.php)?/?$ local/mcp/oauth-resource.php [L,QSA]
```

## Verify on `univasco.eadsuporte.com.br`

First deploy the plugin and run the Moodle upgrade. Check the installed version:
new PHP logs should report the updated release rather than `1.3.11`.

```bash
BASE=https://univasco.eadsuporte.com.br

# Both metadata URLs should return HTTP 200 and valid JSON.
curl -i "$BASE/.well-known/oauth-authorization-server/local/mcp"
curl -i "$BASE/.well-known/oauth-authorization-server/local/mcp/server.php"
curl -i "$BASE/.well-known/oauth-authorization-server"

# A GET without credentials should discover OAuth (HTTP 401 with resource_metadata).
curl -i "$BASE/local/mcp/server.php"

# The metadata URL advertised by WWW-Authenticate must return HTTP 200.
curl -i "$BASE/local/mcp/oauth-resource.php?side=server"
```

Check the `issuer` field in each authorization-server JSON result: it must be
exactly the *root*, `/local/mcp`, or `/local/mcp/server.php` URL whose
`/.well-known/` metadata was requested, respectively. The protected-resource
response must reference the canonical MCP resource and `/local/mcp` authorization
server. If Nginx returns 404 for root well-known paths, inspect the loaded
virtual-host configuration with `sudo nginx -T` and check which location is
processing those requests.

An unauthenticated **POST** to the server should also return `401` and an OAuth
challenge. Once the user grants access, authenticated POST requests work with
`Authorization: Bearer <OAuth access token>`.

## Backwards compatibility

Legacy manual tokens remain accepted with `Authorization: Bearer mcp_...`.
`/local/mcp/server.php?token=mcp_...` and `?toke=mcp_...` are also retained for
legacy clients, but URLs containing tokens can be logged by reverse proxies.
OAuth access tokens must **never** be sent in query strings.
