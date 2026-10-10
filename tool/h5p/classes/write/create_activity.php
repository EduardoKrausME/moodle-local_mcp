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
 * Create a h5p course module.
 *
 * @package   mcptool_h5p
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_h5p\write;

use local_mcp\security\authenticated_identity;
use local_mcp\write\tool\activity_create_base;
use stdClass;
use local_mcp\course\activity_upload;

/** Moodle activity creation via standard module hooks. */
final class create_activity extends activity_create_base {
    protected function module_name(): string { return 'h5pactivity'; }
    protected function tool_prefix(): string { return 'h5p'; }
    protected function extra_properties(): array {
        return [
            'filename' => ['type' => 'string', 'minLength' => 5, 'description' => 'H5P package filename.'],
            'file_base64' => ['type' => 'string', 'minLength' => 1, 'description' => 'Base64 .h5p ZIP package, max 40 MiB.'],
        ];
    }
    protected function extra_required(): array { return ['filename', 'file_base64']; }
    protected function configure(stdClass $info, array $a, authenticated_identity $identity): void {
        global $CFG;
        $info->packagefile = activity_upload::create_draft(
            $a['file_base64'], $a['filename'], $identity->userid, true
        );
        $info->reference = $a['filename'];
        $factory = new \core_h5p\factory();
        $core = $factory->get_core();
        $config = \core_h5p\helper::decode_display_options($core);
        $info->displayoptions = \core_h5p\helper::get_display_options($core, $config);
        $info->enabletracking = 1;
        $info->grademethod = (int)(get_config('h5pactivity', 'grademethod') ?: 1);
        $info->reviewmode = (int)(get_config('h5pactivity', 'reviewmode') ?: 0);
        $info->grade = $CFG->gradepointdefault;
    }
}
