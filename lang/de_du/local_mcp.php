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
 * Language overrides for de_du.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

// Only strings that differ from the parent language (de) are defined here.
$string['authorizationdeniedbody'] = 'Melde dich mit einem aktiven Moodle-Konto an, um ChatGPT, Claude oder eine andere mit dem MCP-Protokoll kompatible KI-Anwendung zu autorisieren. Gastkonten können keine Verbindung herstellen.';
$string['chatgptsetupfinish'] = 'Lade diese Seite nach Abschluss der Autorisierung neu. Diese Anleitung verschwindet automatisch, sobald eine aktive ChatGPT-Verbindung erkannt wird.';
$string['chatgptsetupintro'] = 'Verbinde diese Moodle-Website über das MCP-Protokoll mit ChatGPT. Es sind noch keine aktiven ChatGPT-Verbindungen vorhanden. Folge diesen Schritten, um den sicheren Zugriff einzurichten.';
$string['chatgptsetupstep1body'] = 'Klicke auf die Schaltfläche unten, um ChatGPT in einem neuen Tab zu öffnen und dich anzumelden.';
$string['chatgptsetupstep2body'] = 'Öffne in ChatGPT im Web Plugins, klicke auf + und wähle Add custom MCP server. Nenne den Server Moodle MCP und wähle OAuth-Authentifizierung mit dynamischer Clientregistrierung (DCR).';
$string['chatgptsetupstep4body'] = 'Lies den Sicherheitshinweis, wähle I understand and want to continue und klicke auf Create as a plugin. Installiere das Plugin, falls du dazu aufgefordert wirst.';
$string['chatgptsetupstep5body'] = 'Öffne eine Unterhaltung in ChatGPT und verwende die Moodle-MCP-Integration. Melde dich auf Aufforderung mit einem aktiven Moodle-Konto (nicht als Gast) an und genehmige die angeforderten Lese- und Schreibberechtigungen. Die Werkzeuge sind durch deine Moodle-Rechte beschränkt.';
$string['connectionguideacknowledgement'] = 'Ich verstehe und möchte weitermachen';
$string['connectionguideacknowledgementhelp'] = 'Lies in ChatGPT den Sicherheitshinweis, aktiviere diese Bestätigung und schließe mit Create as plugin ab. Diese Seite zeigt die Felder nur beispielhaft.';
$string['connectionguideadvancedhelp'] = 'ChatGPT erkennt diese Endpunkte normalerweise automatisch. Falls du sie manuell eingeben musst, kopiere die folgenden Werte, insbesondere die Basis-URL des Autorisierungsservers mit der Endung /local/mcp/server.php.';
$string['connectionguideadvancedpreview'] = 'Prüfe erkannte OAuth-Endpunkte oder gib sie manuell in ChatGPT ein.';
$string['connectionguidechatgptstartintro'] = 'Öffne den Bereich Plugins und folge diesen Schritten, um diesen Moodle-MCP-Server hinzuzufügen.';
$string['connectionguidechatgptstartstep2'] = 'Klicke oben rechts auf Add.';
$string['connectionguidechatgptstartstep3'] = 'Wähle Add custom MCP server und verwende die URL im folgenden Formular.';
$string['connectionguideclaudestartintro'] = 'Füge dieses Moodle in den Connector-Einstellungen von Claude als benutzerdefinierten Connector hinzu.';
$string['connectionguideclaudestartstep2'] = 'Wähle Add custom connector und dann Web.';
$string['connectionguideclaudestartstep3'] = 'Gib die unten angezeigte MCP-Server-URL ein und verbinde dein Moodle-Konto über OAuth.';
$string['connectionguideclaudestep2'] = 'Benenne den Connector, füge die oben angezeigte MCP-Server-URL ein und fahre mit den Authentifizierungseinstellungen fort.';
$string['connectionguideclaudestep3'] = 'Wähle Sign in now oder Sign in when needed und unter OAuth client die Option Register automatically (DCR). Wähle nicht No sign in. Ein Clientgeheimnis ist nicht erforderlich.';
$string['connectionguideclaudestep4'] = 'Schließe das Hinzufügen des Connectors ab, klicke auf Connect, melde dich in Moodle an und erteile die angeforderten Berechtigungen. Aktiviere den Connector für deine Claude-Unterhaltung, bevor du die Werkzeuge verwendest.';
$string['connectionguideconnected'] = 'Eine aktive ChatGPT-OAuth-Verbindung wurde erkannt. Du kannst diese Anleitung verwenden, um eine weitere Verbindung einzurichten.';
$string['connectionguidecreatehelp'] = 'Klicke in ChatGPT auf Create as plugin, schließe die Installation ab, melde dich anschließend in Moodle an und genehmige die angeforderten LESE-/SCHREIB-Berechtigungen.';
$string['connectionguidedescriptionhelp'] = 'Beschreibe die Funktion des Servers, zum Beispiel: Zugriff auf Kurse, Nutzer und Aktivitäten in Moodle.';
$string['connectionguidefinalbody'] = 'Öffne einen Chat mit dem neuen Plugin, verbinde dich über OAuth und autorisiere den Zugriff in Moodle. Autorisierte OAuth-Clients und Verbindungen kannst du in der Moodle-MCP-Verwaltung prüfen.';
$string['connectionguideiconhelp'] = 'Klicke in ChatGPT auf das Pluszeichen und lade ein eigenes PNG-Symbol hoch. Verwende ein quadratisches Bild mit mindestens 256 × 256 Pixeln und höchstens 10 KB. Das Symbol ist optional, hilft aber bei der Erkennung deines Moodles.';
$string['connectionguideinstructionsintro'] = 'Ordne jedes nummerierte Feld dem Beispiel links zu.';
$string['connectionguideintro'] = 'Verbinde Moodle über das MCP-Protokoll mit ChatGPT. Folge den Feldern im Dialog Add custom MCP server. Diese Seite ist eine bebilderte Anleitung; die Verbindung wird in ChatGPT erstellt.';
$string['connectionguidenamehelp'] = 'Gib Moodle MCP oder einen eindeutigen Namen wie Moodle MCP – Meine Schule ein.';
$string['connectionguideoauthhelp'] = 'Wähle OAuth. Dieses Moodle MCP verwendet den OAuth-Autorisierungsablauf. Ein manuelles Token oder die Option No authentication ist nicht erforderlich.';
$string['connectionguideotherintro'] = 'Verwende diese Einstellungen mit einem Client, der entfernte MCP-Server über Streamable HTTP und den von Moodle geforderten OAuth-Ablauf unterstützt. Die Einstellungsnamen können abweichen.';
$string['connectionguideotherstep1'] = 'Füge einen entfernten MCP-Server mit dem Transport Streamable HTTP hinzu und gib die oben angezeigte HTTPS-URL ein. Verwende kein Moodle-Webservice-Token in der URL.';
$string['connectionguideotherstep2'] = 'Konfiguriere den OAuth Authorization Code Flow mit PKCE S256. Falls unterstützt, verwende die dynamische Clientregistrierung (RFC 7591).';
$string['connectionguideotherstep3'] = 'Verwende für die OAuth-Erkennung die Basis-URL des Autorisierungsservers mit der Endung /local/mcp/server.php. Für die manuelle Einrichtung verwende die Endpunkte unten und setze die OAuth-Ressource auf die MCP-Endpunkt-URL.';
$string['connectionguideotherstep4'] = 'Melde dich in Moodle an, genehmige mcp:read und bei Bedarf mcp:write. Prüfe die verfügbaren Werkzeuge und bestätige jeden Schreibvorgang einzeln.';
$string['connectionguidepreviewnotice'] = 'Vorschau der ChatGPT-Felder. Um die Verbindung einzurichten, öffne ChatGPT und fülle das Formular dort aus.';
$string['connectionguiderisknotice'] = 'Benutzerdefinierte MCP-Server können auf Daten im Rahmen der von dir erteilten Berechtigungen zugreifen. Verbinde dich nur mit vertrauenswürdigen Servern.';
$string['connectionguidesafetyhelp'] = 'Lies die Sicherheitswarnung und wähle I understand and want to continue nur, wenn du diesem Moodle-Server vertraust.';
$string['connectionguideurlhelp'] = 'Wähle Server URL (nicht Tunnel). Kopiere die Adresse aus dem Beispiel und füge sie in ChatGPT ein. Verwende die HTTPS-URL mit der Endung /local/mcp/server.php ohne zusätzliche Parameter.';
$string['dashboardactivityhelp'] = 'Letzte 7 Tage in deiner Zeitzone. Die Aktivität umfasst Abfragen, Vorschauen, ausstehende Genehmigungen und Fehler. Nur genehmigte und abgeschlossene Änderungen verändern Moodle-Daten.';
$string['oauthconsentfooter'] = 'Autorisiere nur Anwendungen, die du kennst und denen du vertraust.';
$string['oauthconsentpermissionsintro'] = 'Prüfe, welche Aktionen diese Anwendung in deinem Namen ausführen darf.';
$string['oauthconsentsecuritybody'] = 'Diese Verbindung kann die Berechtigungen deines Moodle-Kontos nicht überschreiten. Einige sensible Vorgänge erfordern eine zusätzliche Bestätigung. Du kannst den Zugriff später widerrufen.';
$string['oauthconsentsecuritytitle'] = 'Deine Moodle-Berechtigungen gelten weiterhin';
$string['oauthconsentwritesummary'] = 'ChatGPT, Claude oder einer anderen über MCP verbundenen Anwendung erlauben, Daten mit den für dein Konto verfügbaren Schreibwerkzeugen zu erstellen, zu ändern oder zu löschen; vorbehaltlich einer Bestätigung.';
