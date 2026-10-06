<?php
require_once(__DIR__ . '/../../../config.php');

$base = $CFG->wwwroot . '/local/mcp';
$data = [
    'issuer' => $base,
    'authorization_endpoint' => $base . '/oauth/authorize.php',
    'token_endpoint' => $base . '/oauth/token.php',
    'scopes_supported' => ['mcp:read', 'mcp:write'],
    'response_types_supported' => ['code'],
    'grant_types_supported' => ['authorization_code', 'refresh_token'],
    'code_challenge_methods_supported' => ['S256'],
    'token_endpoint_auth_methods_supported' => ['none'],
];
if (get_config('local_mcp', 'dynamicregistration')) {
    $data['registration_endpoint'] = $base . '/oauth/register.php';
}
\local_mcp\protocol\http::json($data);
