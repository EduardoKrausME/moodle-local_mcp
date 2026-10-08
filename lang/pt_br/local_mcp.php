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

$string['audit'] = 'Auditoria';
$string['authorizationdeniedbody'] = 'Você não possui permissão para conectar este Moodle a aplicações externas. Somente um administrador do Moodle pode autorizar uma conexão MCP.';
$string['authorizationdeniedtitle'] = 'Conexão não autorizada';
$string['authorize'] = 'Autorizar conexão';
$string['authorizeheading'] = 'Conectar {$a} ao Moodle';
$string['authorizeintro'] = '{$a} está solicitando acesso a este Moodle.';
$string['cancel'] = 'Cancelar';
$string['chatgptsetupcopied'] = 'URL do servidor copiada.';
$string['chatgptsetupcopy'] = 'Copiar URL';
$string['chatgptsetupcopyfallback'] = 'Selecione e copie a URL destacada.';
$string['chatgptsetupdcrdisabled'] = 'O registro dinâmico de clientes OAuth está desativado. Ative essa opção antes de conectar o ChatGPT.';
$string['chatgptsetupdiscoveryhelp'] = 'Se o ChatGPT apresentar erro na descoberta OAuth, verifique se o servidor web direciona este endereço RFC 8414 aos metadados de autorização do Moodle MCP:';
$string['chatgptsetupfinish'] = 'Após concluir a autorização, atualize esta página. Este guia desaparecerá automaticamente quando uma conexão ativa do ChatGPT for identificada.';
$string['chatgptsetuphttpsdisabled'] = 'O endereço deste Moodle não usa HTTPS. O ChatGPT exige um servidor MCP acessível pela internet com HTTPS.';
$string['chatgptsetupintro'] = 'Este Moodle ainda não possui uma conexão ativa com o ChatGPT. Siga os passos abaixo para cadastrar o servidor MCP.';
$string['chatgptsetupnewtab'] = 'abre em nova aba';
$string['chatgptsetupopenchatgpt'] = 'Abrir ChatGPT';
$string['chatgptsetupopensettings'] = 'Abrir configurações do Moodle MCP';
$string['chatgptsetupserverlabel'] = 'URL do servidor MCP';
$string['chatgptsetupstep1body'] = 'Clique no botão abaixo para abrir o ChatGPT em uma nova aba e entre na sua conta.';
$string['chatgptsetupstep1title'] = 'Abra o ChatGPT';
$string['chatgptsetupstep2body'] = 'No ChatGPT pelo navegador, acesse Plugins, clique em + e selecione Adicionar servidor MCP personalizado. Use Moodle MCP como nome e escolha autenticação OAuth com registro dinâmico (DCR).';
$string['chatgptsetupstep2title'] = 'Adicione um servidor MCP personalizado';
$string['chatgptsetupstep3body'] = 'Cole o endereço abaixo no campo Server URL (URL do servidor) do ChatGPT:';
$string['chatgptsetupstep3title'] = 'Informe a URL deste Moodle';
$string['chatgptsetupstep4body'] = 'Revise o aviso de segurança, confirme que compreendeu os riscos e clique em Criar como plugin. Instale o plugin criado, se solicitado.';
$string['chatgptsetupstep4title'] = 'Crie e instale o plugin';
$string['chatgptsetupstep5body'] = 'Abra uma conversa e utilize o plugin Moodle MCP. Quando o ChatGPT solicitar a conexão, entre neste Moodle como administrador e autorize as permissões de leitura e gravação solicitadas.';
$string['chatgptsetupstep5title'] = 'Autorize o acesso ao Moodle';
$string['chatgptsetuptitle'] = 'Conecte este Moodle ao ChatGPT';
$string['chatgptsetuptroubleshooting'] = 'Solução de problemas de descoberta OAuth';
$string['connections'] = 'Conexões';
$string['dashboard'] = 'Dashboard';
$string['manualtokencreated'] = 'Token manual criado. Copie o segredo agora; ele não será exibido novamente.';
$string['manualtokens'] = 'Tokens manuais';
$string['mcp:manage'] = 'Gerenciar o Moodle MCP';
$string['oauthclients'] = 'Clientes OAuth';
$string['pluginname'] = 'Moodle MCP';
$string['readapis'] = 'APIs READ';
$string['readpermissions'] = 'Permissões de leitura';
$string['settings'] = 'Configurações';
$string['subplugintype_mcptool'] = 'Ferramenta MCP de atividade';
$string['subplugintype_mcptool_plural'] = 'Ferramentas MCP de atividades';
$string['writeapis'] = 'APIs WRITE';
$string['writepermissions'] = 'Permissões de gravação';
