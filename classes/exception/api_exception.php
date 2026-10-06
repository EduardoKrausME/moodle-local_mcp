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

defined('MOODLE_INTERNAL') || die;

/**
 * Class api_exception.
 */
class api_exception extends \moodle_exception {
    /**
     * Method __construct.
     *
     * @param string $machinecode Parameter machinecode.
     * @param int $httpstatus Parameter httpstatus.
     * @param string $message Parameter message.
     * @param array $details Parameter details.
     */
    public function __construct(
        public readonly string $machinecode,
        public readonly int $httpstatus = 400,
        string $message = '',
        public readonly array $details = [],
    ) {
        parent::__construct('error', 'local_mcp', '', null, $message ?: $machinecode);
    }
}
