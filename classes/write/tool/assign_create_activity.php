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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Safely create real Assignment modules with submission and grading.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;

use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use stdClass;

/**
 * Uses the Moodle module API, not direct database writes.
 */
final class assign_create_activity extends activity_create_base {
    protected function module_name(): string {
        return 'assign';
    }

    protected function extra_properties(): array {
        return [
            'grade' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
            'duedate' => ['type' => 'integer', 'minimum' => 0],
            'allowsubmissionsfromdate' => ['type' => 'integer', 'minimum' => 0],
            'cutoffdate' => ['type' => 'integer', 'minimum' => 0],
            'file_enabled' => ['type' => 'boolean'],
            'onlinetext_enabled' => ['type' => 'boolean'],
            'maxfiles' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
        ];
    }

    public function get_description(): string {
        return 'Create a functional Moodle Assignment with file and/or online text submission, teacher grading, '
            . 'and optional dates. Checks permissions and rejects duplicate names in the same section.';
    }

    public function preview(array $arguments, authenticated_identity $identity): array {
        $preview = parent::preview($arguments, $identity);
        $this->check_options($arguments);
        $preview['grade'] = (float)($arguments['grade'] ?? 100);
        $preview['file_enabled'] = (bool)($arguments['file_enabled'] ?? true);
        $preview['onlinetext_enabled'] = (bool)($arguments['onlinetext_enabled'] ?? false);
        $preview['duedate'] = (int)($arguments['duedate'] ?? 0);
        return $preview;
    }

    private function check_options(array $arguments): void {
        $this->check_submission_options($arguments);
        $this->check_dates($arguments);
    }

    private function check_submission_options(array $arguments): void {
        $files = (bool)($arguments['file_enabled'] ?? true);
        $online = (bool)($arguments['onlinetext_enabled'] ?? false);
        if (!$files && !$online) {
            throw new api_exception('assignment_submission_required', 400);
        }
    }

    private function check_dates(array $arguments): void {
        $start = (int)($arguments['allowsubmissionsfromdate'] ?? 0);
        $due = (int)($arguments['duedate'] ?? 0);
        $cutoff = (int)($arguments['cutoffdate'] ?? 0);
        if (($start && $due && $due < $start) || ($due && $cutoff && $cutoff < $due)
            || ($start && $cutoff && $cutoff < $start)) {
            throw new api_exception('invalid_assignment_dates', 400);
        }
    }

    protected function configure(stdClass $info, array $arguments, authenticated_identity $identity): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/lib.php');
        $this->check_options($arguments);

        // Retry safety: never create two assignments with the same name in this course section.
        $modinfo = get_fast_modinfo((int)$arguments['courseid']);
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->modname === 'assign' && (int)$cm->sectionnum === (int)$arguments['sectionnum']
                && $cm->name === $info->name) {
                throw new api_exception('assignment_already_exists', 409);
            }
        }

        $info->grade = (float)($arguments['grade'] ?? 100);
        $info->allowsubmissionsfromdate = (int)($arguments['allowsubmissionsfromdate'] ?? 0);
        $info->duedate = (int)($arguments['duedate'] ?? 0);
        $info->cutoffdate = (int)($arguments['cutoffdate'] ?? 0);
        $info->gradingduedate = 0;
        $info->alwaysshowdescription = 1;
        $info->submissiondrafts = 0;
        $info->requiresubmissionstatement = 0;
        $info->sendnotifications = 0;
        $info->sendlatenotifications = 0;
        $info->teamsubmission = 0;
        $info->requireallteammemberssubmit = 0;
        $info->teamsubmissiongroupingid = 0;
        $info->blindmarking = 0;
        $info->markingworkflow = 0;
        $info->markingallocation = 0;
        $info->attemptreopenmethod = 'none';
        $info->maxattempts = 1;

        // Submission and feedback plugin settings expected by mod_assign::add_instance().
        $info->assignsubmission_file_enabled = (int)($arguments['file_enabled'] ?? true);
        $info->assignsubmission_file_maxfiles = (int)($arguments['maxfiles'] ?? 1);
        $info->assignsubmission_file_maxsizebytes = 0;
        $info->assignsubmission_file_filetypes = '';
        $info->assignsubmission_onlinetext_enabled = (int)($arguments['onlinetext_enabled'] ?? false);
        $info->assignsubmission_onlinetext_wordlimit_enabled = 0;
        $info->assignsubmission_onlinetext_wordlimit = 0;
        $info->assignfeedback_comments_enabled = 1;
        $info->assignfeedback_file_enabled = 0;
    }
}
