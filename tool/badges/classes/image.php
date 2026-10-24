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
 * Validate badge PNG uploads from HTTPS URLs or Base64.
 *
 * @package   mcptool_badges
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_badges;

use local_mcp\exception\api_exception;

/**
 * Strict PNG importer. Images are not kept in this plugin's own file area.
 */
final class image {
    public const MAX_BYTES = 262144;

    /**
     * @param array $args Tool arguments, including exactly one image source.
     * @return array Raw bytes plus safe metadata.
     */
    public static function load(array $args): array {
        $hasurl = !empty($args["image_url"]);
        $hasbase64 = !empty($args["image_base64"]);
        if ($hasurl === $hasbase64) {
            throw new api_exception("Supply exactly one of image_url or image_base64", 400);
        }
        $raw = $hasurl ? self::fetch_url((string)$args["image_url"])
            : self::decode_base64((string)$args["image_base64"]);
        return self::inspect($raw, $args["require_transparency"] ?? true);
    }

    /**
     * Validate the supplied Base64 without any external network request.
     *
     * @param array $args Tool arguments.
     * @return array|null Safe description, or null if using URL.
     */
    public static function preview_image(array $args): ?array {
        if (empty($args["image_base64"])) {
            return null;
        }
        $meta = self::inspect(self::decode_base64((string)$args["image_base64"]),
            $args["require_transparency"] ?? true);
        unset($meta["bytes"]);
        return $meta;
    }

    /**
     * @param string $data Raw Base64, or a PNG data URI.
     * @return string
     */
    private static function decode_base64(string $data): string {
        if (str_starts_with($data, "data:image/png;base64,")) {
            $data = substr($data, strlen("data:image/png;base64,"));
        }
        // Reject oversized input before allocating a decoded binary buffer.
        if (strlen($data) > 360000 || !preg_match("~^[A-Za-z0-9+/]*={0,2}$~D", $data)) {
            throw new api_exception("Invalid or oversized image_base64", 400);
        }
        $raw = base64_decode($data, true);
        if ($raw === false || strlen($raw) === 0 || strlen($raw) > self::MAX_BYTES) {
            throw new api_exception("Invalid PNG Base64 or image over 256 KiB", 400);
        }
        return $raw;
    }

    /**
     * Download only from public HTTPS endpoints, using Moodle's SSRF-guarded curl.
     *
     * @param string $url Image URL.
     * @return string
     */
    private static function fetch_url(string $url): string {
        global $CFG;
        $parts = parse_url($url);
        if (!$parts || strtolower($parts["scheme"] ?? "") !== "https" ||
            empty($parts["host"]) || !empty($parts["user"]) || !empty($parts["pass"]) ||
            !empty($parts["fragment"]) || isset($parts["port"]) && (int)$parts["port"] !== 443) {
            throw new api_exception("image_url must be a public HTTPS URL on port 443", 400);
        }
        $host = strtolower(rtrim($parts["host"], "."));
        if (filter_var($host, FILTER_VALIDATE_IP) ||
            $host === "localhost" || str_ends_with($host, ".localhost") ||
            str_ends_with($host, ".local") || str_ends_with($host, ".internal")) {
            throw new api_exception("Private or literal IP hosts are not accepted", 400);
        }
        require_once($CFG->libdir . "/filelib.php");
        // The default Moodle security helper blocks internal addresses and pins DNS resolutions.
        $curl = new \curl();
        $body = $curl->get($url, [], [
            "CURLOPT_TIMEOUT" => 15,
            "CURLOPT_CONNECTTIMEOUT" => 5,
            "CURLOPT_FOLLOWLOCATION" => false,
            "CURLOPT_MAXFILESIZE" => self::MAX_BYTES,
            "CURLOPT_SSL_VERIFYPEER" => true,
            "CURLOPT_SSL_VERIFYHOST" => 2,
        ]);
        $info = $curl->get_info();
        if ($curl->get_errno() || (int)($info["http_code"] ?? 0) !== 200 ||
            !is_string($body) || strlen($body) === 0 || strlen($body) > self::MAX_BYTES) {
            throw new api_exception("Image download failed, redirected, or exceeded 256 KiB", 400);
        }
        return $body;
    }

    /**
     * @param string $raw Original PNG bytes.
     * @param bool $requiretransparency Enforce transparent canvas edges.
     * @return array
     */
    public static function inspect(string $raw, bool $requiretransparency = true): array {
        if (strlen($raw) > self::MAX_BYTES || substr($raw, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            throw new api_exception("Image must be a PNG of at most 256 KiB", 400);
        }
        $info = @getimagesizefromstring($raw);
        if (!$info || $info[2] !== IMAGETYPE_PNG ||
            $info[0] !== $info[1] || $info[0] < 256 || $info[0] > 1024) {
            throw new api_exception("PNG must be square, between 256 and 1024 pixels", 400);
        }
        if (!function_exists("imagecreatefromstring")) {
            throw new api_exception("PHP GD extension is required for badge images", 503);
        }
        $resource = @imagecreatefromstring($raw);
        if (!$resource) {
            throw new api_exception("PNG could not be decoded", 400);
        }
        $clear = 0;
        $samples = 0;
        $size = imagesx($resource);
        // Transparent artwork has a mostly clear perimeter, not just one transparent pixel.
        for ($i = 0; $i < $size; $i += max(1, (int)floor($size / 32))) {
            foreach ([[0, $i], [$size - 1, $i], [$i, 0], [$i, $size - 1]] as [$x, $y]) {
                $pixel = imagecolorat($resource, $x, $y);
                $alpha = imageistruecolor($resource)
                    ? (($pixel >> 24) & 0x7f)
                    : imagecolorsforindex($resource, $pixel)["alpha"];
                $samples++;
                if ($alpha >= 120) {
                    $clear++;
                }
            }
        }
        $transparent = $samples > 0 && $clear / $samples >= 0.95;
        imagedestroy($resource);
        if ($requiretransparency && !$transparent) {
            throw new api_exception("PNG border is not mostly transparent; export with a real alpha channel and clear margins", 400);
        }
        return [
            "bytes" => $raw, "sha256" => hash("sha256", $raw),
            "size_bytes" => strlen($raw), "width" => $info[0], "height" => $info[1],
            "transparent_edges" => $transparent,
        ];
    }

    /**
     * @param \badge $badge Native Moodle badge.
     * @param string $raw Validated image bytes.
     * @return void
     */
    public static function save(\badge $badge, string $raw): void {
        global $CFG;
        require_once($CFG->libdir . "/badgeslib.php");
        $dir = make_temp_directory("mcptool_badges");
        $temp = tempnam($dir, "badge_");
        if ($temp === false) {
            throw new api_exception("Cannot allocate image temporary file", 500);
        }
        try {
            if (file_put_contents($temp, $raw) !== strlen($raw)) {
                throw new api_exception("Cannot write badge image", 500);
            }
            badges_process_badge_image($badge, $temp);
            $stored = get_file_storage()->get_file(
                $badge->get_context()->id, "badges", "badgeimage", $badge->id, "/", "f3.png");
            if (!$stored) {
                throw new api_exception("Moodle could not process the badge image", 500);
            }
        } finally {
            if (file_exists($temp)) {
                unlink($temp);
            }
        }
    }
}
