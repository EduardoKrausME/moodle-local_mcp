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
 * list_discussions.php
 *
 * @package   mcptool_forum
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_forum\read;

/**
 * List the visible discussions in a Moodle forum.
 */
final class list_discussions extends \local_mcp\read\tool\base_tool {
    /** @return string */
    public function get_name(): string {
        return 'forum_list_discussions';
    }

    /** @return string */
    public function get_title(): string {
        return 'Forum: list discussions';
    }

    /** @return string */
    public function get_description(): string {
        return 'List the visible discussions in a Moodle forum.';
    }

    /** @return string */
    public function get_required_capability(): string {
        return 'mod/forum:viewdiscussion';
    }

    /** @return array */
    public function get_input_schema(): array {
        return $this->object_schema(['cmid' => ['type' => 'integer', 'minimum' => 1], 'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]], ['cmid']);
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
        global $DB, $CFG, $USER;
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        $cm = get_coursemodule_from_id('forum', (int)$arguments['cmid'], 0, false, MUST_EXIST);
        $forum = $DB->get_record('forum', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);
        if (!get_fast_modinfo($cm->course, $identity->userid)->get_cm($cm->id)->uservisible) {
            throw new \local_mcp\exception\api_exception('permission_denied', 403);
        }
        $limit = min(100, max(1, (int)($arguments['limit'] ?? 25)));
        // Filter against Moodle's per-discussion access rules, including groups and timed discussions.
        $records = $DB->get_records('forum_discussions', ['forum' => $forum->id], 'timemodified DESC', '*', 0, 500);
        $rows = [];
        foreach ($records as $discussion) {
            if (!forum_user_can_see_discussion($forum, $discussion, $context, $USER)) {
                continue;
            }
            $rows[] = [
                'id' => (int)$discussion->id,
                'title' => $discussion->name,
                'created' => (int)$discussion->timemodified,
                'userid' => (int)$discussion->userid,
                'url' => (new \moodle_url('/mod/forum/discuss.php', ['d' => $discussion->id]))->out(false),
            ];
            if (count($rows) >= $limit) {
                break;
            }
        }
        return ['cmid' => (int)$cm->id, 'forumid' => (int)$forum->id, 'discussions' => $rows];

    }
}
