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
 * Moodle badges MCP integration and safe image tests.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcp;

use advanced_testcase;
use local_mcp\exception\api_exception;
use local_mcp\security\authenticated_identity;
use mcptool_badges\image;
use mcptool_badges\provider;
use mcptool_badges\read\get_badge;
use mcptool_badges\read\list_badges;
use mcptool_badges\write\create_badge;
use mcptool_badges\write\replace_image;
use mcptool_badges\write\set_criteria;
use mcptool_badges\write\enable_badge;

/**
 * Badge creation is intentionally separated from activation and issuance.
 *
 * @covers \mcptool_badges\provider
 * @covers \mcptool_badges\image
 * @covers \mcptool_badges\read\get_badge
 * @covers \mcptool_badges\read\list_badges
 * @covers \mcptool_badges\write\create_badge
 * @covers \mcptool_badges\write\replace_image
 * @covers \mcptool_badges\write\set_criteria
 * @covers \mcptool_badges\write\enable_badge
 * @covers \mcptool_badges\read\image_guide
 */
final class badges_tools_test extends advanced_testcase {
    /**
     * Generate a real, optionally transparent square PNG without fixtures.
     *
     * @param bool $transparent Whether its outer pixels contain alpha.
     * @return string
     */
    private static function png(bool $transparent): string {
        $image = imagecreatetruecolor(256, 256);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $background = imagecolorallocatealpha($image, 255, 255, 255, $transparent ? 127 : 0);
        imagefill($image, 0, 0, $background);
        $ink = imagecolorallocatealpha($image, 20, 40, 95, 0);
        imagefilledellipse($image, 128, 128, 135, 135, $ink);
        ob_start();
        imagepng($image);
        $raw = ob_get_clean();
        imagedestroy($image);
        return $raw;
    }

    /**
     * @return void
     */
    public function test_provider_registers_read_and_confirmed_write_tools(): void {
        $provider = new provider();
        $read = array_map(static fn($tool): string => $tool->get_name(), $provider->get_read_tools());
        $write = array_map(static fn($tool): string => $tool->get_name(), $provider->get_write_tools());
        $this->assertSame(["badges_image_guide", "badges_list", "badges_get"], $read);
        $this->assertSame(["badges_create", "badges_replace_image", "badges_set_criteria", "badges_enable"], $write);
        foreach ($provider->get_write_tools() as $tool) {
            $this->assertTrue($tool->requires_confirmation());
            $this->assertSame("object", $tool->get_input_schema()["type"]);
        }
    }

    /**
     * @return void
     */
    public function test_base64_image_preserves_alpha_and_rejects_opaque_image(): void {
        $transparent = self::png(true);
        $details = image::load(["image_base64" => "data:image/png;base64," . base64_encode($transparent)]);
        $this->assertSame(256, $details["width"]);
        $this->assertTrue($details["transparent_edges"]);
        $this->assertSame(hash("sha256", $transparent), $details["sha256"]);

        try {
            image::load(["image_base64" => base64_encode(self::png(false))]);
            $this->fail("Opaque image should have been rejected.");
        } catch (api_exception $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        $opaque = image::load(["image_base64" => base64_encode(self::png(false)),
            "require_transparency" => false]);
        $this->assertFalse($opaque["transparent_edges"]);
    }

    /**
     * @return void
     */
    public function test_image_loader_rejects_private_urls_without_network_access(): void {
        foreach (["http://example.org/badge.png", "https://127.0.0.1/badge.png",
            "https://localhost/badge.png"] as $url) {
            try {
                image::load(["image_url" => $url]);
                $this->fail("Unsafe URL should have been rejected: " . $url);
            } catch (api_exception $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    /**
     * @return void
     */
    public function test_create_preview_and_native_badge_image(): void {
        global $DB, $CFG, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config("enablebadges", 1);
        set_config("badges_allowcoursebadges", 1);
        $CFG->gdversion = 2;
        $course = $this->getDataGenerator()->create_course();
        $identity = new authenticated_identity((int)$USER->id, "test", ["mcp:read", "mcp:write"]);
        $args = [
            "courseid" => (int)$course->id, "name" => "Transparent project achievement",
            "description" => "Awarded for successful completion of the project.",
            "image_base64" => base64_encode(self::png(true)),
        ];
        $tool = new create_badge();
        $before = $DB->count_records("badge");
        $preview = $tool->preview($args, $identity);
        $this->assertSame("base64", $preview["image_source"]);
        $this->assertSame(256, $preview["image"]["width"]);
        $this->assertArrayNotHasKey("bytes", $preview["image"]);
        $this->assertSame($before, $DB->count_records("badge"));
        $created = $tool->execute($args, $identity);
        $this->assertGreaterThan(0, $created["badgeid"]);
        $this->assertSame("inactive", $created["status"]);
        $this->assertSame($before + 1, $DB->count_records("badge"));
        $stored = new \badge($created["badgeid"]);
        $this->assertSame(BADGE_STATUS_INACTIVE, (int)$stored->status);
        $this->assertNotFalse(get_file_storage()->get_file($stored->get_context()->id,
            "badges", "badgeimage", $stored->id, "/", "f3.png"));
        $read = (new get_badge())->execute(["badgeid" => $created["badgeid"]], $identity);
        $this->assertTrue($read["image_exists"]);
        $this->assertSame(0, $read["awards"]);
        $listed = (new list_badges())->execute(["courseid" => $course->id], $identity);
        $this->assertCount(1, $listed["badges"]);
        $this->assertSame($created["badgeid"], $listed["badges"][0]["badgeid"]);

        $replace = new replace_image();
        $replaceargs = [
            "badgeid" => $created["badgeid"],
            "expected_timemodified" => $read["timemodified"],
            "image_base64" => base64_encode(self::png(true)),
        ];
        $this->assertTrue($replace->preview($replaceargs, $identity)["requires_explicit_confirmation"]);
        $changed = $replace->execute($replaceargs, $identity);
        $this->assertTrue($changed["updated"]);
        $stored->set_status(BADGE_STATUS_ACTIVE);
        $this->expectException(api_exception::class);
        $replace->preview(["badgeid" => $stored->id,
            "expected_timemodified" => (int)$stored->timemodified,
            "image_base64" => base64_encode(self::png(true))], $identity);
    }
    /**
     * Missing badges return a typed 404 instead of an internal server error.
     *
     * @return void
     */
    public function test_missing_badge_uses_explicit_not_found(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $arguments = ["badgeid" => 2147483647, "expected_timemodified" => 0,
            "cmids" => [1]];
        foreach ([new get_badge(), new replace_image(), new set_criteria(), new enable_badge()] as $tool) {
            try {
                $tool->resolve_context($arguments);
                $this->fail("Missing badge did not return a controlled error.");
            } catch (api_exception $e) {
                $this->assertSame("badge_not_found", $e->machinecode);
                $this->assertSame(404, $e->httpstatus);
            }
        }
    }

}
