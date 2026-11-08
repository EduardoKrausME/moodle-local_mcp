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
 * Language overrides for de_ch.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

// Only strings that differ from the parent language (de) are defined here.
$string['auditprivacy'] = 'Protokolliert Werkzeugaufrufe von ChatGPT, Claude oder anderen KI-Anwendungen über MCP, einschliesslich Bestätigungen, Fehlern und unkritischen Entitäts-IDs. Gesprächsinhalte, Zugangsdaten und Inhaltswerte werden nicht gespeichert. Nur an diese Moodle-Website übermittelte Vorgänge können protokolliert werden.';
$string['connectionguideacknowledgementhelp'] = 'Lesen Sie in ChatGPT den Sicherheitshinweis, aktivieren Sie diese Bestätigung und schliessen Sie mit Create as plugin ab. Diese Seite zeigt die Felder nur beispielhaft.';
$string['connectionguidecreatehelp'] = 'Klicken Sie in ChatGPT auf Create as plugin, schliessen Sie die Installation ab, melden Sie sich anschliessend in Moodle an und genehmigen Sie die angeforderten LESE-/SCHREIB-Berechtigungen.';
$string['privacy:metadata:mcp_client'] = 'Autorisierte KI-Anwendungen wie ChatGPT und Claude, die über MCP verbunden sind, können die von aktivierten Werkzeugen zurückgegebenen Informationen gemäss den Nutzerberechtigungen und angeforderten Vorgängen erhalten.';
$string['settingsdebuglogging_desc'] = 'Protokolliert strukturierte MCP-Aufrufphasen, Ergebnisse und bereinigte Ausnahmemetadaten. Standardmässig deaktiviert. Änderungen an config.php sind nicht erforderlich. Nur vorübergehend zur Fehlersuche aktivieren und den Zugriff auf PHP-Protokolle beschränken.';
$string['settingslogmessages_desc'] = 'Optionale JSON-RPC-Body-Protokollierung an den Endpunkten server/read/write. Standardmässig deaktiviert und nur aktiv, wenn auch die MCP-Diagnoseprotokollierung eingeschaltet ist. Bekannte Geheimnisse und Binärdaten werden maskiert, Moodle-Personendaten können dennoch erscheinen. Nur vorübergehend aktivieren und PHP-Protokolle schützen.';
$string['connectionguideclaudestep4'] = 'Schliessen Sie das Hinzufügen des Connectors ab, klicken Sie auf Connect, melden Sie sich in Moodle an und erteilen Sie die angeforderten Berechtigungen. Aktivieren Sie den Connector für Ihre Claude-Unterhaltung, bevor Sie die Werkzeuge verwenden.';
