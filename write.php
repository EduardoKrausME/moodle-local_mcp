<?php
define('NO_DEBUG_DISPLAY', true);
require_once(__DIR__ . '/../../config.php');\n\n\\local_mcp\\security\\transport::require_secure();

(new \local_mcp\protocol\mcp_server('write'))->handle();
