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
 * Create a label course module.
 *
 * @package   mcptool_label
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_label\write;

use local_mcp\security\authenticated_identity;
use local_mcp\write\tool\activity_create_base;
use stdClass;

/** Moodle activity creation via standard module hooks. */
final class create_activity extends activity_create_base {
    protected function module_name(): string { return 'label'; }
    protected function requires_name(): bool { return false; }
    protected function extra_properties(): array {
        return ['content' => ['type' => 'string', 'minLength' => 1,
            'description' => 'HTML content displayed in the course section.']];
    }
    protected function extra_required(): array { return ['content']; }
    protected function configure(stdClass $info, array $a, authenticated_identity $identity): void {
        // Moodle mod_label derives its activity name from the HTML introduction.
        $info->intro = clean_text($a['content'], FORMAT_HTML);
        $info->introformat = FORMAT_HTML;
    }
}
