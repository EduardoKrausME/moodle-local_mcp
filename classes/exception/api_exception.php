<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * api_exception.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\exception;

use moodle_exception;

/**
 * Class api_exception.
 */
class api_exception extends moodle_exception {
    public string $machinecode;

    public int $httpstatus;

    public array $details;

    /** @var string|null Explicitly approved client-facing message, never inferred from debug info. */
    private ?string $publicmessage;

    /**
     * Method __construct.
     *
     * @param string $machinecode Parameter machinecode.
     * @param int $httpstatus Parameter httpstatus.
     * @param string $message Parameter message.
     * @param array $details Parameter details.
     * @param string|null $publicmessage Safe, static public message, not exception or user input.
     */
    public function __construct(
        string $machinecode,
        int $httpstatus = 400,
        string $message = '',
        array $details = [],
        ?string $publicmessage = null
    ) {
        $this->machinecode = $machinecode;
        $this->httpstatus = $httpstatus;
        $this->details = $details;
        $this->publicmessage = $publicmessage;
        parent::__construct('error', 'local_mcp', '', null, $message ?: $machinecode);
    }

    /**
     * Return the explicitly approved message or the existing localized generic message.
     * @return string
     */
    public function get_public_message(): string {
        return $this->publicmessage ?? $this->getMessage();
    }

    /**
     * Normalize only known native Moodle failures. Never copy native exception text or data.
     * @param \Throwable $exception Original failure.
     * @return self|null Structured failure, or null for unexpected errors.
     */
    public static function from_throwable(\Throwable $exception): ?self {
        if ($exception instanceof self) {
            return $exception;
        }
        if ($exception instanceof \required_capability_exception) {
            return new self('permission_denied', 403, '', [], 'Permission denied.');
        }
        if ($exception instanceof \invalid_parameter_exception) {
            return new self('invalid_params', 400, '', [], 'Invalid parameters.');
        }
        if (!$exception instanceof moodle_exception ||
                !in_array($exception->module, ['error', 'moodle'], true)) {
            return null;
        }
        switch ($exception->errorcode) {
            case 'invalidcourseid':
                return new self('course_not_found', 404, '', [], 'Course not found or unavailable.');
            case 'invalidcoursemodule':
                return new self('activity_not_found', 404, '', [], 'Activity not found or unavailable.');
            case 'invalidparameter':
                return new self('invalid_params', 400, '', [], 'Invalid parameters.');
            default:
                return null;
        }
    }
}
