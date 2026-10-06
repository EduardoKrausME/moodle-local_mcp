<?php
namespace local_mcp\security;

defined('MOODLE_INTERNAL') || die;

final class confirmation_service {
    public static function args_hash(array $arguments): string {
        self::ksort_recursive($arguments);
        return hash('sha256', json_encode($arguments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public static function issue(authenticated_identity $identity, string $rawaccess, string $tool,
            array $arguments, \context $context): string {
        global $DB;
        $token = secret::generate('mcp_confirm_', 32);
        $ttl = (int)get_config('local_mcp', 'confirmationttl') ?: 300;
        $DB->insert_record('local_mcp_confirm', (object)[
            'prefix' => secret::prefix($token),
            'tokenhash' => secret::hash($token),
            'userid' => $identity->userid,
            'clientid' => $identity->clientid,
            'connectionid' => $identity->connectionid,
            'accesshash' => secret::hash($rawaccess),
            'tool' => $tool,
            'argshash' => self::args_hash($arguments),
            'contextid' => $context->id,
            'expires' => time() + $ttl,
            'used' => 0,
            'timecreated' => time(),
        ]);
        return $token;
    }

    public static function consume(string $token, authenticated_identity $identity, string $rawaccess,
            string $tool, array $arguments, \context $context): void {
        global $DB;
        $rec = $DB->get_record('local_mcp_confirm', ['tokenhash' => secret::hash($token)], '*', MUST_EXIST);
        $valid = !$rec->used && $rec->expires >= time()
            && (int)$rec->userid === $identity->userid
            && $rec->tool === $tool
            && hash_equals($rec->argshash, self::args_hash($arguments))
            && hash_equals($rec->accesshash, secret::hash($rawaccess))
            && (int)$rec->contextid === (int)$context->id
            && (($rec->clientid === null && $identity->clientid === null) || (int)$rec->clientid === (int)$identity->clientid)
            && (($rec->connectionid === null && $identity->connectionid === null) || (int)$rec->connectionid === (int)$identity->connectionid);
        if (!$valid) {
            throw new \local_mcp\exception\api_exception('confirmation_expired', 409);
        }
        $DB->set_field('local_mcp_confirm', 'used', 1, ['id' => $rec->id]);
    }

    private static function ksort_recursive(array &$value): void {
        ksort($value);
        foreach ($value as &$item) {
            if (is_array($item)) {
                self::ksort_recursive($item);
            }
        }
    }
}
