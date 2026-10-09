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
 * registry.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write;

use coding_exception;
use local_mcp\extension\manager;
use local_mcp\write\tool\bulk_create_courses;
use local_mcp\write\tool\create_category;
use local_mcp\write\tool\create_course;
use local_mcp\write\tool\create_section;
use local_mcp\write\tool\create_user;
use local_mcp\write\tool\enrol_user;
use local_mcp\write\tool\grade_submission;
use local_mcp\write\tool\purge_all_caches;
use local_mcp\write\tool\purge_css_cache;
use local_mcp\write\tool\send_message;
use local_mcp\write\tool\set_course_image;
use local_mcp\write\tool\set_site_config;
use local_mcp\write\tool\suspend_user;
use local_mcp\write\tool\unenrol_user;
use local_mcp\write\tool\update_category;
use local_mcp\write\tool\update_course;
use local_mcp\write\tool\update_section;
use local_mcp\write\tool\update_user;

/**
 * Class registry.
 */
final class registry {
    /** @return tool_interface[] */
    public static function get_tools(): array {
        $tools = [
            new create_category(),
            new bulk_create_courses(),
            new create_course(),
            new create_section(),
            new create_user(),
            new enrol_user(),
            new grade_submission(),
            new purge_css_cache(),
            new purge_all_caches(),
            new send_message(),
            new suspend_user(),
            new unenrol_user(),
            new update_category(),
            new update_course(),
            new set_course_image(),
            new set_site_config(),
            new update_section(),
            new update_user(),
        ];
        foreach (manager::write_providers() as $provider) {
            foreach ($provider->get_write_tools() as $tool) {
                if (!$tool instanceof tool_interface) {
                    throw new coding_exception('WRITE provider returned a non-WRITE tool.');
                }
                $tools[] = $tool;
            }
        }
        return self::index($tools);
    }

    /**
     * Method index.
     *
     * @param array $tools Parameter tools.
     * @return array Return value.
     */
    private static function index(array $tools): array {
        $indexed = [];
        foreach ($tools as $tool) {
            $name = $tool->get_name();
            if (isset($indexed[$name])) {
                throw new coding_exception('Duplicate MCP WRITE tool: ' . $name);
            }
            $indexed[$name] = $tool;
        }
        return $indexed;
    }
}
