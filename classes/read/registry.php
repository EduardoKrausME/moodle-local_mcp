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

namespace local_mcp\read;

use local_mcp\read\tool\get_course_completion_config;
use local_mcp\extension\manager;
use local_mcp\extension\tool_provider_loader;
use local_mcp\read\tool\get_gradebook_structure;
use local_mcp\read\tool\get_grade_history;
use local_mcp\read\tool\get_course_completion_criteria;
use local_mcp\read\tool\get_course_completion_status;
use local_mcp\read\tool\get_course_progress_report;
use local_mcp\read\tool\get_course_groups;
use local_mcp\read\tool\get_group_members;
use local_mcp\read\tool\get_activity_restrictions;
use local_mcp\read\tool\get_activity_completion;
use local_mcp\read\tool\get_question;
use local_mcp\read\tool\search_questions;
use local_mcp\read\tool\list_question_categories;
use local_mcp\read\tool\list_question_banks;
use local_mcp\read\tool\get_assignment;
use local_mcp\read\tool\get_assignment_feedback_context;
use local_mcp\read\tool\get_assignment_feedback_queue;
use local_mcp\read\tool\get_assignment_submission_image;
use local_mcp\read\tool\get_assignments;
use local_mcp\read\tool\get_attempts;
use local_mcp\read\tool\get_calendar_events;
use local_mcp\read\tool\get_course;
use local_mcp\read\tool\get_site_config;
use local_mcp\read\tool\get_password_policy;
use local_mcp\read\tool\get_site_status;
use local_mcp\read\tool\get_course_activities;
use local_mcp\read\tool\get_course_contents;
use local_mcp\read\tool\get_course_image;
use local_mcp\read\tool\get_course_participants;
use local_mcp\read\tool\get_course_sections;
use local_mcp\read\tool\get_grades;
use local_mcp\read\tool\get_progress;
use local_mcp\read\tool\get_quiz;
use local_mcp\read\tool\get_quizzes;
use local_mcp\read\tool\get_submissions;
use local_mcp\read\tool\get_user;
use local_mcp\read\tool\get_user_courses;
use local_mcp\read\tool\list_categories;
use local_mcp\read\tool\search_courses;
use local_mcp\read\tool\search_users;

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
            list_question_banks::class,
            list_question_categories::class,
            search_questions::class,
            get_question::class,
            get_assignment::class,
            get_assignment_feedback_context::class,
            get_assignment_feedback_queue::class,
            get_assignment_submission_image::class,
            get_assignments::class,
            get_attempts::class,
            get_calendar_events::class,
            get_course::class,
            get_course_groups::class,
            get_group_members::class,
            get_activity_restrictions::class,
            get_activity_completion::class,
            get_site_config::class,
            get_password_policy::class,
            get_site_status::class,
            get_course_image::class,
            get_course_activities::class,
            get_course_contents::class,
            get_course_participants::class,
            get_course_sections::class,
            get_grades::class,
            get_gradebook_structure::class,
            get_grade_history::class,
            get_course_completion_config::class,
            get_course_completion_criteria::class,
            get_course_completion_status::class,
            get_course_progress_report::class,
            list_categories::class,
            get_progress::class,
            get_quiz::class,
            get_quizzes::class,
            get_submissions::class,
            get_user::class,
            get_user_courses::class,
            search_courses::class,
            search_users::class,
        ];
        $conflicts = [];
        $tools = tool_provider_loader::collect_builtins($classes, tool_interface::class, $conflicts);
        return tool_provider_loader::collect($tools, manager::read_providers(),
            'get_read_tools', tool_interface::class, $conflicts);
    }
}
