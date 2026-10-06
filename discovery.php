<?php
require_once(__DIR__ . '/../../config.php');

$base = $CFG->wwwroot . '/local/mcp';
\local_mcp\protocol\http::json([
    'name' => 'Moodle MCP',
    'mcp' => [
        'read' => $base . '/read.php',
        'write' => $base . '/write.php',
    ],
    'oauth' => [
        'issuer' => $base,
        'authorization_server_metadata' => $base . '/.well-known/oauth-authorization-server.php',
        'protected_resource_metadata' => $base . '/.well-known/oauth-protected-resource.php',
    ],
]);
