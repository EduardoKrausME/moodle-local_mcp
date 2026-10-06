<?php
defined('MOODLE_INTERNAL') || die;

$tasks = [
    [
        'classname' => '\\local_mcp\\task\\cleanup',
        'blocking' => 0,
        'minute' => '17',
        'hour' => '3',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
];
