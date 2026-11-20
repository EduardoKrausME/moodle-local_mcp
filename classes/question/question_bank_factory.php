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
 * Select the question storage implementation once per request.
 *
 * @package local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_mcp\question;

/**
 * Class question_bank_factory
 */
final class question_bank_factory {
    /** @var string|null */
    private static $driver = null;

    /**
     * driver
     *
     * @return string
     */
    public static function driver(): string {
        global $CFG;
        if (self::$driver === null) {
            self::$driver = (int)$CFG->version < 2022041900
                ? legacy_question_bank::class
                : versioned_question_bank::class;
        }
        return self::$driver;
    }

    /**
     * category_driver
     *
     * @return string
     */
    public static function category_driver(): string {
        // core_question\category_manager arrived after the initial 4.0 question bank redesign.
        return class_exists(\core_question\category_manager::class)
            ? versioned_question_bank::class : legacy_question_bank::class;
    }
}
