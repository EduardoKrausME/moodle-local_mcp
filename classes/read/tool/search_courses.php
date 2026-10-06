<?php
namespace local_mcp\read\tool;

defined('MOODLE_INTERNAL') || die;

final class search_courses extends base_tool {
    public function get_name(): string { return 'search_courses'; }
    public function get_title(): string { return 'Search courses'; }
    public function get_description(): string { return 'Search courses visible to the connected Moodle user.'; }
    public function get_required_capability(): string { return 'moodle/course:view'; }
    public function get_input_schema(): array {
        return $this->object_schema([
            'query' => ['type' => 'string'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
        ]);
    }
    public function resolve_context(array $arguments): \context { return \context_system::instance(); }
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        $query = trim((string)($arguments['query'] ?? ''));
        $limit = min(100, max(1, (int)($arguments['limit'] ?? 20)));
        $out = [];
        foreach (get_courses('all', 'c.sortorder ASC', 'c.id,c.fullname,c.shortname,c.visible') as $course) {
            if ((int)$course->id === SITEID) { continue; }
            $ctx = \context_course::instance($course->id);
            if (!has_capability('moodle/course:view', $ctx, $identity->userid)) { continue; }
            if ($query !== '' && stripos($course->fullname . ' ' . $course->shortname, $query) === false) { continue; }
            $out[] = ['id'=>(int)$course->id,'fullname'=>$course->fullname,'shortname'=>$course->shortname,'visible'=>(bool)$course->visible];
            if (count($out) >= $limit) { break; }
        }
        return ['courses' => $out];
    }
}
