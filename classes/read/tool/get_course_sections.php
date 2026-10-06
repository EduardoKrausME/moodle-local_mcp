<?php
namespace local_mcp\read\tool;
defined('MOODLE_INTERNAL') || die;
final class get_course_sections extends get_course_contents {
    public function get_name(): string { return 'get_course_sections'; }
    public function get_title(): string { return 'Get course sections'; }
    public function get_description(): string { return 'Return visible course sections with their activities.'; }
}
