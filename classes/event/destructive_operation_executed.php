<?php
namespace local_mcp\event;

defined('MOODLE_INTERNAL') || die;

final class destructive_operation_executed extends \core\event\base {
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    public static function get_name(): string {
        return 'MCP destructive operation executed';
    }

    public function get_description(): string {
        return "The user with id '{$this->userid}' executed a destructive MCP write operation.";
    }
}
