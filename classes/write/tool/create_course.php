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
 * create_course.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;

use context;
use context_coursecat;
use local_mcp\security\authenticated_identity;
use local_mcp\course\image_service;
use local_mcp\exception\api_exception;

/**
 * Class create_course.
 */
final class create_course extends base_tool {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string {
        return 'create_course';
    }

    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string {
        return 'Create course';
    }

    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string {
        return 'Create a Moodle course with full name, short name, optional idnumber, HTML summary, visibility, '
            . 'and optional course image supplied as Base64 or an HTTPS URL.';
    }

    /**
     * Method get_required_capability.
     *
     * @return string Return value.
     */
    public function get_required_capability(): string {
        return 'moodle/course:create';
    }

    /**
     * Method get_input_schema.
     *
     * @return array Return value.
     */
    public function get_input_schema(): array {
        return $this->object_schema([
            'fullname' => ['type' => 'string', 'minLength' => 1],
            'shortname' => ['type' => 'string', 'minLength' => 1],
            'categoryid' => ['type' => 'integer', 'minimum' => 1],
            'idnumber' => ['type' => 'string', 'description' => 'Optional course ID number/code.'],
            'summary' => ['type' => 'string', 'description' => 'Course description in HTML, stored with FORMAT_HTML.'],
            'summaryformat' => ['type' => 'integer', 'enum' => [1],
                'description' => 'Course summary format; 1 = HTML.'],
            'visible' => ['type' => 'boolean'],
            'image_base64' => ['type' => 'string',
                'description' => 'Optional Base64 PNG/JPEG/WebP or data URI of a local image, max 5 MiB decoded.'],
            'image_url' => ['type' => 'string',
                'description' => 'Optional publicly downloadable HTTPS image URL. Use exactly one image source.'],
        ], ['fullname', 'shortname', 'categoryid']);
    }


    /**
     * Method resolve_context.
     *
     * @param array $arguments Parameter arguments.
     * @return context Return value.
     */
    public function resolve_context(array $arguments): context {
        return context_coursecat::instance((int)$arguments['categoryid']);
    }

    /**
     * Method execute.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    /**
     * Safe preview that never returns the Base64 image to ChatGPT.
     *
     * @param array $arguments Arguments for the new course.
     * @param authenticated_identity $identity Authenticated administrator.
     * @return array Preview data.
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        $this->validate_image_source($arguments);
        $this->validate_summary_format($arguments);
        return [
            'fullname' => clean_param((string)$arguments['fullname'], PARAM_TEXT),
            'shortname' => clean_param((string)$arguments['shortname'], PARAM_TEXT),
            'categoryid' => (int)$arguments['categoryid'],
            'idnumber' => $arguments['idnumber'] ?? null,
            'summary_html_bytes' => isset($arguments['summary']) ? strlen((string)$arguments['summary']) : 0,
            'summaryformat' => (int)($arguments['summaryformat'] ?? FORMAT_HTML),
            'visible' => (bool)($arguments['visible'] ?? true),
            'image_source' => !empty($arguments['image_base64']) ? 'base64'
                : (!empty($arguments['image_url']) ? 'https_url' : null),
        ];
    }

    /**
     * Reject non-HTML formats when handling an HTML summary.
     *
     * @param array $arguments Input values.
     * @return void
     */
    private function validate_summary_format(array $arguments): void {
        if (isset($arguments['summaryformat']) && (int)$arguments['summaryformat'] !== FORMAT_HTML) {
            throw new api_exception('invalid_summaryformat', 400, 'Only summaryformat=1 (HTML) is supported.');
        }
    }

    /**
     * Ensure the course image input can be stored.
     *
     * @param array $arguments Input values.
     * @return void
     */
    private function validate_image_source(array $arguments): void {
        if (array_key_exists('image_base64', $arguments) || array_key_exists('image_url', $arguments)) {
            if (empty($arguments['image_base64']) && empty($arguments['image_url'])) {
                return;
            }
            if (!empty($arguments['image_base64']) && !empty($arguments['image_url'])) {
                throw new api_exception('image_source_required', 400);
            }
        }
    }

    /**
     * Create a fully populated course, including its optional overview image.
     *
     * @param array $arguments New course fields.
     * @param authenticated_identity $identity Authenticated administrator.
     * @return array Created course metadata.
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $this->validate_image_source($arguments);
        $this->validate_summary_format($arguments);
        $imagedata = [];
        if (!empty($arguments['image_base64'])) {
            $imagedata['image_base64'] = $arguments['image_base64'];
        } else if (!empty($arguments['image_url'])) {
            $imagedata['image_url'] = $arguments['image_url'];
        }
        // Verify the supplied bytes or URL before creating a new course.
        if ($imagedata) {
            image_service::decode($imagedata);
        }

        $record = (object)[
            'fullname' => clean_param((string)$arguments['fullname'], PARAM_TEXT),
            'shortname' => clean_param((string)$arguments['shortname'], PARAM_TEXT),
            'category' => (int)$arguments['categoryid'],
            'visible' => array_key_exists('visible', $arguments) ? (int)(bool)$arguments['visible'] : 1,
        ];
        if (array_key_exists('idnumber', $arguments)) {
            $record->idnumber = clean_param((string)$arguments['idnumber'], PARAM_TEXT);
        }
        if (array_key_exists('summary', $arguments)) {
            $record->summary = clean_param((string)$arguments['summary'], PARAM_CLEANHTML);
        }
        if (array_key_exists('summary', $arguments) || array_key_exists('summaryformat', $arguments)) {
            $record->summaryformat = FORMAT_HTML;
        }

        $transaction = $DB->start_delegated_transaction();
        $course = create_course($record);
        $image = null;
        if ($imagedata) {
            $image = image_service::replace((int)$course->id, $imagedata, $identity->userid)['image'];
        }
        $transaction->allow_commit();
        return [
            'id' => (int)$course->id,
            'fullname' => $course->fullname,
            'shortname' => $course->shortname,
            'categoryid' => (int)$course->category,
            'idnumber' => $course->idnumber,
            'summary' => $course->summary,
            'summaryformat' => (int)$course->summaryformat,
            'visible' => (bool)$course->visible,
            'image' => $image,
        ];
    }
}
