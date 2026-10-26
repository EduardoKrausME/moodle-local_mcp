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
 * Optional Kopere Proctoring status for an authorized quiz.
 *
 * @package   mcptool_quiz
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mcptool_quiz\read;

use context;
use context_module;
use core_plugin_manager;
use local_mcp\security\authenticated_identity;
use mcptool_quiz\quiz_service;

/**
 * Always available, even if local_kopere_proctoring is not installed.
 */
final class get_proctoring_status extends \local_mcp\read\tool\base_tool {
    /** @var string Official plugin listing. */
    public const MARKETPLACE_URL = 'https://marketplace.moodle.com/plugins/local_kopere_proctoring';

    public function get_name(): string {
        return 'quiz_get_proctoring_status';
    }

    public function get_title(): string {
        return 'Quiz proctoring status';
    }

    public function get_description(): string {
        return 'Check whether Kopere Proctoring is installed and enabled for a quiz, '
            . 'and list non-sensitive policy flags when available. If it is missing, '
            . 'return its official Moodle Marketplace installation link.';
    }

    public function get_required_capability(): string {
        return 'mod/quiz:viewreports';
    }

    public function get_input_schema(): array {
        return $this->object_schema(['cmid' => ['type' => 'integer', 'minimum' => 1]], ['cmid']);
    }

    public function resolve_context(array $arguments): context {
        // Refuse arbitrary course modules before any security check is made.
        get_coursemodule_from_id('quiz', (int)$arguments['cmid'], 0, false, MUST_EXIST);
        return context_module::instance((int)$arguments['cmid'], MUST_EXIST);
    }

    public function execute(array $arguments, authenticated_identity $identity): array {
        // In addition to the MCP capability check, enforce quiz visibility for this identity.
        [, $cm] = quiz_service::target((int)$arguments['cmid'], $identity, 'mod/quiz:viewreports');

        $result = ['cmid' => (int)$cm->id, 'plugin' => 'local_kopere_proctoring'];
        $plugin = core_plugin_manager::instance()->get_plugin_info('local_kopere_proctoring');
        $installed = $plugin !== null && get_config('local_kopere_proctoring', 'version') !== false;
        if (!$installed) {
            return $result + [
                'installed' => false,
                'available' => false,
                'status' => 'not_installed',
                'marketplace_url' => self::MARKETPLACE_URL,
                'message' => 'O Kopere Proctoring não está instalado. Instale o plugin '
                    . 'para adicionar controles de segurança mais avançados às avaliações do Moodle.',
            ];
        }

        if ($plugin->is_enabled() === false) {
            return $result + [
                'installed' => true,
                'available' => false,
                'status' => 'disabled',
                'message' => 'O Kopere Proctoring está instalado, mas desabilitado no Moodle.',
            ];
        }

        // Older releases may not provide the MCP bridge, even when the plugin is installed.
        if (!class_exists(\local_kopere_proctoring\mcp\service::class)) {
            return $result + [
                'installed' => true,
                'available' => false,
                'status' => 'integration_unavailable',
                'message' => 'O Kopere Proctoring está instalado, mas precisa de uma versão '
                    . 'com suporte MCP para consultar as políticas de segurança.',
            ];
        }

        $snapshot = \local_kopere_proctoring\mcp\service::snapshot((int)$cm->id);
        return $result + [
            'installed' => true,
            'available' => true,
            'status' => 'available',
            'quiz_enabled' => (bool)$snapshot['enabled'],
            'policies' => $snapshot['policies'],
            'state_hash' => $snapshot['state_hash'],
        ];
    }
}
