<?php
namespace local_mcp\read;

defined('MOODLE_INTERNAL') || die;

final class registry {
    /** @return tool_interface[] */
    public static function get_tools(): array {
        $tools = [
            new \local_mcp\read\tool\search_courses(),
            new \local_mcp\read\tool\get_course(),
            new \local_mcp\read\tool\get_course_contents(),
            new \local_mcp\read\tool\get_course_participants(),
            new \local_mcp\read\tool\search_users(),
            new \local_mcp\read\tool\get_user(),
            new \local_mcp\read\tool\get_user_courses(),
        ];
        foreach (\local_mcp\extension\manager::read_providers() as $provider) {
            foreach ($provider->get_read_tools() as $tool) {
                if (!$tool instanceof tool_interface) {
                    throw new \coding_exception('READ provider returned a non-READ tool.');
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
                throw new \coding_exception('Duplicate MCP READ tool: ' . $name);
            }
            $indexed[$name] = $tool;
        }
        return $indexed;
    }
}
