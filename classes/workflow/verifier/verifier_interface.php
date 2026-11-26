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
 * Contract for read-only workflow postcondition verifiers.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\workflow\verifier;

use context;
use local_mcp\security\authenticated_identity;

/** A verifier never executes a Moodle operation. */
interface verifier_interface {
    /**
     * Capture a bounded condition from an official server preview, or decline.
     *
     * @param array $arguments Original arguments.
     * @param array $preview Server preview.
     * @param authenticated_identity $identity Connection.
     * @param context $context Target context.
     * @return array|null
     */
    public function capture(array $arguments, array $preview, authenticated_identity $identity, context $context): ?array;

    /**
     * Read and verify the actual target state and original operation authorization.
     *
     * @param array $condition Stored condition.
     * @param authenticated_identity $identity Connection.
     * @param int $courseid Expected course.
     * @return array
     */
    public function verify(array $condition, authenticated_identity $identity, int $courseid): array;
}
