<?php
namespace local_mcp\audit;

defined('MOODLE_INTERNAL') || die;

final class logger {
    public static function record(string $event, ?\local_mcp\security\authenticated_identity $identity = null,
            ?string $tool = null, ?string $side = null, ?\context $context = null, array $metadata = [],
            bool $destructive = false, bool $dryrun = false, bool $confirmation = false,
            ?string $status = null, ?int $durationms = null): void {
        global $DB;
        $safe = [];
        foreach ($metadata as $key => $value) {
            if (preg_match('/token|code|secret|prompt|response/i', (string)$key)) {
                continue;
            }
            $safe[$key] = $value;
        }
        $DB->insert_record('local_mcp_audit', (object)[
            'connectionid' => $identity?->connectionid,
            'clientid' => $identity?->clientid,
            'userid' => $identity?->userid,
            'event' => clean_param($event, PARAM_ALPHANUMEXT),
            'tool' => $tool ? clean_param($tool, PARAM_ALPHANUMEXT) : null,
            'side' => $side,
            'destructive' => (int)$destructive,
            'contextid' => $context?->id,
            'ip' => getremoteaddr(),
            'durationms' => $durationms,
            'status' => $status,
            'dryrun' => (int)$dryrun,
            'confirmation' => (int)$confirmation,
            'metadata' => $safe ? json_encode($safe, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            'timecreated' => time(),
        ]);
    }
}
