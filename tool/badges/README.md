# Moodle badge MCP tools

This subplugin integrates with the **native Moodle badge subsystem** in Moodle 4.5–5.3. It exposes READ tools \`badges_image_guide\`, \`badges_list\`, \`badges_get\`, and confirmed WRITE tools \`badges_create\` and \`badges_replace_image\`. The parent \`local_mcp\` plugin discovers them automatically once Moodle has installed the new \`mcptool_badges\` subplugin. No changes to the root version or GitHub Actions workflows are needed.

## How to prepare a badge image

1. Generate a square design, ideally **512 × 512 pixels** (accepted: 256–1024 px square), consisting of one central meaningful symbol, a restrained palette and approximately 10–15% space around the silhouette. It should still be recognizable at 64 × 64 pixels. Avoid personal data, names of students, tiny writing and unintended branding.
2. **The background must be transparent, not white.** Use RGBA PNG with an alpha channel and transparent pixels on the canvas perimeter; never bake a white or checkerboard pattern into the artwork. Preview on both black and white backgrounds and inspect all four corners. A JPG renamed to .png is not acceptable.
3. Export a genuinely transparent **PNG ≤256 KiB**, optimised without flattening alpha. Remove unnecessary metadata. If the source design is SVG, rasterise it to an RGBA PNG before calling the tool. Do not claim the Moodle tool automatically removes white backgrounds; that requires external image editing.
4. Provide exactly one of \`image_url\` (direct public HTTPS PNG link, no private host or redirects) or \`image_base64\` (raw Base64 or \`data:image/png;base64,...\`). Avoid signed URLs containing secrets if possible and keep the URL available until you confirm. Large JSON Base64 payloads may exceed the MCP client's or server's message limits; use an HTTPS URL in that case.
5. Review the \`badges_create\` preview, then explicitly approve the matching arguments and confirmation token. The badge is saved using the core \`badge::create_badge()\` and \`badges_process_badge_image()\` APIs. Its status remains **inactive**; configure criteria and enable it later in Moodle. The tool does **not** issue awards to students.

Example image-generation instruction:

> Design a 512 × 512 px academic-achievement badge with a single stylised golden graduation cap, a compact navy and gold laurel wreath, bold legible silhouette, 12% clear border, no words, no background plate or outer shadows. Export a PNG with truly transparent RGBA background; all corners must be transparent, with no white matte or checkerboard. Optimize the PNG to 256 KiB or less while retaining alpha.

Example tool arguments:

\`\`\`json
{
  "courseid": 33,
  "name": "Course Project Excellence",
  "description": "Recognises an excellent completed academic project.",
  "image_url": "https://example.org/assets/course-project-badge.png",
  "image_caption": "Gold academic cap and laurel on transparent canvas",
  "require_transparency": true
}
\`\`\`

Use \`courseid: 0\` or omit it for a site-level badge. \`badges_list\` and \`badges_get\` accept course/badge IDs, and \`badges_replace_image\` accepts \`badgeid\`, \`expected_timemodified\` from \`badges_get\`, and one image source. Replacement is denied after activation or issuance.

**Security:** Creation requires \`moodle/badges:createbadge\`; replacement requires \`moodle/badges:configuredetails\`; listing and details require \`moodle/badges:viewbadges\` in the matching system/course context. URL downloads go through Moodle's SSRF-protected curl, reject non-HTTPS/redirects/private hosts and enforce a size cap. All WRITEs use the parent MCP preview/confirmation flow; Base64 values are never echoed in previews or result data. No badge is enabled or awarded automatically.
