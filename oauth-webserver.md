# OAuth discovery rewrite for Moodle MCP

The `local_mcp` OAuth issuer is `https://YOUR-MOODLE/local/mcp`, and standards-based MCP clients must be able to fetch:

```text
https://YOUR-MOODLE/.well-known/oauth-authorization-server/local/mcp
```

Moodle plugins cannot register routes outside Moodle's directory, so configure **one** web-server rewrite to the plugin's authorization metadata endpoint.

Apache, in the Moodle site's root rewrite configuration:

```apache
RewriteEngine On
RewriteRule ^\.well-known/oauth-authorization-server/local/mcp$ local/mcp/.well-known/oauth-authorization-server.php [L]
```

Nginx, inside the Moodle virtual host:

```nginx
location = /.well-known/oauth-authorization-server/local/mcp {
    rewrite ^ /local/mcp/.well-known/oauth-authorization-server.php last;
}
```

For Moodle installed under a subdirectory, adjust **both** URLs, including the subdirectory prefix. Do not redirect to an issuer different from the `issuer` field returned by the endpoint.

After applying the configuration, verify that the discovery URL returns valid authorization-server metadata. Then enable **Dynamic client registration** in the plugin settings and connect the MCP client to `https://YOUR-MOODLE/local/mcp/server.php` over HTTPS. Complete the normal Moodle sign-in and administrator consent.

**Security:** OAuth uses Authorization Code with PKCE S256; the client must supply the endpoint-bound `resource` parameter. Do not put OAuth tokens in URL query parameters.

For illustrated setup instructions, see [Português](pt_br/recursos/oauth.html) or [English](en/recursos/oauth.html).
