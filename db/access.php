<?php
defined('MOODLE_INTERNAL') || die;

$capabilities = [
    'local/mcp:manage' => [
        'riskbitmask' => RISK_CONFIG | RISK_DATALOSS | RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['manager' => CAP_PROHIBIT],
    ],
];
