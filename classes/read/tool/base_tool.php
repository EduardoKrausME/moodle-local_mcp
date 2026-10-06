<?php
namespace local_mcp\read\tool;

defined('MOODLE_INTERNAL') || die;

abstract class base_tool implements \local_mcp\read\tool_interface {
    protected function object_schema(array $properties, array $required = []): array {
        return ['type' => 'object', 'properties' => $properties, 'required' => $required, 'additionalProperties' => false];
    }

    protected function course_context(int $courseid): \context_course {
        return \context_course::instance($courseid, MUST_EXIST);
    }

    protected function user_context(int $userid): \context_user {
        return \context_user::instance($userid, MUST_EXIST);
    }
}
