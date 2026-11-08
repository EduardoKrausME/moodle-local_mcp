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

$string['audit'] = 'Auditoría';
$string['auditclient'] = 'Cliente OAuth';
$string['auditconfirmed'] = 'Confirmado';
$string['auditconnection'] = 'ID de conexión';
$string['auditduration'] = 'Duración (ms)';
$string['auditevent'] = 'Evento';
$string['auditexport'] = 'Exportar CSV filtrado (hasta 5000 filas)';
$string['auditnone'] = 'No se encontraron registros de auditoría.';
$string['auditobjects'] = 'Nombres de campos / ID de entidades / cantidades';
$string['auditonlychatgpt'] = 'Solo conexiones de ChatGPT';
$string['auditprivacy'] = 'Registra las llamadas a herramientas realizadas por ChatGPT, Claude u otras aplicaciones de IA a través de MCP, incluidas las confirmaciones, los errores y los ID seguros de las entidades. No almacena el texto de las conversaciones, las credenciales ni los valores del contenido. Solo se pueden auditar las operaciones enviadas a este sitio Moodle.';
$string['auditrequestid'] = 'ID de solicitud';
$string['auditresults'] = 'Registros de auditoría encontrados: {$a}';
$string['auditside'] = 'Lado';
$string['auditstatus'] = 'Estado';
$string['audittool'] = 'Herramienta';
$string['audituser'] = 'Usuario';
$string['audituserid'] = 'ID de usuario de Moodle';
$string['auditwhen'] = 'Fecha y hora';
$string['authorizationdeniedbody'] = 'Inicia sesión con una cuenta activa de Moodle para autorizar ChatGPT, Claude u otra aplicación de IA compatible con el protocolo MCP. Las cuentas de invitado no pueden conectarse.';
$string['authorizationdeniedtitle'] = 'Conexión no autorizada';
$string['authorize'] = 'Autorizar conexión';
$string['authorizeheading'] = 'Conectar {$a} a Moodle';
$string['authorizeintro'] = '{$a} solicita acceso a este Moodle.';
$string['backtodashboard'] = 'Volver al panel';
$string['cachedef_ratelimit'] = 'Caché de límite de solicitudes';
$string['cachedef_tooldefinitions'] = 'Caché de definiciones de herramientas';
$string['cancel'] = 'Cancelar';
$string['chatgptsetupcopied'] = 'URL del servidor copiada.';
$string['chatgptsetupcopy'] = 'Copiar URL';
$string['chatgptsetupcopyfallback'] = 'Selecciona y copia la URL resaltada.';
$string['chatgptsetupdcrdisabled'] = 'El registro dinámico de clientes OAuth está desactivado. Actívalo antes de conectar ChatGPT.';
$string['chatgptsetupdiscoveryhelp'] = 'Si falla el descubrimiento de OAuth, consulta los metadatos publicados directamente por Moodle MCP en esta URL:';
$string['chatgptsetupfinish'] = 'Después de completar la autorización, vuelve a cargar esta página. Esta guía desaparecerá automáticamente cuando se detecte una conexión activa de ChatGPT.';
$string['chatgptsetuphttpsdisabled'] = 'Este sitio Moodle no tiene configurada una URL HTTPS. Para conectar ChatGPT mediante el protocolo MCP, el servidor debe estar accesible desde Internet por HTTPS.';
$string['chatgptsetupintro'] = 'Conecta este sitio Moodle a ChatGPT mediante el protocolo MCP. Todavía no hay conexiones activas de ChatGPT; sigue estos pasos para configurar un acceso seguro.';
$string['chatgptsetupnewtab'] = 'se abre en una pestaña nueva';
$string['chatgptsetupopenchatgpt'] = 'Abrir ChatGPT';
$string['chatgptsetupopensettings'] = 'Abrir configuración de Moodle MCP';
$string['chatgptsetupserverlabel'] = 'URL del servidor MCP';
$string['chatgptsetupstep1body'] = 'Haz clic en el botón siguiente para abrir ChatGPT en otra pestaña e iniciar sesión.';
$string['chatgptsetupstep1title'] = 'Abrir ChatGPT';
$string['chatgptsetupstep2body'] = 'En ChatGPT para la web, abre Plugins, haz clic en + y selecciona Agregar servidor MCP personalizado. Asígnale el nombre Moodle MCP y elige la autenticación OAuth con registro dinámico de clientes (DCR).';
$string['chatgptsetupstep2title'] = 'Agregar un servidor MCP personalizado';
$string['chatgptsetupstep3body'] = 'Pega la siguiente dirección en el campo URL del servidor de ChatGPT:';
$string['chatgptsetupstep3title'] = 'Introducir la URL de este servidor Moodle';
$string['chatgptsetupstep4body'] = 'Lee la advertencia de seguridad, selecciona Entiendo y deseo continuar y haz clic en Crear como plugin. Instala el plugin creado si se te solicita.';
$string['chatgptsetupstep4title'] = 'Crear e instalar el plugin';
$string['chatgptsetupstep5body'] = 'Abre una conversación en ChatGPT y utiliza la integración Moodle MCP. Cuando se te solicite, inicia sesión con una cuenta activa de Moodle (no de invitado) y aprueba los permisos de lectura y escritura solicitados. Las herramientas respetan tus permisos de Moodle.';
$string['chatgptsetupstep5title'] = 'Autorizar acceso a Moodle';
$string['chatgptsetuptitle'] = 'Conectar tu sitio Moodle a ChatGPT';
$string['chatgptsetuptroubleshooting'] = 'Solucionar problemas de descubrimiento de OAuth';
$string['clientcolclientid'] = 'ID de cliente';
$string['clientcolcreated'] = 'Creado';
$string['clientcoldynamic'] = 'Dinámico';
$string['clientcolenabled'] = 'Habilitado';
$string['clientcollastused'] = 'Último uso';
$string['clientcolname'] = 'Nombre';
$string['clientcoltype'] = 'Tipo';
$string['connectionactions'] = 'Acciones';
$string['connectionactivelist'] = 'Conexiones OAuth activas';
$string['connectionclient'] = 'Cliente';
$string['connectioncreated'] = 'Creado';
$string['connectionguideoauthresource'] = 'Recurso';
$string['connectionguideoauthserverbase'] = 'URL base del servidor de autorización';
$string['connectionguideoauthregisterurl'] = 'URL de registro';
$string['connectionguideoauthtokenurl'] = 'URL de token';
$string['connectionguideoauthauthorizeurl'] = 'URL de autorización';
$string['connectionguideacknowledgementhelp'] = 'En ChatGPT, lee el aviso de seguridad, marca esta confirmación y termina con Crear como plugin. Esta página solo ilustra los campos.';
$string['connectionguiderisknotice'] = 'Los servidores MCP personalizados pueden acceder a los datos permitidos por los permisos que concedas. Conecta únicamente servidores en los que confíes.';
$string['connectionguideacknowledgement'] = 'Entiendo y deseo continuar';
$string['connectionguideadvanced'] = 'Configuración avanzada de OAuth';
$string['connectionguideadvancedhelp'] = 'ChatGPT suele descubrir automáticamente estos puntos de acceso. Si necesitas introducirlos manualmente, copia los valores siguientes, especialmente la URL base del servidor de autorización que termina en /local/mcp/server.php.';
$string['connectionguideadvancedpreview'] = 'Consulta los puntos de acceso OAuth descubiertos o introdúcelos manualmente en ChatGPT.';
$string['connectionguideauthentication'] = 'Autenticación';
$string['connectionguideconnected'] = 'Se ha detectado una conexión OAuth activa de ChatGPT. Puedes seguir estas instrucciones para configurar otra conexión.';
$string['connectionguideconnection'] = 'Conexión';
$string['connectionguidecreate'] = 'Crear como plugin';
$string['connectionguidecreatehelp'] = 'Haz clic en Crear como plugin en ChatGPT, completa la instalación y después inicia sesión en Moodle y aprueba los permisos de LECTURA/ESCRITURA solicitados.';
$string['connectionguidedescription'] = 'Descripción (opcional)';
$string['connectionguidedescriptionexample'] = 'Accede a cursos, actividades y herramientas de Moodle a través de ChatGPT.';
$string['connectionguidedescriptionhelp'] = 'Describe qué hace el servidor. Por ejemplo: Acceso a cursos, usuarios y actividades en Moodle.';
$string['connectionguidedocumentation'] = 'Leer la guía completa de conexión';
$string['connectionguideeyebrow'] = 'Moodle MCP · Configuración de ChatGPT';
$string['connectionguidefinalbody'] = 'Abre un chat con el plugin nuevo, conéctate mediante OAuth y autoriza el acceso en Moodle. Puedes revisar los clientes OAuth y las conexiones autorizadas desde el área de administración de Moodle MCP.';
$string['connectionguidefinaltitle'] = 'Después de crear el plugin';
$string['connectionguideformtitle'] = 'Agregar servidor MCP personalizado';
$string['connectionguideicon'] = 'Icono (opcional)';
$string['connectionguideiconhelp'] = 'Haz clic en el signo más de ChatGPT y sube tu propio icono PNG. Utiliza un icono cuadrado de al menos 256 × 256 píxeles y comprímelo a 10 KB o menos. Es opcional, pero ayuda a identificar tu Moodle.';
$string['connectionguideiconhint'] = 'Solo PNG. Recomendado: 256 × 256 px o más, hasta 10 KB.';
$string['connectionguideinstructionsintro'] = 'Relaciona cada campo numerado con el ejemplo de la izquierda.';
$string['connectionguideintro'] = 'Conecta Moodle a ChatGPT mediante el protocolo MCP. Sigue los campos que aparecen en el cuadro Agregar servidor MCP personalizado; esta página es una guía ilustrada y la conexión se crea en ChatGPT.';
$string['connectionguidename'] = 'Nombre';
$string['connectionguidenamehelp'] = 'Introduce Moodle MCP o un nombre distintivo, como Moodle MCP – Mi Escuela.';
$string['connectionguideoauthhelp'] = 'Elige OAuth. Este Moodle MCP utiliza el flujo de autorización OAuth; no necesitas un token manual ni la opción Sin autenticación.';
$string['connectionguidepreviewnotice'] = 'Vista previa de los campos de ChatGPT. Para crear la conexión, abre ChatGPT y completa allí el formulario.';
$string['connectionguidesafety'] = 'Confirmación de seguridad';
$string['connectionguidesafetyhelp'] = 'Lee la advertencia de seguridad y selecciona Entiendo y deseo continuar solo si confías en este servidor Moodle.';
$string['connectionguidesteps'] = 'Qué introducir en cada campo';
$string['connectionguidetitle'] = 'Conectar tu Moodle a ChatGPT';
$string['connectionguidetunnelmode'] = 'Túnel';
$string['connectionguideurlhelp'] = 'Selecciona URL del servidor (no Túnel). Copia la dirección del ejemplo y pégala en ChatGPT. Utiliza la URL HTTPS que termina en /local/mcp/server.php, sin parámetros adicionales.';
$string['connectionguideurlmode'] = 'URL del servidor';
$string['connectionlastused'] = 'Último uso';
$string['connectionnoneactive'] = 'No hay conexiones OAuth activas.';
$string['connectionrevoke'] = 'Revocar acceso';
$string['connectionrevoked'] = 'Se revocó la conexión y se invalidaron sus tokens.';
$string['connections'] = 'Conexiones';
$string['connectionscopes'] = 'Ámbitos';
$string['connectionuser'] = 'Usuario';
$string['dashboard'] = 'Panel';
$string['dashboardactivitychart'] = 'Consultas y cambios por día';
$string['dashboardactivityheading'] = 'Actividad de las herramientas';
$string['dashboardactivityhelp'] = 'Últimos 7 días en tu zona horaria. La actividad incluye consultas, vistas previas, aprobaciones pendientes y errores; solo los cambios aprobados y completados modifican los datos de Moodle.';
$string['dashboardcalls'] = 'Llamadas';
$string['dashboardclients'] = 'Clientes OAuth activos';
$string['dashboardconnections'] = 'Conexiones activas';
$string['dashboarddestructivetoday'] = 'Cambios destructivos completados hoy';
$string['dashboardexamples'] = 'Ejemplos de acciones disponibles';
$string['dashboardmoretools'] = '+{$a} más';
$string['dashboardnoactivity'] = 'No se han auditado operaciones de ChatGPT, Claude u otras aplicaciones conectadas por MCP en los últimos 7 días.';
$string['dashboardoverview'] = 'Resumen de la integración de IA (MCP)';
$string['dashboardplugin'] = 'Plugin';
$string['dashboardpluginschart'] = 'Acciones de cambio disponibles por plugin';
$string['dashboardquicklinks'] = 'Accesos rápidos del panel';
$string['dashboardreadtoday'] = 'Consultas de hoy';
$string['dashboardstatuschart'] = 'Estados de las llamadas (últimos 7 días)';
$string['dashboardstatuserror'] = 'Error';
$string['dashboardstatusok'] = 'Completado correctamente';
$string['dashboardstatuspending'] = 'Pendiente de confirmación';
$string['dashboardstatuspreview'] = 'Simulación';
$string['dashboardtodaydescription'] = 'Estado de las conexiones y operaciones de aplicaciones de IA registradas hoy.';
$string['dashboardtools'] = 'Herramientas';
$string['dashboardtotaltools'] = 'Acciones de cambio disponibles';
$string['dashboardviewall'] = 'Ver todas las acciones de cambio disponibles';
$string['dashboardwritecompletedtoday'] = 'Cambios completados hoy';
$string['dashboardwritesummary'] = 'Acciones de cambio disponibles por plugin';
$string['dashboardwritesummaryhelp'] = 'Acciones de cambio descubiertas automáticamente en los plugins de Moodle habilitados.';
$string['dashboardwritetoday'] = 'Solicitudes de cambio de hoy';
$string['error'] = 'Error de la API MCP';
$string['importgiftquestionbank'] = 'Importar banco de preguntas GIFT';
$string['importgiftquestionbank_desc'] = 'Importa hasta 500 preguntas GIFT en UTF-8 a una categoría existente (categoryid) o crea una nueva (name), en una única operación confirmada.';
$string['mcp:manage'] = 'Administrar Moodle MCP';
$string['newconnection'] = 'Nueva conexión';
$string['oauthclientdisable'] = 'Deshabilitar cliente';
$string['oauthclientdisabled'] = 'Se deshabilitó el cliente OAuth y se revocaron todas sus conexiones y tokens.';
$string['oauthclients'] = 'Clientes OAuth';
$string['oauthconsentapplication'] = 'Aplicación externa';
$string['oauthconsentconnection'] = 'Conexión entre la aplicación externa y este sitio Moodle';
$string['oauthconsentdestination'] = 'Tu sitio Moodle';
$string['oauthconsenteyebrow'] = 'Solicitud de autorización OAuth';
$string['oauthconsentfooter'] = 'Autoriza solo aplicaciones que reconozcas y en las que confíes.';
$string['oauthconsentpermissions'] = 'Permisos solicitados';
$string['oauthconsentpermissionsintro'] = 'Revisa lo que esta aplicación podrá hacer en tu nombre.';
$string['oauthconsentreadsummary'] = 'Permitir que ChatGPT, Claude u otra aplicación conectada por MCP consulte la información disponible mediante las herramientas de solo lectura de Moodle.';
$string['oauthconsentsecuritybody'] = 'Esta conexión no puede superar los permisos de tu cuenta de Moodle. Algunas operaciones sensibles requieren confirmación adicional. Puedes revocar el acceso más adelante.';
$string['oauthconsentsecuritytitle'] = 'Tus permisos de Moodle siguen aplicándose';
$string['oauthconsenttitle'] = 'Autorizar una nueva conexión';
$string['oauthconsentwritesummary'] = 'Permitir que ChatGPT, Claude u otra aplicación conectada por MCP cree, edite o elimine datos mediante las herramientas de escritura disponibles para tu cuenta, sujetas a confirmación.';
$string['pluginname'] = 'Moodle MCP';
$string['privacy:metadata:local_mcp_access_token'] = 'Almacena identificadores de tokens de acceso OAuth, asociaciones con usuarios, ámbitos, horas de uso y direcciones IP.';
$string['privacy:metadata:local_mcp_audit'] = 'Almacena metadatos de seguridad y operaciones de la actividad MCP.';
$string['privacy:metadata:local_mcp_auth_code'] = 'Almacena metadatos de códigos de autorización OAuth de un solo uso asociados a un usuario.';
$string['privacy:metadata:local_mcp_confirm'] = 'Almacena registros de confirmación vinculados al usuario para operaciones de escritura MCP.';
$string['privacy:metadata:local_mcp_connections'] = 'Almacena conexiones MCP autorizadas.';
$string['privacy:metadata:local_mcp_refresh_token'] = 'Almacena metadatos de tokens de actualización OAuth, asociaciones con usuarios y marcas de tiempo de su ciclo de vida.';
$string['privacy:metadata:mcp_client'] = 'Las aplicaciones de IA autorizadas, como ChatGPT y Claude, conectadas mediante el protocolo MCP pueden recibir información devuelta por las herramientas habilitadas según los permisos del usuario y las operaciones solicitadas.';
$string['privacy:metadata:mcp_client:activities'] = 'Las herramientas MCP autorizadas pueden devolver actividades, entregas e información de finalización.';
$string['privacy:metadata:mcp_client:courses'] = 'Las herramientas MCP autorizadas pueden devolver datos de cursos y matriculaciones.';
$string['privacy:metadata:mcp_client:grades'] = 'Las herramientas MCP autorizadas pueden devolver calificaciones e información de evaluaciones.';
$string['privacy:metadata:mcp_client:otherdata'] = 'Pueden devolverse otros datos accesibles mediante las herramientas MCP instaladas cuando lo solicite un usuario autorizado.';
$string['privacy:metadata:mcp_client:userid'] = 'Se pueden enviar identificadores de usuario de Moodle para identificar al usuario autorizado o los resultados.';
$string['privacy:metadata:mcp_client:userprofile'] = 'Las herramientas MCP autorizadas pueden devolver información del perfil del usuario.';
$string['readapis'] = 'API de LECTURA';
$string['readapiscount'] = 'Herramientas de LECTURA: {$a}';
$string['readapisempty'] = 'No hay herramientas de LECTURA disponibles.';
$string['readapisviewall'] = 'Ver todas las herramientas de LECTURA de este plugin';
$string['readpermissions'] = 'Permisos de lectura';
$string['settings'] = 'Configuración';
$string['settingsaccessttl'] = 'Duración del token de acceso';
$string['settingsaccessttl_desc'] = 'Duración predeterminada de los tokens de acceso OAuth.';
$string['settingsauditretention'] = 'Días de conservación de auditoría';
$string['settingscodettl'] = 'Duración del código de autorización';
$string['settingscodettl_desc'] = 'Los códigos de autorización son de corta duración y de un solo uso.';
$string['settingsconfirmationttl'] = 'Duración de la confirmación';
$string['settingsconfirmationttl_desc'] = 'Duración de los tokens de confirmación de ESCRITURA de un solo uso.';
$string['settingslogmessages'] = 'Registrar mensajes JSON de MCP en PHP error_log';
$string['settingsdebuglogging'] = 'Registrar eventos de diagnóstico MCP en PHP error_log';
$string['settingsdebuglogging_desc'] = 'Registra etapas, resultados y metadatos de excepciones depurados de las llamadas MCP. Desactivado de forma predeterminada. No es necesario modificar config.php. Actívalo temporalmente para diagnosticar problemas y restringe el acceso a los registros PHP.';
$string['settingslogmessages_desc'] = 'Seguimiento opcional del cuerpo JSON-RPC en los puntos de acceso server/read/write; desactivado de forma predeterminada y efectivo solo cuando el registro de diagnóstico MCP también está habilitado. Se ocultan secretos conocidos y datos binarios, pero pueden aparecer datos personales de Moodle. Actívalo temporalmente, restringe el acceso a los registros PHP y desactívalo después.';
$string['settingsrateoauth'] = 'Solicitudes OAuth/minuto';
$string['settingsrateread'] = 'Solicitudes de LECTURA/minuto';
$string['settingsratewrite'] = 'Solicitudes de ESCRITURA/minuto';
$string['settingsrefreshttl'] = 'Duración del token de actualización';
$string['settingsrefreshttl_desc'] = 'Duración predeterminada de los tokens de actualización OAuth.';
$string['subplugintype_mcptool'] = 'Herramienta de actividad MCP';
$string['subplugintype_mcptool_plural'] = 'Herramientas de actividad MCP';
$string['writeapis'] = 'API de ESCRITURA';
$string['writeapiscolcapability'] = 'Capacidad';
$string['writeapiscolconfirmation'] = 'Confirmación';
$string['writeapiscoldescription'] = 'Descripción';
$string['writeapiscoldestructive'] = 'Destructivo';
$string['writeapiscoldryrun'] = 'Simulación';
$string['writeapiscolname'] = 'Nombre';
$string['writeapiscount'] = 'Herramientas de ESCRITURA: {$a}';
$string['writeapisempty'] = 'No hay herramientas de ESCRITURA disponibles.';
$string['writeapisonlyread'] = 'Plugins instalados con API de solo LECTURA';
$string['writeapisonlyreadhelp'] = 'Estas integraciones están disponibles mediante LECTURA MCP, no ESCRITURA. Abre sus listas de API de LECTURA para ver todas las herramientas. El orden de instalación no cambia el tipo de API.';
$string['writepermissions'] = 'Permisos de escritura';

$string['connectionguidetablabel'] = 'Elegir una aplicación de IA';
$string['connectionguidetabother'] = 'Otra IA';
$string['connectionguideclaudetitle'] = 'Conectar Moodle a Claude';
$string['connectionguideclaudeintro'] = 'En Claude, agrega un conector web personalizado que apunte a este servidor MCP. Moodle gestiona el inicio de sesión mediante OAuth.';
$string['connectionguideclaudestep1'] = 'En Claude, abre Customize > Connectors > Add custom connector. En organizaciones, un propietario puede usar Organization settings > Connectors > Add > Custom > Web.';
$string['connectionguideclaudestep2'] = 'Asigna un nombre al conector, pega la URL del servidor MCP mostrada arriba y continúa con la configuración de autenticación.';
$string['connectionguideclaudestep3'] = 'Elige Sign in now o Sign in when needed y, en OAuth client, selecciona Register automatically (DCR). No elijas No sign in; no necesitas crear un secreto de cliente.';
$string['connectionguideclaudestep4'] = 'Termina de agregar el conector, haz clic en Connect, inicia sesión en Moodle y concede los permisos solicitados. Habilita el conector para tu conversación de Claude antes de utilizar sus herramientas.';
$string['connectionguideclaudenote'] = 'Claude debe poder acceder al servidor MCP mediante HTTPS. Las herramientas de escritura requieren el ámbito mcp:write, permisos de Moodle y confirmación expresa antes de realizar cambios.';
$string['connectionguideopenclaude'] = 'Abrir Claude';
$string['connectionguideclaudedocs'] = 'Documentación oficial de Claude';
$string['connectionguideothertitle'] = 'Conectar otras aplicaciones de IA';
$string['connectionguideotherintro'] = 'Utiliza esta configuración con un cliente que admita servidores MCP remotos por Streamable HTTP y el flujo OAuth requerido por Moodle. Los nombres de las opciones varían según la aplicación.';
$string['connectionguideotherstep1'] = 'Agrega un servidor MCP remoto mediante el transporte Streamable HTTP e introduce la URL HTTPS que aparece arriba. No utilices un token de servicio web de Moodle en la URL.';
$string['connectionguideotherstep2'] = 'Configura el código de autorización OAuth con PKCE S256. Si es compatible, usa el registro dinámico de clientes (RFC 7591) para registrar el cliente automáticamente.';
$string['connectionguideotherstep3'] = 'Para el descubrimiento de OAuth, utiliza la URL base del servidor de autorización que termina en /local/mcp/server.php. Si necesitas configuración manual, utiliza los puntos de acceso siguientes y mantén el recurso OAuth igual a la URL del punto de acceso MCP.';
$string['connectionguideotherstep4'] = 'Inicia sesión en Moodle, aprueba mcp:read y, si es necesario, mcp:write. Consulta las herramientas disponibles y confirma por separado cada operación de escritura.';
$string['connectionguideotheradvanced'] = 'URL para clientes sin descubrimiento automático.';
$string['connectionguideothernote'] = 'La compatibilidad depende de que el cliente admita MCP remoto, OAuth, PKCE y registro de clientes. local_mcp no se autentica con tokens en URL y no requiere acceso a Nginx para introducir manualmente esta configuración.';
$string['connectionguidechatgptstarttitle'] = 'Comenzar en ChatGPT';
$string['connectionguidechatgptstartintro'] = 'Abre la sección Plugins y sigue estos pasos para agregar este servidor Moodle MCP.';
$string['connectionguidechatgptstartstep1'] = 'Abre ChatGPT Plugins con el botón siguiente.';
$string['connectionguidechatgptstartstep2'] = 'Haz clic en Add, en la esquina superior derecha.';
$string['connectionguidechatgptstartstep3'] = 'Selecciona Add custom MCP server y utiliza la URL que aparece en el formulario siguiente.';
$string['connectionguidechatgptstartaction'] = 'Abrir ChatGPT Plugins';
$string['connectionguideclaudestarttitle'] = 'Comenzar en Claude';
$string['connectionguideclaudestartintro'] = 'Agrega este Moodle como conector personalizado desde la configuración de conectores de Claude.';
$string['connectionguideclaudestartstep1'] = 'En Claude, abre Customize > Connectors.';
$string['connectionguideclaudestartstep2'] = 'Selecciona Add custom connector y elige Web.';
$string['connectionguideclaudestartstep3'] = 'Introduce la URL del servidor MCP que aparece abajo y conecta tu cuenta de Moodle mediante OAuth.';
