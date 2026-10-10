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
 * Common Moodle course-module creation plumbing.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;

use context;
use context_course;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use moodle_url;
use stdClass;

/**
 * Create course modules through the supported Moodle add_moduleinfo API.
 * Each subplugin supplies its own input schema and module-specific fields.
 */
abstract class activity_create_base extends base_tool {
    /** @return string Native Moodle module name, without mod_. */
    abstract protected function module_name(): string;

    /** @return array Additional module-specific properties. */
    abstract protected function extra_properties(): array;

    /** @return array Required module-specific fields. */
    protected function extra_required(): array {
        return [];
    }

    /** @return bool Whether a name must be supplied. */
    protected function requires_name(): bool {
        return true;
    }

    /** @return void Fill activity settings before calling core. */
    abstract protected function configure(stdClass $info, array $arguments, authenticated_identity $identity): void;

    /** @return string Tool name, usually aligned with the native module. */
    protected function tool_prefix(): string {
        return $this->module_name();
    }

    /** @return string */
    public function get_name(): string {
        return $this->tool_prefix() . '_create_activity';
    }

    /** @return string */
    public function get_title(): string {
        return $this->tool_prefix() . ': create activity';
    }

    /** @return string */
    public function get_description(): string {
        return 'Create a ' . $this->module_name() . ' activity in a course section using the Moodle core module API.';
    }

    /** @return string */
    public function get_required_capability(): string {
        return 'moodle/course:manageactivities';
    }

    /** @return array */
    public function get_input_schema(): array {
        $fields = [
            'courseid' => ['type' => 'integer', 'minimum' => 1],
            'sectionnum' => ['type' => 'integer', 'minimum' => 0],
            'name' => ['type' => 'string', 'minLength' => 1],
            'intro' => ['type' => 'string'],
        ] + $this->extra_properties();
        $required = ['courseid', 'sectionnum'];
        if ($this->requires_name()) {
            $required[] = 'name';
        }
        return $this->object_schema($fields, array_merge($required, $this->extra_required()));
    }

    /** @return context */
    public function resolve_context(array $arguments): context {
        return context_course::instance((int)$arguments['courseid'], MUST_EXIST);
    }

    /**
     * Do not include large uploaded payloads in previews or logs.
     *
     * @param array $arguments Input data.
     * @param authenticated_identity $identity Acting user.
     * @return array Safe metadata.
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        $this->check_module_capability($arguments, $identity);
        return [
            'operation' => $this->get_name(),
            'courseid' => (int)$arguments['courseid'],
            'sectionnum' => (int)$arguments['sectionnum'],
            'name' => clean_param($arguments['name'] ?? '', PARAM_TEXT),
            'module' => $this->module_name(),
            'filename' => isset($arguments['filename'])
                ? clean_param($arguments['filename'], PARAM_FILE) : null,
            'upload_bytes_estimate' => isset($arguments['file_base64'])
                ? (int)(strlen($arguments['file_base64']) * 3 / 4) : null,
        ];
    }

    /**
     * @param array $arguments Input data.
     * @param authenticated_identity $identity Acting user.
     * @return void
     */
    private function check_module_capability(array $arguments, authenticated_identity $identity): void {
        $module = $this->module_name();
        $context = $this->resolve_context($arguments);
        if (!has_capability('mod/' . $module . ':addinstance', $context, $identity->userid)) {
            throw new api_exception('permission_denied', 403);
        }
    }

    /** @return array Newly created activity identifiers. */
    public function execute(array $arguments, authenticated_identity $identity): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/modlib.php');
        $course = get_course((int)$arguments['courseid']);
        $this->check_module_capability($arguments, $identity);
        [$module, $context, $section, $cm, $info] = prepare_new_moduleinfo_data(
            $course, $this->module_name(), (int)$arguments['sectionnum']
        );
        $info->name = clean_param($arguments['name'] ?? '', PARAM_TEXT);
        $info->intro = clean_text($arguments['intro'] ?? '', FORMAT_HTML);
        $info->introformat = FORMAT_HTML;
        $this->configure($info, $arguments, $identity);
        $result = add_moduleinfo($info, $course);
        $moduleurl = $this->module_name() === 'label'
            ? new moodle_url('/course/view.php', ['id' => $course->id])
            : new moodle_url('/mod/' . $this->module_name() . '/view.php', [
                'id' => $result->coursemodule,
            ]);
        if ($this->module_name() === 'label') {
            $moduleurl->set_anchor('module-' . $result->coursemodule);
        }
        return [
            'cmid' => (int)$result->coursemodule,
            'instanceid' => (int)$result->instance,
            'courseid' => (int)$course->id,
            'module' => $this->module_name(),
            'url' => $moduleurl->out(false),
        ];
    }
}
