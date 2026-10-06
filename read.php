<?php
define('NO_DEBUG_DISPLAY', true);
require_once(__DIR__ . '/../../config.php');

(new \local_mcp\protocol\mcp_server('read'))->handle();
