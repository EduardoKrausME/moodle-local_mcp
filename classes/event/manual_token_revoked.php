<?php
namespace local_mcp\event;

defined('MOODLE_INTERNAL') || die;

final class manual_token_revoked extends \core\event\base {
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_mcp_manual_token';
    }

    public static function get_name(): string {
        return 'MCP manual token revoked';
    }

    public function get_description(): string {
        return "The user with id '{$this->userid}' revoked MCP manual token '{$this->objectid}'.";
    }
}
