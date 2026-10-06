<?php
namespace local_mcp\extension;

defined('MOODLE_INTERNAL') || die;

interface write_provider_interface {
    /** @return \local_mcp\write\tool_interface[] */
    public function get_write_tools(): array;
}
