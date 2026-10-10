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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Create a url course module.
 *
 * @package   mcptool_url
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_url\write;

use local_mcp\security\authenticated_identity;
use local_mcp\write\tool\activity_create_base;
use stdClass;

/** Moodle activity creation via standard module hooks. */
final class create_activity extends activity_create_base {
    protected function module_name(): string { return 'url'; }
    protected function extra_properties(): array {
        return ['externalurl' => ['type' => 'string', 'format' => 'uri', 'minLength' => 1]];
    }
    protected function extra_required(): array { return ['externalurl']; }
    protected function configure(stdClass $info, array $a, authenticated_identity $identity): void {
        global $CFG;
        require_once($CFG->libdir . '/resourcelib.php');
        $url = trim($a['externalurl']);
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)),
                ['http', 'https'], true)) {
            throw new \local_mcp\exception\api_exception('invalid_url', 400);
        }
        $info->externalurl = $url;
        $info->display = RESOURCELIB_DISPLAY_AUTO;
        $info->printintro = 0;
    }
}
