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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Create an entry in an existing Moodle glossary.
 *
 * @package   mcptool_glossary
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_glossary\write;

use context;
use context_module;
use local_mcp\security\authenticated_identity;
use local_mcp\write\tool\base_tool;

/** Delegate to the existing Moodle glossary external API, including its validation and events. */
final class create_entry extends base_tool {
    public function get_name(): string { return 'glossary_create_entry'; }
    public function get_title(): string { return 'Glossary: create entry'; }
    public function get_description(): string { return 'Add a concept and HTML definition to a glossary.'; }
    public function get_required_capability(): string { return 'mod/glossary:write'; }
    public function get_input_schema(): array {
        return $this->object_schema([
            'cmid' => ['type' => 'integer', 'minimum' => 1],
            'concept' => ['type' => 'string', 'minLength' => 1],
            'definition' => ['type' => 'string', 'minLength' => 1],
        ], ['cmid', 'concept', 'definition']);
    }
    public function resolve_context(array $a): context {
        return context_module::instance((int)$a['cmid'], MUST_EXIST);
    }
    public function execute(array $a, authenticated_identity $identity): array {
        global $DB;
        $cm = get_coursemodule_from_id('glossary', (int)$a['cmid'], 0, false, MUST_EXIST);
        $glossary = $DB->get_record('glossary', ['id' => $cm->instance], '*', MUST_EXIST);
        $result = \mod_glossary\external::add_entry(
            (int)$glossary->id,
            clean_param($a['concept'], PARAM_TEXT),
            clean_text($a['definition'], FORMAT_HTML),
            FORMAT_HTML
        );
        return [
            'entryid' => (int)$result['entryid'],
            'cmid' => (int)$cm->id,
            'glossaryid' => (int)$glossary->id,
        ];
    }
}
