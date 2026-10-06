<?php
namespace local_mcp\event;

defined('MOODLE_INTERNAL') || die;

final class write_operation_executed extends \core\event\base {
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    public static function get_name(): string {
        return 'MCP write operation executed';
    }

    public function get_description(): string {
        return "The user with id '{$this->userid}' executed an MCP write operation.";
    }
}
