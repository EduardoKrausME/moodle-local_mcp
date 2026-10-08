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
 * get_content.php
 *
 * @package   mcptool_page
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_page\read;

/**
 * Read the text and formatting of a Moodle Page resource.
 */
final class get_content extends \local_mcp\read\tool\base_tool {
    /** @return string */
    public function get_name(): string {
        return 'page_get_content';
    }

    /** @return string */
    public function get_title(): string {
        return 'Page: read content';
    }

    /** @return string */
    public function get_description(): string {
        return 'Read the text and formatting of a Moodle Page resource.';
    }

    /** @return string */
    public function get_required_capability(): string {
        return 'mod/page:view';
    }

    /** @return array */
    public function get_input_schema(): array {
        return $this->object_schema(['cmid' => ['type' => 'integer', 'minimum' => 1]], ['cmid']);
    }

    /**
     * @param array $arguments Tool input.
     * @return \context
     */
    public function resolve_context(array $arguments): \context {
        return \context_module::instance((int)$arguments['cmid'], MUST_EXIST);
    }

    /**
     * @param array $arguments Tool input.
     * @param \local_mcp\security\authenticated_identity $identity OAuth identity.
     * @return array
     */
    public function execute(array $arguments,
            \local_mcp\security\authenticated_identity $identity): array {
        global $DB;
        $cm = get_coursemodule_from_id('page', (int)$arguments['cmid'], 0, false, MUST_EXIST);
        $page = $DB->get_record('page', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);
        if (!get_fast_modinfo($cm->course, $identity->userid)->get_cm($cm->id)->uservisible) {
            throw new \local_mcp\exception\api_exception('permission_denied', 403);
        }
        return [
            'cmid' => (int)$cm->id,
            'name' => $page->name,
            'content' => format_text($page->content, $page->contentformat,
                ['context' => $context, 'filter' => true]),
            'revision' => (int)$page->revision,
            'url' => (new \moodle_url('/mod/page/view.php', ['id' => $cm->id]))->out(false),
        ];

    }
}
