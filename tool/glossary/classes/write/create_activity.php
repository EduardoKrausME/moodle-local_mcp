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
 * Create a glossary course module.
 *
 * @package   mcptool_glossary
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_glossary\write;

use local_mcp\security\authenticated_identity;
use local_mcp\write\tool\activity_create_base;
use stdClass;

/** Moodle activity creation via standard module hooks. */
final class create_activity extends activity_create_base {
    protected function module_name(): string { return 'glossary'; }
    protected function extra_properties(): array {
        return [
            'allowduplicatedentries' => ['type' => 'boolean'],
            'defaultapproval' => ['type' => 'boolean'],
            'allowcomments' => ['type' => 'boolean'],
        ];
    }
    protected function configure(stdClass $info, array $a, authenticated_identity $identity): void {
        $info->displayformat = 'dictionary';
        $info->mainglossary = 0;
        $info->globalglossary = 0;
        $info->allowduplicatedentries = !empty($a['allowduplicatedentries']) ? 1 : 0;
        $info->defaultapproval = array_key_exists('defaultapproval', $a)
            ? (int)(bool)$a['defaultapproval'] : 1;
        $info->allowcomments = !empty($a['allowcomments']) ? 1 : 0;
        $info->allowprintview = 1;
        $info->usedynalink = 0;
        $info->showalphabet = 1;
        $info->showall = 1;
        $info->showspecial = 1;
        $info->entbypage = 10;
        $info->editalways = 0;
        $info->rsstype = 0;
        $info->rssarticles = 0;
        $info->assessed = 0;
        $info->scale = 0;
        $info->completionentries = 0;
    }
}
