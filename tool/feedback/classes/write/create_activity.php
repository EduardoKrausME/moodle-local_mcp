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
 * Create a native Moodle Feedback activity.
 *
 * @package   mcptool_feedback
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_feedback\write;

use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\write\tool\activity_create_base;
use stdClass;

/**
 * Create a feedback survey without publishing student answers.
 */
final class create_activity extends activity_create_base {
    /**
     * Method module_name.
     *
     * @return string Return value.
     */
    protected function module_name(): string {
        return "feedback";
    }

    /**
     * Method extra_properties.
     *
     * @return array Return value.
     */
    protected function extra_properties(): array {
        return [
            "anonymous" => ["type" => "boolean"],
            "multiple_submit" => ["type" => "boolean"],
            "timeopen" => ["type" => "integer", "minimum" => 0],
            "timeclose" => ["type" => "integer", "minimum" => 0],
        ];
    }

    /**
     * Method configure.
     *
     * @param stdClass $info Parameter info.
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return void Return value.
     */
    protected function configure(stdClass $info, array $arguments, authenticated_identity $identity): void {
        global $CFG;
        require_once($CFG->dirroot . "/mod/feedback/lib.php");
        $open = (int)($arguments["timeopen"] ?? 0);
        $close = (int)($arguments["timeclose"] ?? 0);
        if ($open && $close && $close <= $open) {
            throw new api_exception("feedback_invalid_dates", 400);
        }
        $info->timeopen = $open;
        $info->timeclose = $close;
        $info->anonymous = !empty($arguments["anonymous"]) ? FEEDBACK_ANONYMOUS_YES : FEEDBACK_ANONYMOUS_NO;
        $info->multiple_submit = !empty($arguments["multiple_submit"]) ? 1 : 0;
        $info->publish_stats = 0;
        $info->email_notification = 0;
        $info->autonumbering = 1;
        $info->site_after_submit = "";
        $info->page_after_submit = "";
        $info->page_after_submitformat = FORMAT_HTML;
        $info->page_after_submit_editor = ["text" => "", "format" => FORMAT_HTML, "itemid" => 0];
        $info->introeditor = ["text" => $info->intro, "format" => FORMAT_HTML, "itemid" => 0];
    }

    /**
     * Method preview.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        $preview = parent::preview($arguments, $identity);
        $preview["anonymous"] = !empty($arguments["anonymous"]);
        $preview["multiple_submit"] = !empty($arguments["multiple_submit"]);
        return $preview;
    }
}
