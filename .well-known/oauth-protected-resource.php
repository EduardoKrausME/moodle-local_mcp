<?php
require_once(__DIR__ . '/../../../config.php');

$base = $CFG->wwwroot . '/local/mcp';
\local_mcp\protocol\http::json([
    'resource' => $base,
    'authorization_servers' => [$base],
    'scopes_supported' => ['mcp:read', 'mcp:write'],
    'bearer_methods_supported' => ['header'],
]);
