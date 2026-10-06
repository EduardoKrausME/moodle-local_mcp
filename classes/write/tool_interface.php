<?php
namespace local_mcp\write;

defined('MOODLE_INTERNAL') || die;

interface tool_interface {
    public function get_name(): string;
    public function get_title(): string;
    public function get_description(): string;
    public function get_input_schema(): array;
    public function get_required_capability(): string;
    public function resolve_context(array $arguments): \context;
    public function supports_dry_run(): bool;
    public function requires_confirmation(): bool;
    public function is_destructive(): bool;
    public function preview(array $arguments, \local_mcp\security\authenticated_identity $identity): array;
    public function execute(array $arguments, \local_mcp\security\authenticated_identity $identity): array;
}
