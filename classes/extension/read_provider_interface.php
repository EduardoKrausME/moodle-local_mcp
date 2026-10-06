<?php
namespace local_mcp\extension;

defined('MOODLE_INTERNAL') || die;

interface read_provider_interface {
    /** @return \local_mcp\read\tool_interface[] */
    public function get_read_tools(): array;
}
