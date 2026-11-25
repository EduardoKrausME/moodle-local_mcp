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

use local_mcp\write\tool\set_course_completion_config;
use local_mcp\write\tool\replace_course_completion_activity;
use local_mcp\extension\manager;
use local_mcp\extension\tool_provider_loader;
use local_mcp\write\tool\workflow_sequence;
use local_mcp\write\tool\set_grade_item_visibility;
use local_mcp\write\tool\copy_activity_images;
use local_mcp\write\tool\create_group;
use local_mcp\write\tool\add_group_members;
use local_mcp\write\tool\remove_group_members;
use local_mcp\write\tool\set_activity_groupmode;
use local_mcp\write\tool\set_activity_restrictions;
use local_mcp\write\tool\set_activity_completion;
use local_mcp\write\tool\update_question;
use local_mcp\write\tool\delete_course_module;
use local_mcp\write\tool\move_activity;
use local_mcp\write\tool\hide_activity;
use local_mcp\write\tool\show_activity;
use local_mcp\write\tool\create_question;
use local_mcp\write\tool\create_question_category;
use local_mcp\write\tool\import_gift_question_bank;
use local_mcp\write\tool\bulk_create_courses;
use local_mcp\write\tool\create_category;
use local_mcp\write\tool\create_course;
use local_mcp\write\tool\create_section;
use local_mcp\write\tool\create_user;
use local_mcp\write\tool\enrol_user;
use local_mcp\write\tool\grade_submission;
use local_mcp\write\tool\save_assignment_feedback;
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

    /**
     * get_tools
     *
     * @param array|null $conflicts Duplicate names excluded from this registry.
     * @return array
     */
    public static function get_tools(?array &$conflicts = null): array {
        $classes = [
            workflow_sequence::class,
            copy_activity_images::class,
            delete_course_module::class,
            move_activity::class,
            hide_activity::class,
            show_activity::class,
            create_question_category::class,
            import_gift_question_bank::class,
            create_question::class,
            update_question::class,
            create_category::class,
            bulk_create_courses::class,
            create_course::class,
            create_group::class,
            add_group_members::class,
            remove_group_members::class,
            set_activity_groupmode::class,
            set_activity_restrictions::class,
            set_activity_completion::class,
            set_course_completion_config::class,
            replace_course_completion_activity::class,
            create_section::class,
            create_user::class,
            enrol_user::class,
            grade_submission::class,
            set_grade_item_visibility::class,
            save_assignment_feedback::class,
            purge_css_cache::class,
            purge_all_caches::class,
            send_message::class,
            suspend_user::class,
            unenrol_user::class,
            update_category::class,
            update_course::class,
            set_course_image::class,
            set_site_config::class,
            update_section::class,
            update_user::class,
        ];
        $conflicts = [];
        $tools = tool_provider_loader::collect_builtins($classes, tool_interface::class, $conflicts);
        return tool_provider_loader::collect($tools, manager::write_providers(),
            'get_write_tools', tool_interface::class, $conflicts);
    }
}
