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

    /** Cache per authorization context for a short period to avoid immediate rediscovery loops. */
    public const CACHE_TTL_MS = 60000;

    /**
     * Return the effective Moodle URL configured for this instance.
     *
     * @return string Moodle wwwroot.
     */
    public static function wwwroot(): string {
        global $CFG;
        return rtrim((string)$CFG->wwwroot, "/");
    }

    /**
     * Include the current Moodle instance identity in MCP metadata.
     *
     * @return array
     */
    public static function site_meta(): array {
        return ["moodle_wwwroot" => self::wwwroot()];
    }

    /**
     * Attach the site identity to any MCP tool data, including errors and previews.
     *
     * @param array $data Tool data.
     * @return array
     */
    public static function with_site_identity(array $data): array {
        $data["moodle_wwwroot"] = self::wwwroot();
        return $data;
    }

    /**
     * Keep the site URL visible in both modern and legacy handshakes.
     *
     * @return string
     */
    private static function instructions(): string {
        return "This connector operates on Moodle at " . self::wwwroot() . ". "
            . "Always identify this Moodle instance by its wwwroot, especially before "
            . "previewing or confirming WRITE actions. Use tools/list to discover tools "
            . "for this instance. For requests about live Moodle courses, categories, "
            . "users, enrolments or images, invoke tools/call instead of inventing data. "
            . "For all Moodle WRITE content, use a plain ASCII hyphen (-) instead of "
            . "the Unicode em dash (U+2014). The server also replaces em dashes "
            . "in incoming WRITE text before validation, preview and execution. "
            . "READ and WRITE permissions are independent; Moodle capabilities are "
            . "checked for each operation. WRITE tools can require confirmation. "
            . "When the user requests multiple Moodle changes, create a workflow_sequence plan "
            . "with the ordered item titles and their WRITE tool names (maximum 30). "
            . "Prepare, preview, approve, execute and check exactly one item at a time. "
            . "Pass workflow_id and workflow_step to each planned WRITE operation. "
            . "Do not preview or perform later items before the user selects Next. "
            . "After a successful confirmed WRITE, present the Next action supplied by the connector. "
            . "Next advances the workflow plan only; it never approves or executes a Moodle WRITE. "
            . "Do not silently advance, batch confirmations, or claim that a preview changed Moodle.";
    }

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
            "websiteUrl" => self::wwwroot(),
        ];
    }

    /**
     * Advertise only features actually implemented by local_mcp.
     *
     * @return array
     */
    public static function capabilities(): array {
        return ["tools" => (object)[], "resources" => ["listChanged" => false]];
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
                "moodle_wwwroot" => self::wwwroot(),
            ],
            'instructions' => self::instructions(),
            // Keep metadata scoped to the authenticated client and refresh it regularly.
            // Revoking a token is still enforced on every HTTP request.
            'ttlMs' => self::CACHE_TTL_MS,
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
            "instructions" => self::instructions(),
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
            $result['ttlMs'] = self::CACHE_TTL_MS;
            $result['cacheScope'] = 'private';
        }
        return $result;
    }
}
