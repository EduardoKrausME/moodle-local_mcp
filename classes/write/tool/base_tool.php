<?php
namespace local_mcp\write\tool;

defined('MOODLE_INTERNAL') || die;

abstract class base_tool implements \local_mcp\write\tool_interface {
    public function supports_dry_run(): bool { return false; }
    public function requires_confirmation(): bool { return true; }
    public function is_destructive(): bool { return false; }
    public function preview(array $arguments, \local_mcp\security\authenticated_identity $identity): array {
        return ['tool'=>$this->get_name(),'arguments'=>$arguments];
    }
    protected function object_schema(array $properties, array $required=[]): array {
        return ['type'=>'object','properties'=>$properties,'required'=>$required,'additionalProperties'=>false];
    }
}
