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
 * local_mcp.php
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$string['audit'] = 'Auditlogboek';
$string['auditclient'] = 'OAuth-client';
$string['auditconfirmed'] = 'Bevestigd';
$string['auditconnection'] = 'Verbindings-ID';
$string['auditduration'] = 'Duur (ms)';
$string['auditevent'] = 'Gebeurtenis';
$string['auditexport'] = 'Gefilterde CSV exporteren (maximaal 5.000 rijen)';
$string['auditnone'] = 'Geen overeenkomende auditregistraties.';
$string['auditobjects'] = 'Veldnamen / entiteits-ID\'s / aantallen';
$string['auditonlychatgpt'] = 'Alleen ChatGPT-verbindingen';
$string['auditprivacy'] = 'Registreert toolaanroepen van ChatGPT, Claude of andere AI-toepassingen via MCP, waaronder bevestigingen, fouten en veilige entiteits-ID\'s. Gespreksteksten, inloggegevens en inhoudswaarden worden niet opgeslagen. Alleen bewerkingen die naar deze Moodle-site zijn verzonden kunnen worden gecontroleerd.';
$string['auditrequestid'] = 'Verzoek-ID';
$string['auditresults'] = 'Overeenkomende auditregistraties: {$a}';
$string['auditside'] = 'Zijde';
$string['auditstatus'] = 'Status';
$string['audittool'] = 'Tool';
$string['audituser'] = 'Gebruiker';
$string['audituserid'] = 'Moodle-gebruikers-ID';
$string['auditwhen'] = 'Tijdstip';
$string['authorizationdeniedbody'] = 'Meld je aan met een actief Moodle-account om ChatGPT, Claude of een andere AI-toepassing die het MCP-protocol ondersteunt te autoriseren. Gastaccounts kunnen geen verbinding maken.';
$string['authorizationdeniedtitle'] = 'Verbinding niet geautoriseerd';
$string['authorize'] = 'Verbinding autoriseren';
$string['authorizeheading'] = '{$a} verbinden met Moodle';
$string['authorizeintro'] = '{$a} vraagt toegang tot deze Moodle-site.';
$string['backtodashboard'] = 'Terug naar dashboard';
$string['cachedef_ratelimit'] = 'Cache voor snelheidslimieten';
$string['cachedef_tooldefinitions'] = 'Cache voor tooldefinities';
$string['cancel'] = 'Annuleren';
$string['chatgptsetupcopied'] = 'Server-URL gekopieerd.';
$string['chatgptsetupcopy'] = 'URL kopiëren';
$string['chatgptsetupcopyfallback'] = 'Selecteer en kopieer de gemarkeerde URL.';
$string['chatgptsetupdcrdisabled'] = 'Dynamische OAuth-clientregistratie is uitgeschakeld. Schakel deze in voordat je ChatGPT verbindt.';
$string['chatgptsetupdiscoveryhelp'] = 'Controleer bij problemen met OAuth-detectie de metagegevens die Moodle MCP rechtstreeks op deze URL publiceert:';
$string['chatgptsetupfinish'] = 'Laad deze pagina opnieuw nadat je de autorisatie hebt voltooid. Deze handleiding verdwijnt automatisch zodra een actieve ChatGPT-verbinding is gevonden.';
$string['chatgptsetuphttpsdisabled'] = 'Deze Moodle-site heeft geen HTTPS-URL ingesteld. Om ChatGPT via MCP te verbinden moet de server via internet bereikbaar zijn met HTTPS.';
$string['chatgptsetupintro'] = 'Verbind deze Moodle-site met ChatGPT via het MCP-protocol. Er zijn nog geen actieve ChatGPT-verbindingen; volg deze stappen om veilige toegang in te stellen.';
$string['chatgptsetupnewtab'] = 'opent in een nieuw tabblad';
$string['chatgptsetupopenchatgpt'] = 'ChatGPT openen';
$string['chatgptsetupopensettings'] = 'Moodle MCP-instellingen openen';
$string['chatgptsetupserverlabel'] = 'MCP-server-URL';
$string['chatgptsetupstep1body'] = 'Klik op de onderstaande knop om ChatGPT in een nieuw tabblad te openen en je aan te melden.';
$string['chatgptsetupstep1title'] = 'ChatGPT openen';
$string['chatgptsetupstep2body'] = 'Open in ChatGPT op het web Plugins, klik op + en kies Add custom MCP server. Geef de naam Moodle MCP op en kies OAuth-verificatie met dynamische clientregistratie (DCR).';
$string['chatgptsetupstep2title'] = 'Een aangepaste MCP-server toevoegen';
$string['chatgptsetupstep3body'] = 'Plak het volgende adres in het veld Server URL in ChatGPT:';
$string['chatgptsetupstep3title'] = 'De URL van deze Moodle-server invoeren';
$string['chatgptsetupstep4body'] = 'Lees de beveiligingswaarschuwing, selecteer I understand and want to continue en klik op Create as a plugin. Installeer de aangemaakte plugin als daarom wordt gevraagd.';
$string['chatgptsetupstep4title'] = 'Plugin maken en installeren';
$string['chatgptsetupstep5body'] = 'Open een gesprek in ChatGPT en gebruik de Moodle MCP-integratie. Meld je desgevraagd aan met een actief Moodle-account (geen gastaccount) en keur de gevraagde lees- en schrijfrechten goed. Tools blijven beperkt door je Moodle-rechten.';
$string['chatgptsetupstep5title'] = 'Toegang tot Moodle autoriseren';
$string['chatgptsetuptitle'] = 'Je Moodle-site met ChatGPT verbinden';
$string['chatgptsetuptroubleshooting'] = 'Problemen met OAuth-detectie oplossen';
$string['clientcolclientid'] = 'Client-ID';
$string['clientcolcreated'] = 'Aangemaakt';
$string['clientcoldynamic'] = 'Dynamisch';
$string['clientcolenabled'] = 'Ingeschakeld';
$string['clientcollastused'] = 'Laatst gebruikt';
$string['clientcolname'] = 'Naam';
$string['clientcoltype'] = 'Type';
$string['connectionactions'] = 'Acties';
$string['connectionactivelist'] = 'Actieve OAuth-verbindingen';
$string['connectionclient'] = 'Client';
$string['connectioncreated'] = 'Aangemaakt';
$string['connectionguideoauthresource'] = 'Bron';
$string['connectionguideoauthserverbase'] = 'Basis-URL van de autorisatieserver';
$string['connectionguideoauthregisterurl'] = 'Registratie-URL';
$string['connectionguideoauthtokenurl'] = 'Token-URL';
$string['connectionguideoauthauthorizeurl'] = 'Autorisatie-URL';
$string['connectionguideacknowledgementhelp'] = 'Lees in ChatGPT de beveiligingsmelding, vink deze bevestiging aan en rond af met Create as plugin. Deze pagina toont alleen een voorbeeld van de velden.';
$string['connectionguiderisknotice'] = 'Aangepaste MCP-servers kunnen gegevens benaderen binnen de rechten die je verleent. Maak alleen verbinding met servers die je vertrouwt.';
$string['connectionguideacknowledgement'] = 'Ik begrijp het en wil doorgaan';
$string['connectionguideadvanced'] = 'Geavanceerde OAuth-instellingen';
$string['connectionguideadvancedhelp'] = 'ChatGPT ontdekt deze eindpunten normaal gesproken automatisch. Als je ze handmatig moet invoeren, kopieer dan de onderstaande waarden, vooral de basis-URL van de autorisatieserver die eindigt op /local/mcp/server.php.';
$string['connectionguideadvancedpreview'] = 'Bekijk gedetecteerde OAuth-eindpunten of voer ze handmatig in ChatGPT in.';
$string['connectionguideauthentication'] = 'Verificatie';
$string['connectionguideconnected'] = 'Er is een actieve ChatGPT OAuth-verbinding gevonden. Je kunt deze instructies gebruiken om nog een verbinding in te stellen.';
$string['connectionguideconnection'] = 'Verbinding';
$string['connectionguidecreate'] = 'Als plugin aanmaken';
$string['connectionguidecreatehelp'] = 'Klik op Create as plugin in ChatGPT, voltooi de installatie, meld je aan bij Moodle en keur de gevraagde LEES-/SCHRIJF-rechten goed.';
$string['connectionguidedescription'] = 'Beschrijving (optioneel)';
$string['connectionguidedescriptionexample'] = 'Moodle-cursussen, activiteiten en tools openen via ChatGPT.';
$string['connectionguidedescriptionhelp'] = 'Beschrijf wat de server doet. Bijvoorbeeld: Toegang tot cursussen, gebruikers en activiteiten in Moodle.';
$string['connectionguidedocumentation'] = 'De volledige verbindingshandleiding lezen';
$string['connectionguideeyebrow'] = 'Moodle MCP · ChatGPT instellen';
$string['connectionguidefinalbody'] = 'Open een gesprek met de nieuwe plugin, maak verbinding via OAuth en autoriseer toegang in Moodle. Geautoriseerde OAuth-clients en verbindingen kun je bekijken in het Moodle MCP-beheer.';
$string['connectionguidefinaltitle'] = 'Na het aanmaken van de plugin';
$string['connectionguideformtitle'] = 'Aangepaste MCP-server toevoegen';
$string['connectionguideicon'] = 'Pictogram (optioneel)';
$string['connectionguideiconhelp'] = 'Klik op het plusteken in ChatGPT en upload je eigen PNG-pictogram. Gebruik een vierkant pictogram van minimaal 256 × 256 pixels en comprimeer het tot maximaal 10 KB. Het pictogram is optioneel, maar helpt je Moodle-site te herkennen.';
$string['connectionguideiconhint'] = 'Alleen PNG. Aanbevolen: 256 × 256 px of groter, maximaal 10 KB.';
$string['connectionguideinstructionsintro'] = 'Koppel elk genummerd veld aan het voorbeeld links.';
$string['connectionguideintro'] = 'Verbind Moodle met ChatGPT via het MCP-protocol. Volg de velden in het venster Add custom MCP server; deze pagina is een geïllustreerde handleiding en de verbinding wordt in ChatGPT aangemaakt.';
$string['connectionguidename'] = 'Naam';
$string['connectionguidenamehelp'] = 'Voer Moodle MCP in of een herkenbare naam, zoals Moodle MCP – Mijn school.';
$string['connectionguideoauthhelp'] = 'Kies OAuth. Deze Moodle MCP gebruikt de OAuth-autorisatiestroom; een handmatig token of de optie No authentication is niet nodig.';
$string['connectionguidepreviewnotice'] = 'Voorbeeld van de ChatGPT-velden. Open ChatGPT en vul daar het formulier in om verbinding te maken.';
$string['connectionguidesafety'] = 'Beveiligingsbevestiging';
$string['connectionguidesafetyhelp'] = 'Lees de beveiligingswaarschuwing en kies I understand and want to continue alleen als je deze Moodle-server vertrouwt.';
$string['connectionguidesteps'] = 'Wat je in elk veld moet invullen';
$string['connectionguidetitle'] = 'Je Moodle met ChatGPT verbinden';
$string['connectionguidetunnelmode'] = 'Tunnel';
$string['connectionguideurlhelp'] = 'Selecteer Server URL (niet Tunnel). Kopieer het voorbeeldadres en plak het in ChatGPT. Gebruik de HTTPS-URL die eindigt op /local/mcp/server.php, zonder extra parameters.';
$string['connectionguideurlmode'] = 'Server-URL';
$string['connectionlastused'] = 'Laatst gebruikt';
$string['connectionnoneactive'] = 'Er zijn geen actieve OAuth-verbindingen.';
$string['connectionrevoke'] = 'Toegang intrekken';
$string['connectionrevoked'] = 'De verbinding is ingetrokken en de bijbehorende tokens zijn ongeldig gemaakt.';
$string['connections'] = 'Verbindingen';
$string['connectionscopes'] = 'Scopes';
$string['connectionuser'] = 'Gebruiker';
$string['dashboard'] = 'Dashboard';
$string['dashboardactivitychart'] = 'Opvragingen en wijzigingen per dag';
$string['dashboardactivityheading'] = 'Toolactiviteit';
$string['dashboardactivityhelp'] = 'De afgelopen 7 dagen in jouw tijdzone. Activiteit omvat opvragingen, voorbeelden, wachtende goedkeuringen en fouten; alleen goedgekeurde en afgeronde wijzigingen passen Moodle-gegevens aan.';
$string['dashboardcalls'] = 'Aanroepen';
$string['dashboardclients'] = 'Actieve OAuth-clients';
$string['dashboardconnections'] = 'Actieve verbindingen';
$string['dashboarddestructivetoday'] = 'Vandaag uitgevoerde destructieve wijzigingen';
$string['dashboardexamples'] = 'Voorbeelden van beschikbare acties';
$string['dashboardmoretools'] = '+{$a} meer';
$string['dashboardnoactivity'] = 'In de afgelopen 7 dagen zijn geen bewerkingen van ChatGPT, Claude of andere via MCP verbonden toepassingen geregistreerd.';
$string['dashboardoverview'] = 'Overzicht AI-integratie (MCP)';
$string['dashboardplugin'] = 'Plugin';
$string['dashboardpluginschart'] = 'Beschikbare wijzigingsacties per plugin';
$string['dashboardquicklinks'] = 'Snelkoppelingen dashboard';
$string['dashboardreadtoday'] = 'Opvragingen vandaag';
$string['dashboardstatuschart'] = 'Status van aanroepen (afgelopen 7 dagen)';
$string['dashboardstatuserror'] = 'Fout';
$string['dashboardstatusok'] = 'Succesvol voltooid';
$string['dashboardstatuspending'] = 'Wacht op bevestiging';
$string['dashboardstatuspreview'] = 'Proefuitvoering';
$string['dashboardtodaydescription'] = 'Status van de vandaag geregistreerde AI-verbindingen en bewerkingen.';
$string['dashboardtools'] = 'Tools';
$string['dashboardtotaltools'] = 'Beschikbare wijzigingsacties';
$string['dashboardviewall'] = 'Alle beschikbare wijzigingsacties bekijken';
$string['dashboardwritecompletedtoday'] = 'Vandaag voltooide wijzigingen';
$string['dashboardwritesummary'] = 'Beschikbare wijzigingsacties per plugin';
$string['dashboardwritesummaryhelp'] = 'Wijzigingsacties die automatisch zijn gevonden in ingeschakelde Moodle-plugins.';
$string['dashboardwritetoday'] = 'Wijzigingsverzoeken vandaag';
$string['error'] = 'MCP API-fout';
$string['importgiftquestionbank'] = 'GIFT-vragenbank importeren';
$string['importgiftquestionbank_desc'] = 'Importeer maximaal 500 UTF-8 GIFT-vragen in een bestaande categorie (categoryid) of maak een nieuwe categorie (name) aan, in één bevestigde bewerking.';
$string['mcp:manage'] = 'Moodle MCP beheren';
$string['newconnection'] = 'Nieuwe verbinding';
$string['oauthclientdisable'] = 'Client uitschakelen';
$string['oauthclientdisabled'] = 'De OAuth-client is uitgeschakeld en alle verbindingen en tokens zijn ingetrokken.';
$string['oauthclients'] = 'OAuth-clients';
$string['oauthconsentapplication'] = 'Externe toepassing';
$string['oauthconsentconnection'] = 'Verbinding tussen de externe toepassing en deze Moodle-site';
$string['oauthconsentdestination'] = 'Jouw Moodle-site';
$string['oauthconsenteyebrow'] = 'OAuth-autorisatieverzoek';
$string['oauthconsentfooter'] = 'Autoriseer alleen toepassingen die je herkent en vertrouwt.';
$string['oauthconsentpermissions'] = 'Gevraagde rechten';
$string['oauthconsentpermissionsintro'] = 'Controleer wat deze toepassing namens jou mag doen.';
$string['oauthconsentreadsummary'] = 'Sta ChatGPT, Claude of een andere via MCP verbonden toepassing toe om informatie te bekijken die beschikbaar is via alleen-lezen tools in Moodle.';
$string['oauthconsentsecuritybody'] = 'Deze verbinding kan de rechten van je Moodle-account niet overschrijden. Sommige gevoelige bewerkingen vereisen extra bevestiging. Je kunt de toegang later intrekken.';
$string['oauthconsentsecuritytitle'] = 'Je Moodle-rechten blijven van kracht';
$string['oauthconsenttitle'] = 'Nieuwe verbinding autoriseren';
$string['oauthconsentwritesummary'] = 'Sta ChatGPT, Claude of een andere via MCP verbonden toepassing toe om gegevens aan te maken, te wijzigen of te verwijderen met de schrijftools die je account mag gebruiken, na bevestiging.';
$string['pluginname'] = 'Moodle MCP';
$string['privacy:metadata:local_mcp_access_token'] = 'Slaat identificaties van OAuth-toegangstokens, gebruikerskoppelingen, scopes, gebruikstijden en IP-adressen op.';
$string['privacy:metadata:local_mcp_audit'] = 'Slaat beveiligings- en bewerkingsmetagegevens over MCP-activiteit op.';
$string['privacy:metadata:local_mcp_auth_code'] = 'Slaat metagegevens op van eenmalige OAuth-autorisatiecodes die aan een gebruiker zijn gekoppeld.';
$string['privacy:metadata:local_mcp_confirm'] = 'Slaat aan gebruikers gekoppelde bevestigingsregistraties voor MCP-schrijfbewerkingen op.';
$string['privacy:metadata:local_mcp_connections'] = 'Slaat geautoriseerde MCP-verbindingen op.';
$string['privacy:metadata:local_mcp_refresh_token'] = 'Slaat metagegevens van OAuth-vernieuwingstokens, gebruikerskoppelingen en tijdstempels van de levenscyclus op.';
$string['privacy:metadata:mcp_client'] = 'Geautoriseerde AI-toepassingen zoals ChatGPT en Claude die via MCP zijn verbonden, kunnen de informatie ontvangen die ingeschakelde tools teruggeven volgens de gebruikersrechten en gevraagde bewerkingen.';
$string['privacy:metadata:mcp_client:activities'] = 'Geautoriseerde MCP-tools kunnen activiteiten, inzendingen en voltooiingsinformatie teruggeven.';
$string['privacy:metadata:mcp_client:courses'] = 'Geautoriseerde MCP-tools kunnen cursus- en inschrijvingsgegevens teruggeven.';
$string['privacy:metadata:mcp_client:grades'] = 'Geautoriseerde MCP-tools kunnen cijfers en beoordelingsinformatie teruggeven.';
$string['privacy:metadata:mcp_client:otherdata'] = 'Andere gegevens die via geïnstalleerde MCP-tools toegankelijk zijn, kunnen op verzoek van een geautoriseerde gebruiker worden teruggegeven.';
$string['privacy:metadata:mcp_client:userid'] = 'Moodle-gebruikers-ID\'s kunnen worden verzonden om de geautoriseerde gebruiker of resultaten te identificeren.';
$string['privacy:metadata:mcp_client:userprofile'] = 'Geautoriseerde MCP-tools kunnen gebruikersprofielgegevens teruggeven.';
$string['readapis'] = 'LEES-API\'s';
$string['readapiscount'] = 'LEES-tools: {$a}';
$string['readapisempty'] = 'Er zijn geen LEES-tools beschikbaar.';
$string['readapisviewall'] = 'Alle LEES-tools van deze plugin bekijken';
$string['readpermissions'] = 'Leesrechten';
$string['settings'] = 'Instellingen';
$string['settingsaccessttl'] = 'Levensduur toegangstoken';
$string['settingsaccessttl_desc'] = 'Standaardgeldigheidsduur van OAuth-toegangstokens.';
$string['settingsauditretention'] = 'Bewaartermijn auditgegevens (dagen)';
$string['settingscodettl'] = 'Levensduur autorisatiecode';
$string['settingscodettl_desc'] = 'Autorisatiecodes zijn kort geldig en kunnen maar één keer worden gebruikt.';
$string['settingsconfirmationttl'] = 'Geldigheidsduur bevestiging';
$string['settingsconfirmationttl_desc'] = 'Geldigheidsduur van eenmalige bevestigingstokens voor SCHRIJVEN.';
$string['settingslogmessages'] = 'MCP JSON-berichten vastleggen in PHP error_log';
$string['settingsdebuglogging'] = 'MCP-diagnosegebeurtenissen vastleggen in PHP error_log';
$string['settingsdebuglogging_desc'] = 'Legt gestructureerde stappen, resultaten en opgeschoonde foutgegevens van MCP-aanroepen vast. Standaard uitgeschakeld. Aanpassen van config.php is niet nodig. Schakel dit tijdelijk in bij foutopsporing en beperk de toegang tot PHP-logbestanden.';
$string['settingslogmessages_desc'] = 'Optioneel loggen van JSON-RPC-verzoekinhoud op de eindpunten server/read/write; standaard uitgeschakeld en alleen werkzaam als MCP-diagnoselogging ook is ingeschakeld. Bekende geheimen en binaire gegevens worden afgeschermd, maar persoonlijke Moodle-gegevens kunnen nog zichtbaar zijn. Gebruik dit tijdelijk, bescherm PHP-logbestanden en schakel het daarna uit.';
$string['settingsrateoauth'] = 'OAuth-verzoeken/minuut';
$string['settingsrateread'] = 'LEES-verzoeken/minuut';
$string['settingsratewrite'] = 'SCHRIJF-verzoeken/minuut';
$string['settingsrefreshttl'] = 'Levensduur vernieuwingstoken';
$string['settingsrefreshttl_desc'] = 'Standaardgeldigheidsduur van OAuth-vernieuwingstokens.';
$string['subplugintype_mcptool'] = 'MCP-activiteitstool';
$string['subplugintype_mcptool_plural'] = 'MCP-activiteitstools';
$string['writeapis'] = 'SCHRIJF-API\'s';
$string['writeapiscolcapability'] = 'Mogelijkheid';
$string['writeapiscolconfirmation'] = 'Bevestiging';
$string['writeapiscoldescription'] = 'Beschrijving';
$string['writeapiscoldestructive'] = 'Destructief';
$string['writeapiscoldryrun'] = 'Proefuitvoering';
$string['writeapiscolname'] = 'Naam';
$string['writeapiscount'] = 'SCHRIJF-tools: {$a}';
$string['writeapisempty'] = 'Er zijn geen SCHRIJF-tools beschikbaar.';
$string['writeapisonlyread'] = 'Geïnstalleerde plugins met alleen LEES-API\'s';
$string['writeapisonlyreadhelp'] = 'Deze integraties zijn via MCP LEZEN beschikbaar, niet via SCHRIJVEN. Open hun LEES-API-lijsten om alle tools te bekijken. De installatievolgorde verandert het API-type niet.';
$string['writepermissions'] = 'Schrijfrechten';

$string['connectionguidetablabel'] = 'Kies een AI-toepassing';
$string['connectionguidetabother'] = 'Andere AI';
$string['connectionguideclaudetitle'] = 'Moodle verbinden met Claude';
$string['connectionguideclaudeintro'] = 'Voeg in Claude een aangepaste webconnector toe die naar deze MCP-server verwijst. Moodle verzorgt de aanmelding via OAuth.';
$string['connectionguideclaudestep1'] = 'Open in Claude Customize > Connectors > Add custom connector. Een organisatie-eigenaar kan Organization settings > Connectors > Add > Custom > Web gebruiken.';
$string['connectionguideclaudestep2'] = 'Geef de connector een naam, plak de bovenstaande MCP-server-URL en ga door naar de verificatie-instellingen.';
$string['connectionguideclaudestep3'] = 'Kies Sign in now of Sign in when needed en selecteer onder OAuth client Register automatically (DCR). Kies niet No sign in; een clientgeheim is niet nodig.';
$string['connectionguideclaudestep4'] = 'Voltooi het toevoegen van de connector, klik op Connect, meld je aan bij Moodle en verleen de gevraagde scopes. Schakel de connector in voor je Claude-gesprek voordat je de tools gebruikt.';
$string['connectionguideclaudenote'] = 'Claude moet de MCP-server via HTTPS kunnen bereiken. Schrijftools vereisen de scope mcp:write, Moodle-rechten en expliciete bevestiging voordat wijzigingen worden aangebracht.';
$string['connectionguideopenclaude'] = 'Claude openen';
$string['connectionguideclaudedocs'] = 'Officiële Claude-documentatie';
$string['connectionguideothertitle'] = 'Andere AI-toepassingen verbinden';
$string['connectionguideotherintro'] = 'Gebruik deze instellingen met een client die externe MCP-servers via Streamable HTTP en de door Moodle vereiste OAuth-stroom ondersteunt. Namen van instellingen verschillen per toepassing.';
$string['connectionguideotherstep1'] = 'Voeg een externe MCP-server toe met Streamable HTTP en voer de bovenstaande HTTPS-URL in. Gebruik geen Moodle-webservicetoken in de URL.';
$string['connectionguideotherstep2'] = 'Configureer OAuth Authorization Code met PKCE S256. Gebruik indien ondersteund dynamische clientregistratie (RFC 7591) om de client automatisch te registreren.';
$string['connectionguideotherstep3'] = 'Gebruik voor OAuth-detectie de basis-URL van de autorisatieserver die eindigt op /local/mcp/server.php. Gebruik voor handmatige configuratie de onderstaande eindpunten en stel de OAuth-resource gelijk aan de MCP-eindpunt-URL.';
$string['connectionguideotherstep4'] = 'Meld je aan bij Moodle, keur mcp:read goed en indien nodig mcp:write. Controleer de beschikbare tools en bevestig elke schrijfbewerking afzonderlijk.';
$string['connectionguideotheradvanced'] = 'URL\'s voor clients zonder automatische detectie.';
$string['connectionguideothernote'] = 'Compatibiliteit hangt af van ondersteuning voor externe MCP, OAuth, PKCE en clientregistratie. local_mcp gebruikt geen tokens in URL\'s voor authenticatie en vereist geen toegang tot Nginx om deze instellingen handmatig in te voeren.';
$string['connectionguidechatgptstarttitle'] = 'Beginnen in ChatGPT';
$string['connectionguidechatgptstartintro'] = 'Open Plugins en volg deze stappen om deze Moodle MCP-server toe te voegen.';
$string['connectionguidechatgptstartstep1'] = 'Open ChatGPT Plugins met de knop hieronder.';
$string['connectionguidechatgptstartstep2'] = 'Klik rechtsboven op Add.';
$string['connectionguidechatgptstartstep3'] = 'Selecteer Add custom MCP server en gebruik de URL in het onderstaande formulier.';
$string['connectionguidechatgptstartaction'] = 'ChatGPT Plugins openen';
$string['connectionguideclaudestarttitle'] = 'Beginnen in Claude';
$string['connectionguideclaudestartintro'] = 'Voeg deze Moodle-site als aangepaste connector toe via de connectorinstellingen in Claude.';
$string['connectionguideclaudestartstep1'] = 'Open in Claude Customize > Connectors.';
$string['connectionguideclaudestartstep2'] = 'Selecteer Add custom connector en kies Web.';
$string['connectionguideclaudestartstep3'] = 'Voer de onderstaande MCP-server-URL in en verbind je Moodle-account via OAuth.';
