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
 * get_posts.php
 *
 * @package   mcptool_forum
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_forum\read;

use coding_exception;
use context;
use context_module;
use dml_exception;
use local_mcp\exception\api_exception;
use local_mcp\read\tool\base_tool;
use local_mcp\security\authenticated_identity;
use local_mcp\security\untrusted_content;
use moodle_exception;

/**
 * Read visible posts from one forum discussion, respecting groups and per-post visibility.
 */
final class get_posts extends base_tool {
    /**
     * get_name
     *
     * @return string
     */
    public function get_name(): string {
        return 'forum_get_posts';
    }

    /**
     * get_title
     *
     * @return string
     */
    public function get_title(): string {
        return 'Forum: read discussion';
    }

    /**
     * get_description
     *
     * @return string
     */
    public function get_description(): string {
        return 'Read visible posts from one forum discussion, respecting groups and per-post visibility.';
    }

    /**
     * get_required_capability
     *
     * @return string
     */
    public function get_required_capability(): string {
        return 'mod/forum:viewdiscussion';
    }

    /**
     * get_input_schema
     *
     * @return array
     */
    public function get_input_schema(): array {
        return $this->object_schema(['discussionid' => ['type' => 'integer', 'minimum' => 1], 'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100], 'offset' => ['type' => 'integer', 'minimum' => 0]], ['discussionid']);
    }

    /**
     * resolve_context
     *
     * @param array $arguments
     * @return context
     * @throws coding_exception
     * @throws dml_exception
     */
    public function resolve_context(array $arguments): context {
        global $DB;
        $discussion = $DB->get_record('forum_discussions', ['id' => (int)$arguments['discussionid']], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('forum', $discussion->forum, $discussion->course, false, MUST_EXIST);
        return context_module::instance($cm->id);
    }

    /**
     * execute
     *
     * @param array $arguments
     * @param authenticated_identity $identity
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws api_exception
     */
    public function execute(array                  $arguments,
                            authenticated_identity $identity): array {
        global $DB, $CFG, $USER;
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        $discussion = $DB->get_record('forum_discussions', ['id' => (int)$arguments['discussionid']], '*', MUST_EXIST);
        $forum = $DB->get_record('forum', ['id' => $discussion->forum], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('forum', $forum->id, $forum->course, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        if (!get_fast_modinfo($cm->course, $identity->userid)->get_cm($cm->id)->uservisible) {
            throw new api_exception('permission_denied', 403);
        }
        if (!forum_user_can_see_discussion($forum, $discussion, $context, $USER)) {
            throw new api_exception('permission_denied', 403);
        }
        $posts = [];
        $limit = min(100, max(1, (int)($arguments['limit'] ?? 50)));
        $offset = max(0, (int)($arguments['offset'] ?? 0));
        foreach ($DB->get_records('forum_posts', ['discussion' => $discussion->id],
            'created ASC', '*', $offset, $limit) as $post) {
            if (!forum_user_can_see_post($forum, $discussion, $post, $USER, $cm)) {
                continue;
            }
            $posts[] = [
                'id' => (int)$post->id,
                'parent' => (int)$post->parent,
                'userid' => (int)$post->userid,
                'subject' => $post->subject,
                'message' => untrusted_content::text(format_text($post->message, $post->messageformat,
                    ['context' => $context, 'filter' => true])),
                'created' => (int)$post->created,
            ];
        }
        return ['discussionid' => (int)$discussion->id, 'title' => $discussion->name, 'offset' => $offset,
            'limit' => $limit, 'posts' => $posts];
    }
}
