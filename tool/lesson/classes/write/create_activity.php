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
 * Create a Moodle Lesson activity.
 *
 * @package   mcptool_lesson
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_lesson\write;

use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\write\tool\activity_create_base;
use stdClass;

/**
 * Create a real Lesson with conservative defaults.
 */
final class create_activity extends activity_create_base {
    protected function module_name(): string {
        return "lesson";
    }

    protected function extra_properties(): array {
        return [
            "grade" => ["type" => "integer", "minimum" => 0, "maximum" => 100],
            "maxanswers" => ["type" => "integer", "minimum" => 2, "maximum" => 10],
            "available" => ["type" => "integer", "minimum" => 0],
            "deadline" => ["type" => "integer", "minimum" => 0],
        ];
    }

    protected function configure(stdClass $info, array $a, authenticated_identity $identity): void {
        $start = (int)($a["available"] ?? 0);
        $end = (int)($a["deadline"] ?? 0);
        if ($start && $end && $end <= $start) {
            throw new api_exception("invalid_lesson_dates", 400);
        }
        $info->introeditor = ["text" => $info->intro, "format" => FORMAT_HTML, "itemid" => 0];
        $info->grade = (int)($a["grade"] ?? 100);
        $info->maxanswers = (int)($a["maxanswers"] ?? 5);
        $info->available = $start;
        $info->deadline = $end;
        $info->mediafile = 0;
        $info->practice = 0;
        $info->custom = 0;
        $info->review = 1;
        $info->maxattempts = 1;
        $info->retake = 0;
        $info->usemaxgrade = 0;
        $info->progressbar = 1;
        $info->displayleft = 0;
        $info->slideshow = 0;
        $info->timelimit = 0;
        $info->minquestions = 0;
    }

    public function preview(array $arguments, authenticated_identity $identity): array {
        $preview = parent::preview($arguments, $identity);
        $preview["maxanswers"] = (int)($arguments["maxanswers"] ?? 5);
        $preview["grade"] = (int)($arguments["grade"] ?? 100);
        return $preview;
    }
}
