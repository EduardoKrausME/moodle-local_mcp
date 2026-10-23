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
 * Adapter for Moodle's native quiz overview regrading engine.
 *
 * @package   mcptool_quiz
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_quiz;

/**
 * Set the report context without running the HTML report controller.
 *
 * The inherited public regrade_attempt() performs version-aware question-engine regrading.
 */
final class regrade_report extends \quiz_overview_report {
    /**
     * @param \context_module $context Assignment module context.
     */
    public function __construct(\context_module $context) {
        $this->context = $context;
    }
}
