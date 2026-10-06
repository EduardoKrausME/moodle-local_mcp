<?php
namespace local_mcp\event;

defined('MOODLE_INTERNAL') || die;

final class connection_authorized extends \core\event\base {
    protected function init(): void {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_mcp_connection';
    }

    public static function get_name(): string {
        return 'MCP connection authorized';
    }

    public function get_description(): string {
        return "The user with id '{$this->userid}' authorized MCP connection '{$this->objectid}'.";
    }
}
