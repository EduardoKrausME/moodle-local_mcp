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
 * Stable question bank API for MCP tools on Moodle 3.10 and newer.
 *
 * @package local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_mcp\question;

use context;
use local_mcp\security\authenticated_identity;
use stdClass;

/**
 * Delegates storage operations to the version-specific question bank.
 */
final class bank_service {
    public static function category_context(int $categoryid): context {
        $driver = question_bank_factory::driver();
        return $driver::category_context($categoryid);
    }

    public static function row(int $questionid): stdClass {
        $driver = question_bank_factory::driver();
        return $driver::row($questionid);
    }

    public static function question_context(int $id): context {
        $driver = question_bank_factory::driver();
        return $driver::question_context($id);
    }

    public static function authorize(stdClass $q, authenticated_identity $identity, bool $edit = false): void {
        $driver = question_bank_factory::driver();
        $driver::authorize($q, $identity, $edit);
    }

    public static function banks(int $courseid, authenticated_identity $identity): array {
        $driver = question_bank_factory::driver();
        return $driver::banks($courseid, $identity);
    }

    public static function categories(int $contextid): array {
        $driver = question_bank_factory::driver();
        return $driver::categories($contextid);
    }

    public static function search(array $a, authenticated_identity $identity): array {
        $driver = question_bank_factory::driver();
        return $driver::search($a, $identity);
    }

    public static function details(int $id, authenticated_identity $identity): array {
        $driver = question_bank_factory::driver();
        return $driver::details($id, $identity);
    }

    public static function form(array $a, string $qtype): stdClass {
        $driver = question_bank_factory::driver();
        return $driver::form($a, $qtype);
    }

    public static function save(array $a, authenticated_identity $identity, ?int $oldid = null): array {
        $driver = question_bank_factory::driver();
        return $driver::save($a, $identity, $oldid);
    }

    public static function validate_category(array $a, authenticated_identity $identity): array {
        $driver = question_bank_factory::category_driver();
        return $driver::validate_category($a, $identity);
    }

    public static function name_exists(int $categoryid, string $name): bool {
        $driver = question_bank_factory::driver();
        return $driver::name_exists($categoryid, $name);
    }

    public static function create_category(array $a, authenticated_identity $identity): array {
        $driver = question_bank_factory::category_driver();
        return $driver::create_category($a, $identity);
    }
}
