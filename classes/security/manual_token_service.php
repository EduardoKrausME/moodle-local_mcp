<?php
namespace local_mcp\security;

defined('MOODLE_INTERNAL') || die;

final class manual_token_service {
    public static function create(string $name, int $userid, bool $read, bool $write, ?int $expires): array {
        global $DB;
        if (!$read && !$write) {
            throw new \local_mcp\exception\api_exception('invalid_scope', 400);
        }
        $token = secret::generate('mcp_', 40);
        $now = time();
        $id = $DB->insert_record('local_mcp_manual_token', (object)[
            'name' => clean_param($name, PARAM_TEXT),
            'userid' => $userid,
            'prefix' => secret::prefix($token),
            'tokenhash' => secret::hash($token),
            'readenabled' => (int)$read,
            'writeenabled' => (int)$write,
            'expires' => $expires,
            'enabled' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
            'lastused' => null,
            'lastip' => null,
        ]);
        return ['id' => (int)$id, 'token' => $token];
    }

    public static function revoke(int $id): void {
        global $DB;
        $DB->update_record('local_mcp_manual_token', (object)[
            'id' => $id,
            'enabled' => 0,
            'timemodified' => time(),
        ]);
    }
}
