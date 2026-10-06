<?php
namespace local_mcp\write;

defined('MOODLE_INTERNAL') || die;

final class registry {
    /** @return tool_interface[] */
    public static function get_tools(): array {
        $tools = [
            new \local_mcp\write\tool\create_course(),
            new \local_mcp\write\tool\update_course(),
            new \local_mcp\write\tool\enrol_user(),
            new \local_mcp\write\tool\unenrol_user(),
            new \local_mcp\write\tool\create_user(),
            new \local_mcp\write\tool\update_user(),
            new \local_mcp\write\tool\suspend_user(),
            new \local_mcp\write\tool\send_message(),
        ];
        foreach (\local_mcp\extension\manager::write_providers() as $provider) {
            foreach ($provider->get_write_tools() as $tool) {
                if (!$tool instanceof tool_interface) {
                    throw new \coding_exception('WRITE provider returned a non-WRITE tool.');
                }
                $tools[] = $tool;
            }
        }
        return self::index($tools);
    }

    private static function index(array $tools): array {
        $indexed = [];
        foreach ($tools as $tool) {
            $name = $tool->get_name();
            if (isset($indexed[$name])) {
                throw new \coding_exception('Duplicate MCP WRITE tool: ' . $name);
            }
            $indexed[$name] = $tool;
        }
        return $indexed;
    }
}
