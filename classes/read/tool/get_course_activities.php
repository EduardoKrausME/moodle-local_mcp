<?php
namespace local_mcp\read\tool;
defined('MOODLE_INTERNAL') || die;
final class get_course_activities extends get_course_contents {
    public function get_name(): string { return 'get_course_activities'; }
    public function get_title(): string { return 'Get course activities'; }
    public function get_description(): string { return 'Return visible Moodle activities grouped by section.'; }
}
