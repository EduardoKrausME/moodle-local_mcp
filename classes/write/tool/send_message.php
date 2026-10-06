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
 * send_message.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\write\tool;

use context;
use context_user;
use core\message\message;
use local_mcp\security\authenticated_identity;

/**
 * Class send_message.
 */
final class send_message extends base_tool {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string {
        return 'send_message';
    }

    /**
     * Method get_title.
     *
     * @return string Return value.
     */
    public function get_title(): string {
        return 'Send message';
    }

    /**
     * Method get_description.
     *
     * @return string Return value.
     */
    public function get_description(): string {
        return 'Send a Moodle instant message from the connected user.';
    }

    /**
     * Method get_required_capability.
     *
     * @return string Return value.
     */
    public function get_required_capability(): string {
        return 'moodle/site:sendmessage';
    }

    /**
     * Method get_input_schema.
     *
     * @return array Return value.
     */
    public function get_input_schema(): array {
        return $this->object_schema([
            'touserid' => ['type' => 'integer', 'minimum' => 1], 'message' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 10000]
        ], ['touserid', 'message']);
    }

    /**
     * Method resolve_context.
     *
     * @param array $arguments Parameter arguments.
     * @return context Return value.
     */
    public function resolve_context(array $arguments): context {
        return context_user::instance((int)$arguments['touserid'], MUST_EXIST);
    }

    /**
     * Method execute.
     *
     * @param array $arguments Parameter arguments.
     * @param authenticated_identity $identity Parameter identity.
     * @return array Return value.
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        global $DB;
        $from = $DB->get_record('user', ['id' => $identity->userid, 'deleted' => 0], '*', MUST_EXIST);
        $to = $DB->get_record('user', ['id' => (int)$arguments['touserid'], 'deleted' => 0], '*', MUST_EXIST);
        $message = new message();
        $message->component = 'moodle';
        $message->name = 'instantmessage';
        $message->userfrom = $from;
        $message->userto = $to;
        $message->subject = '';
        $message->fullmessage = clean_param($arguments['message'], PARAM_TEXT);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '';
        $message->smallmessage = $message->fullmessage;
        $message->notification = 0;
        $id = message_send($message);
        return ['sent' => true, 'messageid' => (int)$id];
    }
}
