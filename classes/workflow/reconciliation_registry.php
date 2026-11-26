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
 * Allowlisted workflow reconciliation verifiers.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\workflow;

use local_mcp\workflow\verifier\activity_completion;
use local_mcp\workflow\verifier\verifier_interface;

/** Only explicitly implemented operations can be reconciled. */
final class reconciliation_registry {
    /**
     * Resolve a trusted condition type.
     *
     * @param string $type Condition type.
     * @return verifier_interface|null
     */
    public static function get(string $type): ?verifier_interface {
        return $type === "activity_completion" ? new activity_completion() : null;
    }
}
