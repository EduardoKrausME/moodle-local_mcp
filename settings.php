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
 * settings.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_mcp\security\rate_limiter;

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_category('local_mcp_category', get_string('pluginname', 'local_mcp')));
    $pages = [
        'dashboard' => '/local/mcp/index.php',
        'connections' => '/local/mcp/admin/connections.php',
        'oauthclients' => '/local/mcp/admin/clients.php',
        'readapis' => '/local/mcp/admin/readapis.php',
        'writeapis' => '/local/mcp/admin/writeapis.php',
        'audit' => '/local/mcp/admin/audit.php',
    ];
    foreach ($pages as $key => $url) {
        $ADMIN->add('local_mcp_category', new admin_externalpage('local_mcp_' . $key,
            get_string($key, 'local_mcp'), new moodle_url($url), "local/mcp:manage"));
    }

    $settings = new admin_settingpage("local_mcp_settings", get_string("settings", "local_mcp"));
    $settings->add(new admin_setting_configduration("local_mcp/accessttl",
        get_string("settingsaccessttl", "local_mcp"),
        get_string("settingsaccessttl_desc", "local_mcp"), 3600, 60));
    $settings->add(new admin_setting_configduration("local_mcp/refreshttl",
        get_string("settingsrefreshttl", "local_mcp"),
        get_string("settingsrefreshttl_desc", "local_mcp"), 90 * DAYSECS, HOURSECS));
    $settings->add(new admin_setting_configduration("local_mcp/codettl",
        get_string("settingscodettl", "local_mcp"),
        get_string("settingscodettl_desc", "local_mcp"), 300, 60));
    $settings->add(new admin_setting_configduration("local_mcp/confirmationttl",
        get_string("settingsconfirmationttl", "local_mcp"),
        get_string("settingsconfirmationttl_desc", "local_mcp"), 1800, 30));
    $settings->add(new admin_setting_configcheckbox("local_mcp/dynamicregistration",
        get_string("settingsdynamicregistration", "local_mcp"),
        get_string("settingsdynamicregistration_desc", "local_mcp"), 1));
    $settings->add(new admin_setting_configcheckbox("local_mcp/logmessages",
        get_string("settingslogmessages", "local_mcp"),
        get_string("settingslogmessages_desc", "local_mcp"), 0));
    $settings->add(new admin_setting_configtext("local_mcp/rateread",
        get_string("settingsrateread", "local_mcp"), "", rate_limiter::DEFAULT_READ_PER_MINUTE, PARAM_INT));
    $settings->add(new admin_setting_configtext("local_mcp/ratewrite",
        get_string("settingsratewrite", "local_mcp"), "", rate_limiter::DEFAULT_WRITE_PER_MINUTE, PARAM_INT));
    $settings->add(new admin_setting_configtext("local_mcp/rateoauth",
        get_string("settingsrateoauth", "local_mcp"), "", rate_limiter::DEFAULT_OAUTH_PER_MINUTE, PARAM_INT));
    $settings->add(new admin_setting_configtext("local_mcp/auditretention",
        get_string("settingsauditretention", "local_mcp"), "", 180, PARAM_INT));
    $ADMIN->add('local_mcp_category', $settings);
}
