<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * OAuth discovery metadata for MCP clients.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\oauth;

use local_mcp\exception\api_exception;

/**
 * Build RFC 8414 and RFC 9728 documents without trusting user-supplied issuer URLs.
 */
final class metadata {
    /**
     * Resolve the request path relative to Moodle's configured wwwroot.
     *
     * @param ?string $uri HTTP request target for testing or the current request.
     * @return string
     */
    private static function request_path(?string $uri = null): string {
        global $CFG;

        $path = (string)(parse_url($uri ?? ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
        $prefix = rtrim((string)(parse_url($CFG->wwwroot, PHP_URL_PATH) ?: ''), '/');
        if ($prefix !== '' && ($path === $prefix || str_starts_with($path, $prefix . '/'))) {
            $path = substr($path, strlen($prefix));
        }
        return $path;
    }

    /**
     * Produce OAuth authorization-server metadata for the issuer being probed.
     *
     * Some clients use the full server.php URL as a fallback issuer, while modern
     * clients follow the protected resource document to the canonical /local/mcp issuer.
     *
     * @param ?string $uri HTTP request target, or null for the current request.
     * @return array
     */
    public static function authorization_server(?string $uri = null): array {
        global $CFG;

        $root = rtrim($CFG->wwwroot, '/');
        $base = $root . '/local/mcp';
        $path = self::request_path($uri);
        $issuer = $base;

        if (preg_match('~^/\.well-known/(?:oauth-authorization-server|openid-configuration)/?$~D', $path)) {
            $issuer = $root;
        } else if (preg_match('~^/\.well-known/(?:oauth-authorization-server|openid-configuration)/local/mcp/(server|read|write)\.php/?$~D',
                $path, $matches) || preg_match('~^/local/mcp/(server|read|write)\.php/\.well-known/(?:oauth-authorization-server|openid-configuration)/?$~D',
                $path, $matches)) {
            $issuer = $base . '/' . $matches[1] . '.php';
        }

        $result = [
            'issuer' => $issuer,
            'authorization_endpoint' => $base . '/oauth/authorize.php',
            'token_endpoint' => $base . '/oauth/token.php',
            'scopes_supported' => ['mcp:read', 'mcp:write'],
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'resource_indicators_supported' => true,
            'token_endpoint_auth_methods_supported' => ['none'],
        ];
        if (get_config('local_mcp', 'dynamicregistration')) {
            $result['registration_endpoint'] = $base . '/oauth/register.php';
        }
        return $result;
    }

    /**
     * Return metadata for the protected MCP resource endpoint.
     *
     * @param string $side read, write or combined server.
     * @param ?string $uri HTTP request target for a well-known lookup.
     * @return array
     */
    public static function protected_resource(string $side = 'server', ?string $uri = null): array {
        global $CFG;

        $path = self::request_path($uri);
        if (preg_match('~^/\.well-known/oauth-protected-resource/local/mcp/(server|read|write)\.php/?$~D',
                $path, $matches)) {
            $side = $matches[1];
        }
        if (!in_array($side, ['read', 'write', 'server'], true)) {
            throw new api_exception('invalid_target', 400);
        }
        return [
            'resource' => resource::endpoint($side),
            'authorization_servers' => [rtrim($CFG->wwwroot, '/') . '/local/mcp'],
            'scopes_supported' => $side === 'read' ? ['mcp:read']
                : ($side === 'write' ? ['mcp:write'] : ['mcp:read', 'mcp:write']),
            'bearer_methods_supported' => ['header'],
        ];
    }

    /**
     * Support the URL-appended metadata fallback used by some MCP clients.
     *
     * @param string $side The MCP entrypoint in use.
     * @param ?string $uri HTTP request target, or null for the current request.
     * @return bool
     */
    public static function is_appended_discovery(string $side, ?string $uri = null): bool {
        if (!in_array($side, ['read', 'write', 'server'], true)) {
            return false;
        }
        return (bool)preg_match('~^/local/mcp/' . $side
            . '\.php/\.well-known/(?:oauth-authorization-server|openid-configuration)/?$~D',
            self::request_path($uri));
    }
}
