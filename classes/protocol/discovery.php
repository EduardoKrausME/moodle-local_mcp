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
 * discovery.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp\protocol;

/**
 * Protocol metadata for MCP 2026-07-28 with legacy initialize compatibility.
 *
 * No private tokens, usernames or connection-specific tool lists are included.
 */
final class discovery {
    /** MCP specification supported for stateless requests and server/discover. */
    public const CURRENT_VERSION = '2026-07-28';

    /** Original MCP protocol version used by existing integrations. */
    public const LEGACY_VERSION = '2025-06-18';

    /**
     * Metadata common to discovery and legacy initialize.
     *
     * @param string $side Endpoint side.
     * @param string $release Plugin release.
     * @return array
     */
    public static function server_info(string $side, string $release): array {
        return [
            'name' => 'Moodle MCP ' . strtoupper($side),
            'version' => $release,
        ];
    }

    /**
     * Advertise only features actually implemented by local_mcp.
     *
     * @return array
     */
    public static function capabilities(): array {
        return ['tools' => (object)[]];
    }

    /**
     * Build a 2026-07-28 server/discover result.
     *
     * @param string $side Requested endpoint.
     * @param string $release Moodle plugin release.
     * @return array Valid MCP discovery result.
     */
    public static function result(string $side, string $release): array {
        return [
            'resultType' => 'complete',
            'supportedVersions' => [self::CURRENT_VERSION, self::LEGACY_VERSION],
            'capabilities' => self::capabilities(),
            '_meta' => [
                'io.modelcontextprotocol/serverInfo' => self::server_info($side, $release),
            ],
            'instructions' => 'Use tools/list to see tools authorized for the connected Moodle user. '
                . 'READ and WRITE permissions are independent; Moodle capabilities are checked for '
                . 'each operation. WRITE tools can require a confirmation token before execution.',
            // Tool catalogues and permissions can change at any time.
            // Never let a different token/user reuse cached discovery metadata.
            'ttlMs' => 0,
            'cacheScope' => 'private',
        ];
    }

    /**
     * Preserve the old initialize handshake and legacy version negotiation.
     *
     * @param string $side Endpoint side.
     * @param string $release Plugin release.
     * @return array
     */
    public static function initialize_result(string $side, string $release): array {
        return [
            'protocolVersion' => self::LEGACY_VERSION,
            'capabilities' => self::capabilities(),
            'serverInfo' => self::server_info($side, $release),
        ];
    }

    /**
     * Detect modern responses from the client's request _meta or HTTP header.
     *
     * @param array $params Request parameters.
     * @param string $headerversion Optional MCP-Protocol-Version HTTP header.
     * @return bool
     */
    public static function is_modern(array $params, string $headerversion = ''): bool {
        $meta = $params['_meta'] ?? [];
        $version = is_array($meta) ? ($meta['io.modelcontextprotocol/protocolVersion'] ?? '') : '';
        if ($version === '') {
            $version = $headerversion;
        }
        return $version === self::CURRENT_VERSION;
    }

    /**
     * Mark modern MCP results as complete.
     *
     * @param array $result Result body.
     * @param bool $modern Whether to use the modern result envelope.
     * @param bool $cacheable Whether this is a list result requiring cache metadata.
     * @return array
     */
    public static function decorate_result(array $result, bool $modern, bool $cacheable = false): array {
        if (!$modern) {
            return $result;
        }
        $result = ['resultType' => 'complete'] + $result;
        if ($cacheable) {
            $result['ttlMs'] = 0;
            $result['cacheScope'] = 'private';
        }
        return $result;
    }
}
