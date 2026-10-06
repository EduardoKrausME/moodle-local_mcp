<?php
namespace local_mcp\event;

defined('MOODLE_INTERNAL') || die;

final class manual_token_created extends \core\event\base {
    protected function init(): void {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_mcp_manual_token';
    }

    public static function get_name(): string {
        return 'MCP manual token created';
    }

    public function get_description(): string {
        return "The user with id '{$this->userid}' created MCP manual token '{$this->objectid}'.";
    }
}
