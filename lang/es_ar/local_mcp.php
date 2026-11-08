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
 * Language overrides for es_ar.
 *
 * @package   local_mcp
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

// Only strings that differ from the parent language (es) are defined here.
$string['authorizationdeniedbody'] = 'Iniciá sesión con una cuenta activa de Moodle para autorizar ChatGPT, Claude u otra aplicación de IA compatible con el protocolo MCP. Las cuentas de invitado no pueden conectarse.';
$string['chatgptsetupstep1body'] = 'Hacé clic en el botón siguiente para abrir ChatGPT en otra pestaña e iniciar sesión.';
$string['chatgptsetupstep4body'] = 'Leé la advertencia de seguridad, seleccioná Entiendo y deseo continuar y hacé clic en Crear como plugin. Instalá el plugin creado si se te solicita.';
$string['connectionguideacknowledgement'] = 'Entiendo y quiero continuar';
$string['connectionguideconnected'] = 'Se detectó una conexión OAuth activa de ChatGPT. Podés seguir estas instrucciones para configurar otra conexión.';
$string['connectionguideiconhelp'] = 'Hacé clic en el signo más de ChatGPT y subí tu propio ícono PNG. Usá un ícono cuadrado de al menos 256 × 256 píxeles y comprimilo a 10 KB o menos. Es opcional, pero ayuda a identificar tu Moodle.';
$string['connectionguidenamehelp'] = 'Ingresá Moodle MCP o un nombre distintivo, como Moodle MCP – Mi Escuela.';
$string['connectionguidesteps'] = 'Qué ingresar en cada campo';
$string['connectionguideurlhelp'] = 'Seleccioná Server URL (no Tunnel). Copiá la dirección del ejemplo y pegala en ChatGPT. Usá la URL HTTPS que termina en /local/mcp/server.php, sin parámetros adicionales.';
$string['privacy:metadata:mcp_client:courses'] = 'Las herramientas MCP autorizadas pueden devolver datos de cursos e inscripciones.';
