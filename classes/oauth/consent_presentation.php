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
 * OAuth consent screen identity data derived from the actual callback.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\oauth;

use stdClass;

/**
 * Prepare the client identity details presented during OAuth authorization.
 */
final class consent_presentation {
    /**
     * Use the validated redirect URI rather than untrusted client metadata.
     *
     * @param stdClass $client Registered OAuth client.
     * @param string $redirecturi Redirect URI already validated against the client registration.
     * @return array Consent identity details.
     */
    public static function details(stdClass $client, string $redirecturi): array {
        $parts = parse_url($redirecturi);
        $origin = "";
        if (is_array($parts) && isset($parts["scheme"], $parts["host"])) {
            $origin = $parts["scheme"] . "://" . $parts["host"];
            if (isset($parts["port"])) {
                $origin .= ":" . $parts["port"];
            }
        }

        return [
            "origin" => $origin,
            "redirecturi" => $redirecturi,
            "unverified" => !empty($client->dynamic),
        ];
    }
}
