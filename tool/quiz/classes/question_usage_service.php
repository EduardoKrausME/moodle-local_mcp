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
 * Question bank version and quiz reuse inspection.
 *
 * @package   mcptool_quiz
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_quiz;

use context_module;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\security\untrusted_content;

/**
 * Read question bank versions and authorized quiz references, without answer keys.
 */
final class question_usage_service {
    /**
     * Use the legacy quiz_slots.questionid schema before Moodle 4.0.
     */
    private static function driver(): string {
        global $CFG;
        return (int)$CFG->version < 2022041900
            ? legacy_question_usage_service::class
            : versioned_question_usage_service::class;
    }

    public static function versions(array $a, authenticated_identity $identity): array {
        $driver = self::driver();
        return $driver::versions($a, $identity);
    }

    public static function usage(array $a, authenticated_identity $identity): array {
        $driver = self::driver();
        return $driver::usage($a, $identity);
    }
}
