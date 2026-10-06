<?php
define('NO_DEBUG_DISPLAY', true);
require_once(__DIR__ . '/../../../config.php');\n\n\\local_mcp\\security\\transport::require_secure();

try {
    if (!get_config('local_mcp', 'dynamicregistration')) {
        throw new \local_mcp\exception\api_exception('registration_disabled', 403);
    }
    \local_mcp\security\rate_limiter::check('oauth_register', getremoteaddr(),
        (int)get_config('local_mcp', 'rateoauth') ?: 30);
    $data = \local_mcp\protocol\http::request_json();

    $granttypes = $data['grant_types'] ?? ['authorization_code', 'refresh_token'];
    $responsetypes = $data['response_types'] ?? ['code'];
    $authmethod = $data['token_endpoint_auth_method'] ?? 'none';
    if (array_diff($granttypes, ['authorization_code', 'refresh_token'])
            || array_diff($responsetypes, ['code']) || $authmethod !== 'none') {
        throw new \local_mcp\exception\api_exception('invalid_client_metadata', 400);
    }

    $result = \local_mcp\oauth\client_service::register_public($data);
    \local_mcp\protocol\http::json($result, 201);
} catch (\Throwable $e) {
    \local_mcp\protocol\http::error($e);
}
