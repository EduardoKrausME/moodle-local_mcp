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
 * Regression tests for translated administration labels.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;

/**
 * Verify that administration strings are available in both supported languages.
 */
final class translations_test extends advanced_testcase {
    /**
     * Admin settings and OAuth client labels must not fall back to untranslated strings.
     *
     * @return void
     */
    public function test_admin_strings_exist_in_both_languages(): void {
        $keys = [
            "settingsaccessttl", "settingsaccessttl_desc", "settingsrefreshttl",
            "settingsrefreshttl_desc", "settingscodettl", "settingscodettl_desc",
            "settingsconfirmationttl", "settingsconfirmationttl_desc", "settingsdynamicregistration",
            "settingsdynamicregistration_desc", "settingslogmessages", "settingslogmessages_desc",
            "settingsrateread", "settingsratewrite", "settingsrateoauth",
            "settingsauditretention", "clientcolname", "clientcolclientid",
            "clientcoltype", "clientcoldynamic", "clientcolenabled",
            "clientcolcreated", "clientcollastused", "cachedef_ratelimit",
            "cachedef_tooldefinitions", "privacy:metadata:local_mcp_audit", "privacy:metadata:local_mcp_connections",
            "dashboardstatusok", "dashboardstatuspreview", "dashboardstatuspending",
            "dashboardstatuserror",
        ];

        foreach (["en", "pt_br"] as $language) {
            $string = [];
            require(__DIR__ . "/../lang/" . $language . "/local_mcp.php");

            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $string, $language . ": " . $key);
                $this->assertIsString($string[$key]);
                $this->assertNotSame("", trim($string[$key]), $language . ": " . $key);
            }
        }
    }
}
