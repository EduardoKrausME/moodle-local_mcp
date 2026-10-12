# Binary file uploads for MCP clients

## Purpose

MCP tool arguments are JSON; passing Base64 file content through the language model is neither
reliable nor efficient. Compatible clients can instead transfer bytes with authenticated HTTPS,
receive a short-lived `upload_id`, and use that identifier in subsequent MCP write tools.

The Moodle plugin implements a **separate upload endpoint**, not an MCP protocol-level binary
transport method. It does not automatically receive attachments from every ChatGPT client.

## Upload

The upload endpoint is `POST https://YOUR-MOODLE/local/mcp/upload.php` using
`multipart/form-data` with a field named `file`. Supply a bearer access token issued by this
Moodle MCP server for `server.php` or `write.php`, with the `mcp:write` scope.

```bash
curl --fail-with-body \
  -H "Authorization: Bearer $MOODLE_MCP_ACCESS_TOKEN" \
  -F "file=@./manual.pdf;filename=manual.pdf" \
  "https://YOUR-MOODLE/local/mcp/upload.php"
```

The response is HTTP 201:

```json
{
  "upload_id": "mcp_file_<opaque-random-token>",
  "filename": "manual.pdf",
  "mime_type": "application/pdf",
  "size_bytes": 12345,
  "expires_at": 1791840000
}
```

The example expiry and size are illustrative. No URL, filesystem location, Base64 body, or Moodle
draft item ID is exposed.

Upload a resource by calling `resource_create_activity` with `courseid`, `sectionnum`,
`name` and `file_upload_id` set to the receipt's `upload_id`. For H5P call
`h5p_create_activity` with `file_upload_id`; the filename must end in `.h5p` and the
contents must begin with the ZIP header. For an image use `set_course_image` with
`courseid` and `image_upload_id`. The existing Base64 inputs remain available for older clients.

Uploads are limited to 40 MiB each, 50 active files / 100 MiB per connection and expire after
one hour. Course images retain the stricter 5 MiB PNG/JPEG/WebP validation. These limits
also depend on PHP and web-server upload settings. OAuth revocation and scope changes take effect
on every request. The stored blob and identifier are bound to the original user, client and
connection and cannot be used by a different OAuth connection. Each consuming tool additionally
performs its normal Moodle course capability checks and confirmation flow.

Temporary files are stored in Moodle's private File API area and removed by the scheduled
cleanup task after expiry. An expired receipt cannot be reactivated.

## ChatGPT-specific capabilities

[OpenAI's Plugins reference](https://developers.openai.com/plugins/reference) documents
`window.openai.uploadFile`, `window.openai.selectFiles`, temporary download URLs, and
`_meta["openai/fileParams"]`. These are **client-specific features**, not proof that a ChatGPT
conversation or a generic MCP client can POST to an arbitrary upload endpoint using its OAuth
bearer token. A client or trusted integration must implement the transfer and pass the resulting
`upload_id` to Moodle. Do not pass ChatGPT's `fileId` as Moodle's `upload_id`, as they belong
to different systems.
