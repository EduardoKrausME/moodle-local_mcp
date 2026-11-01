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
 * Register quiz READ and WRITE MCP tools.
 *
 * @package   mcptool_quiz
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_quiz;

use local_mcp\extension\read_provider_interface;
use local_mcp\extension\write_provider_interface;

/**
 * Read-only analytics and confirmed quiz management mutations.
 */
final class provider implements read_provider_interface, write_provider_interface {
    /**
     * Method get_read_tools.
     *
     * @return array Return value.
     */
    public function get_read_tools(): array {
        return [
            new \mcptool_quiz\read\get_question_slots(),
            new \mcptool_quiz\read\get_attempt_responses(),
            new \mcptool_quiz\read\get_attempt_statistics(),
            new \mcptool_quiz\read\get_question_statistics(),
            new \mcptool_quiz\read\get_question_versions(),
            new \mcptool_quiz\read\get_question_usage(),
            new \mcptool_quiz\read\get_proctoring_status(),
        ];
    }

    /**
     * Method get_write_tools.
     *
     * @return array Return value.
     */
    public function get_write_tools(): array {
        return [
            new \mcptool_quiz\write\create_activity(),
            new \mcptool_quiz\write\add_question(),
            new \mcptool_quiz\write\update_activity(),
            new \mcptool_quiz\write\remove_question(),
            new \mcptool_quiz\write\reorder_questions(),
            new \mcptool_quiz\write\regrade_attempt(),
        ];
    }
}
