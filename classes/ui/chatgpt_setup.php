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
 * ChatGPT onboarding setup data.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\ui;

use local_mcp\oauth\connection_service;
use moodle_url;

/**
 * Present the getting-started instructions only before ChatGPT is connected.
 */
final class chatgpt_setup {
    /**
     * Provide template data shared by the dashboard and connections screen.
     *
     * @return array Setup guide presentation data.
     */
    public static function template_data(): array {
        global $CFG;

        return [
            'showchatgptsetup' => !connection_service::has_active_chatgpt_connection(),
            'chatgpturl' => 'https://chatgpt.com/',
            'mcpserverurl' => (new moodle_url('/local/mcp/server.php'))->out(false),
            'settingsurl' => (new moodle_url('/admin/settings.php',
                ['section' => 'local_mcp_settings']))->out(false),
            'discoveryurl' => (new moodle_url(
                '/.well-known/oauth-authorization-server/local/mcp'))->out(false),
            'registrationdisabled' => !get_config('local_mcp', 'dynamicregistration'),
            'httpsdisabled' => stripos($CFG->wwwroot, 'https://') !== 0,
        ];
    }
}
