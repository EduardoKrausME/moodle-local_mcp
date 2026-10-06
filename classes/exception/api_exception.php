<?php
namespace local_mcp\exception;

defined('MOODLE_INTERNAL') || die;

class api_exception extends \moodle_exception {
    public function __construct(
        public readonly string $machinecode,
        public readonly int $httpstatus = 400,
        string $message = '',
        public readonly array $details = [],
    ) {
        parent::__construct('error', 'local_mcp', '', null, $message ?: $machinecode);
    }
}
