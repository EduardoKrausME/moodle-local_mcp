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
 * update_content.php
 *
 * @package   mcptool_page
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_page\write;

use context;
use context_module;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\write\tool\base_tool;

/**
 * Replace the HTML content of a Moodle Page using its activity API after user confirmation.
 */
final class update_content extends base_tool {
    /** @return string */
    public function get_name(): string {
        return 'page_update_content';
    }

    /** @return string */
    public function get_title(): string {
        return 'Page: update content';
    }

    /** @return string */
    public function get_description(): string {
        return 'Replace the HTML content of a Moodle Page using its activity API after user confirmation.';
    }

    /** @return string */
    public function get_required_capability(): string {
        return 'moodle/course:manageactivities';
    }

    /** @return array */
    public function get_input_schema(): array {
        return $this->object_schema(['cmid' => ['type' => 'integer', 'minimum' => 1], 'content' => ['type' => 'string'], 'expected_revision' => ['type' => 'integer', 'minimum' => 0]], ['cmid', 'content', 'expected_revision']);
    }

    /**
     * @param array $arguments Tool input.
     * @return context
     */
    public function resolve_context(array $arguments): context {
        return context_module::instance((int)$arguments['cmid'], MUST_EXIST);
    }

    /**
     * @param array $arguments Tool input.
     * @param authenticated_identity $identity OAuth identity.
     * @return array
     */
    public function execute(array $arguments,
            authenticated_identity $identity): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/page/lib.php');
        $cm = get_coursemodule_from_id('page', (int)$arguments['cmid'], 0, false, MUST_EXIST);
        $page = $DB->get_record('page', ['id' => $cm->instance], '*', MUST_EXIST);
        if ((int)$page->revision !== (int)$arguments['expected_revision']) {
            throw new api_exception('revision_conflict', 409);
        }
        $page->coursemodule = (int)$cm->id;
        $page->instance = (int)$page->id;
        $page->page = ['itemid' => 0, 'text' => clean_text($arguments['content'], FORMAT_HTML),
            'format' => FORMAT_HTML];
        $page->completionexpected = 0;
        page_update_instance($page, null);
        rebuild_course_cache($cm->course, true);
        return ['updated' => true, 'cmid' => (int)$cm->id, 'revision' => (int)$page->revision];

    }
}
