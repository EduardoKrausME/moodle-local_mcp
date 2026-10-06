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
 * authenticated_identity.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\security;

/**
 * Class authenticated_identity.
 */
final class authenticated_identity {
    /**
     * Method __construct.
     *
     * @param int $userid Parameter userid.
     * @param string $type Parameter type.
     * @param array $scopes Parameter scopes.
     * @param ?int $clientid Parameter clientid.
     * @param ?int $connectionid Parameter connectionid.
     * @param ?int $tokenid Parameter tokenid.
     * @param ?string $family Parameter family.
     */
    public function __construct(
        public readonly int     $userid,
        public readonly string  $type,
        public readonly array   $scopes,
        public readonly ?int    $clientid = null,
        public readonly ?int    $connectionid = null,
        public readonly ?int    $tokenid = null,
        public readonly ?string $family = null,
    ) {
    }

    /**
     * Method has_scope.
     *
     * @param string $scope Parameter scope.
     * @return bool Return value.
     */
    public function has_scope(string $scope): bool {
        return in_array($scope, $this->scopes, true);
    }
}
