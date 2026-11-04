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
//
// @package   local_mcp
// @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
// @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\plugin;

/**
 * Builds an installable plugin package using this Moodle's own MCP URL.
 */
final class package_builder {
    /**
     * Prepare portable Agent Plugins files and Codex compatibility files.
     *
     * @param string $serverurl HTTPS MCP endpoint for this Moodle.
     * @param string $sitename Human-readable Moodle name.
     * @return array<string, string> Archive path => UTF-8 file contents.
     */
    public static function get_files(string $serverurl, string $sitename): array {
        $parts = parse_url($serverurl);
        if ($parts === false || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) ||
                isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) ||
                isset($parts['fragment']) || substr($serverurl, -11) !== '/server.php') {
            throw new \invalid_argument_exception('A valid HTTPS Moodle MCP server URL is required.');
        }

        $host = strtolower($parts['host']);
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', $host), '-');
        $name = 'moodle-' . ($slug ?: 'mcp');
        $sitename = trim(str_replace(["\r", "\n", "\t"], ' ', $sitename));
        if ($sitename === '') {
            $sitename = 'Moodle';
        }

        $displayname = 'Moodle - ' . $sitename;
        $description = 'Connect to ' . $sitename . ' through OAuth-protected Moodle MCP tools.';
        $endpointbase = substr($serverurl, 0, -11);
        $auth = [
            'type' => 'oauth',
            'client' => ['mode' => 'dcr'],
            'authorizationUrl' => $endpointbase . '/oauth/authorize.php',
            'tokenUrl' => $endpointbase . '/oauth/token.php',
            'registrationUrl' => $endpointbase . '/oauth/register.php',
            'authorizationServerBase' => $serverurl,
            'resource' => $serverurl,
        ];

        $interface = [
            'displayName' => $displayname,
            'shortDescription' => 'Connect ChatGPT to this Moodle through MCP.',
            'longDescription' => $description,
            'developerName' => $sitename,
            'category' => 'Other',
            'capabilities' => ['Read', 'Write'],
        ];
        $portablemcp = [
            '$schema' => 'https://agent-plugins.org/schemas/1.0.0/mcp.schema.json',
            'mcpServers' => [
                'moodle' => [
                    'type' => 'streamable-http',
                    'url' => $serverurl,
                    'extensions' => ['com.openai' => ['auth' => $auth]],
                ],
            ],
        ];
        $legacymcp = [
            'mcpServers' => [
                'moodle' => [
                    'type' => 'http',
                    'url' => $serverurl,
                    'extensions' => ['com.openai' => ['auth' => $auth]],
                ],
            ],
        ];
        $plugin = [
            '$schema' => 'https://agent-plugins.org/schemas/1.0.0/plugin.schema.json',
            'name' => $name,
            'version' => '1.0.0',
            'description' => $description,
            'extensions' => ['com.openai' => ['interface' => $interface]],
        ];
        $legacyplugin = [
            'name' => $name,
            'version' => '1.0.0',
            'description' => $description,
            'mcpServers' => './.mcp.json',
            'interface' => $interface,
        ];

        $jsonflags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;
        return [
            'plugin.json' => json_encode($plugin, $jsonflags) . "\n",
            'mcp.json' => json_encode($portablemcp, $jsonflags) . "\n",
            '.codex-plugin/plugin.json' => json_encode($legacyplugin, $jsonflags) . "\n",
            '.mcp.json' => json_encode($legacymcp, $jsonflags) . "\n",
            'README.txt' => "Moodle MCP plugin: " . $sitename . "\n" .
                "MCP server: " . $serverurl . "\n" .
                "Authentication: OAuth 2.0 (Dynamic Client Registration / DCR)\n\n" .
                "This ZIP includes portable Agent Plugins and Codex compatibility manifests.\n" .
                "It contains no passwords, access tokens or registered ChatGPT app IDs.\n" .
                "Import capabilities vary by ChatGPT client; custom MCP registration may still be needed.\n",
        ];
    }
}
