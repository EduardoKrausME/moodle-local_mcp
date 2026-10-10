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
 * Create a resource course module.
 *
 * @package   mcptool_resource
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_resource\write;

use local_mcp\security\authenticated_identity;
use local_mcp\write\tool\activity_create_base;
use stdClass;
use local_mcp\course\activity_upload;

/** Moodle activity creation via standard module hooks. */
final class create_activity extends activity_create_base {
    protected function module_name(): string { return 'resource'; }
    protected function extra_properties(): array {
        return [
            'filename' => ['type' => 'string', 'minLength' => 1],
            'file_base64' => ['type' => 'string', 'minLength' => 1,
                'description' => 'Base64 file bytes or a Base64 data URI; up to 40 MiB decoded.'],
        ];
    }
    protected function extra_required(): array { return ['filename', 'file_base64']; }
    protected function configure(stdClass $info, array $a, authenticated_identity $identity): void {
        global $CFG;
        require_once($CFG->libdir . '/resourcelib.php');
        $info->display = RESOURCELIB_DISPLAY_AUTO;
        $info->printintro = 0;
        $info->showsize = 1;
        $info->files = activity_upload::create_draft($a['file_base64'], $a['filename'], $identity->userid);
    }
}
