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
    public int $userid;

    public string $type;

    public array $scopes;

    public ?int $clientid;

    public ?int $connectionid;

    public ?int $tokenid;

    public ?string $family;

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
        int $userid,
        string $type,
        array $scopes,
        ?int $clientid = null,
        ?int $connectionid = null,
        ?int $tokenid = null,
        ?string $family = null
    ) {
        $this->userid = $userid;
        $this->type = $type;
        $this->scopes = $scopes;
        $this->clientid = $clientid;
        $this->connectionid = $connectionid;
        $this->tokenid = $tokenid;
        $this->family = $family;
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
