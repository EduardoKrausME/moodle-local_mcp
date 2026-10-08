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
 * create_discussion.php
 *
 * @package   mcptool_forum
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_forum\write;

use context;
use context_module;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use local_mcp\write\tool\base_tool;
use moodle_url;

/**
 * Create a new topic in a standard forum after an explicit confirmation.
 */
final class create_discussion extends base_tool {
    /** @return string */
    public function get_name(): string {
        return 'forum_create_discussion';
    }

    /** @return string */
    public function get_title(): string {
        return 'Forum: create discussion';
    }

    /** @return string */
    public function get_description(): string {
        return 'Create a new topic in a standard forum after an explicit confirmation.';
    }

    /** @return string */
    public function get_required_capability(): string {
        return 'mod/forum:startdiscussion';
    }

    /** @return array */
    public function get_input_schema(): array {
        return $this->object_schema(['cmid' => ['type' => 'integer', 'minimum' => 1], 'subject' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255], 'message' => ['type' => 'string', 'minLength' => 1], 'groupid' => ['type' => 'integer', 'minimum' => -1]], ['cmid', 'subject', 'message']);
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
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        $cm = get_coursemodule_from_id('forum', (int)$arguments['cmid'], 0, false, MUST_EXIST);
        $forum = $DB->get_record('forum', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = context_module::instance($cm->id);
        $groupid = array_key_exists('groupid', $arguments) ? (int)$arguments['groupid']
            : (int)groups_get_activity_group($cm, true);
        if (!forum_user_can_post_discussion($forum, $groupid, -1, $cm, $context)) {
            throw new api_exception('permission_denied', 403);
        }
        $discussion = (object)[
            'forum' => $forum->id, 'course' => $forum->course,
            'name' => clean_param($arguments['subject'], PARAM_TEXT),
            'message' => clean_text($arguments['message'], FORMAT_HTML),
            'messageformat' => FORMAT_HTML, 'messagetrust' => 0,
            'groupid' => $groupid, 'mailnow' => 0, 'timestart' => 0, 'timeend' => 0, 'pinned' => 0,
        ];
        $id = forum_add_discussion($discussion, null, null, $identity->userid);
        return ['discussionid' => (int)$id,
            'url' => (new moodle_url('/mod/forum/discuss.php', ['d' => $id]))->out(false)];

    }
}
