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
 * Stream OAuth clients to identify ChatGPT connections in audit reports.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\audit;

use local_mcp\oauth\connection_service;

/**
 * Looks up ChatGPT OAuth clients without buffering the client table.
 */
final class chatgpt_client_lookup {
    /**
     * Return the IDs of clients whose redirect URIs belong to ChatGPT.
     *
     * Client names are user-editable and cannot be used for identification.
     *
     * @return int[] ChatGPT OAuth client record IDs.
     */
    public static function get_client_ids(): array {
        global $DB;

        $clientids = [];
        $clients = $DB->get_recordset("local_mcp_oauth_client", null, "", "id, redirecturis");
        try {
            foreach ($clients as $client) {
                $redirects = json_decode($client->redirecturis, true);
                if (!is_array($redirects)) {
                    continue;
                }
                foreach ($redirects as $redirect) {
                    if (is_string($redirect) && connection_service::is_chatgpt_redirect_uri($redirect)) {
                        $clientids[] = (int)$client->id;
                        break;
                    }
                }
            }
        } finally {
            $clients->close();
        }

        return $clientids;
    }
}
