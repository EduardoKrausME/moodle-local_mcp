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
 * reply_to_post.php
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

/**
 * Reply to an existing discussion post as the authenticated Moodle user.
 */
final class reply_to_post extends base_tool {
    /** @return string */
    public function get_name(): string {
        return 'forum_reply_to_post';
    }

    /** @return string */
    public function get_title(): string {
        return 'Forum: reply to post';
    }

    /** @return string */
    public function get_description(): string {
        return 'Reply to an existing discussion post as the authenticated Moodle user.';
    }

    /** @return string */
    public function get_required_capability(): string {
        return 'mod/forum:replypost';
    }

    /** @return array */
    public function get_input_schema(): array {
        return $this->object_schema(['postid' => ['type' => 'integer', 'minimum' => 1], 'message' => ['type' => 'string', 'minLength' => 1], 'subject' => ['type' => 'string']], ['postid', 'message']);
    }

    /**
     * @param array $arguments Tool input.
     * @return context
     */
    public function resolve_context(array $arguments): context {
        global $DB;
        $post = $DB->get_record('forum_posts', ['id' => (int)$arguments['postid']], '*', MUST_EXIST);
        $discussion = $DB->get_record('forum_discussions', ['id' => $post->discussion], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('forum', $discussion->forum, $discussion->course, false, MUST_EXIST);
        return context_module::instance($cm->id);
    }

    /**
     * @param array $arguments Tool input.
     * @param authenticated_identity $identity OAuth identity.
     * @return array
     */
    public function execute(array $arguments,
            authenticated_identity $identity): array {
        global $DB, $CFG, $USER;
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        $parent = $DB->get_record('forum_posts', ['id' => (int)$arguments['postid']], '*', MUST_EXIST);
        $discussion = $DB->get_record('forum_discussions', ['id' => $parent->discussion], '*', MUST_EXIST);
        $forum = $DB->get_record('forum', ['id' => $discussion->forum], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('forum', $forum->id, $forum->course, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        $course = get_course($forum->course);
        if (!forum_user_can_see_post($forum, $discussion, $parent, $USER, $cm)
            || !forum_user_can_post($forum, $discussion, $USER, $cm, $course, $context)) {
            throw new api_exception('permission_denied', 403);
        }
        $post = (object)[
            'discussion' => $discussion->id, 'parent' => $parent->id,
            'subject' => clean_param($arguments['subject'] ?? ('Re: ' . $parent->subject), PARAM_TEXT),
            'message' => clean_text($arguments['message'], FORMAT_HTML),
            'messageformat' => FORMAT_HTML, 'messagetrust' => 0, 'itemid' => 0,
            'mailnow' => 0, 'attachments' => 0,
        ];
        $id = forum_add_new_post($post, null);
        return ['postid' => (int)$id, 'discussionid' => (int)$discussion->id];

    }
}
