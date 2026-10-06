<?php
namespace local_mcp\security;

defined('MOODLE_INTERNAL') || die;

final class token_resolver {
    public static function from_bearer(?string $authorization): authenticated_identity {
        global $DB;
        if (!$authorization || !preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $m)) {
            throw new \local_mcp\exception\api_exception('invalid_token', 401);
        }
        $token = trim($m[1]);
        $prefix = secret::prefix($token);
        $hash = secret::hash($token);

        if (str_starts_with($token, 'mcp_at_')) {
            $records = $DB->get_records('local_mcp_access_token', ['prefix' => $prefix]);
            foreach ($records as $rec) {
                if (!hash_equals($rec->tokenhash, $hash)) {
                    continue;
                }
                if ($rec->revokedat || $rec->expiresat < time()) {
                    throw new \local_mcp\exception\api_exception('expired_token', 401);
                }
                $connection = $DB->get_record('local_mcp_connection', ['id' => $rec->connectionid, 'enabled' => 1]);
                if (!$connection || $connection->revokedat) {
                    throw new \local_mcp\exception\api_exception('invalid_token', 401);
                }
                self::touch('local_mcp_access_token', $rec->id);
                return new authenticated_identity(
                    (int)$rec->userid,
                    'oauth',
                    scope::parse($rec->scopes),
                    (int)$rec->clientid,
                    (int)$rec->connectionid,
                    (int)$rec->id,
                    $rec->family
                );
            }
        }

        if (str_starts_with($token, 'mcp_') && !str_starts_with($token, 'mcp_at_') && !str_starts_with($token, 'mcp_rt_')) {
            $records = $DB->get_records('local_mcp_manual_token', ['prefix' => $prefix, 'enabled' => 1]);
            foreach ($records as $rec) {
                if (!hash_equals($rec->tokenhash, $hash)) {
                    continue;
                }
                if ($rec->expires && $rec->expires < time()) {
                    throw new \local_mcp\exception\api_exception('expired_token', 401);
                }
                self::touch('local_mcp_manual_token', $rec->id);
                $scopes = [];
                if ($rec->readenabled) {
                    $scopes[] = scope::READ;
                }
                if ($rec->writeenabled) {
                    $scopes[] = scope::WRITE;
                }
                return new authenticated_identity((int)$rec->userid, 'manual', $scopes, null, null, (int)$rec->id);
            }
        }

        throw new \local_mcp\exception\api_exception('invalid_token', 401);
    }

    private static function touch(string $table, int $id): void {
        global $DB;
        $DB->update_record($table, (object)[
            'id' => $id,
            'lastused' => time(),
            'lastip' => getremoteaddr(),
        ]);
    }
}
