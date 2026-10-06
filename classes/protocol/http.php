<?php
namespace local_mcp\protocol;

defined('MOODLE_INTERNAL') || die;

final class http {
    public static function json(array $data, int $status = 200, array $headers = []): never {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        foreach ($headers as $header) {
            header($header);
        }
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function request_json(): array {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '{}', true);
        if (!is_array($data)) {
            throw new \local_mcp\exception\api_exception('invalid_request', 400);
        }
        return $data;
    }

    public static function bearer(): array {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(.+)$/i', trim($header), $m)) {
            throw new \local_mcp\exception\api_exception('invalid_token', 401);
        }
        return [$header, trim($m[1])];
    }

    public static function error(\Throwable $e): never {
        if ($e instanceof \local_mcp\exception\api_exception) {
            $headers = [];
            if ($e->httpstatus === 401) {
                $metadata = (new \moodle_url('/local/mcp/.well-known/oauth-protected-resource.php'))->out(false);
                $headers[] = 'WWW-Authenticate: Bearer resource_metadata="' . $metadata . '"';
            }
            self::json(['error' => $e->machinecode, 'message' => $e->getMessage(), 'details' => $e->details],
                $e->httpstatus, $headers);
        }
        self::json(['error' => 'internal_error', 'message' => 'The request could not be completed.'], 500);
    }
}
