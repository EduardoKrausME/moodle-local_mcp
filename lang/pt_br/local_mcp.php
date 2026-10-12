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
$string['connectionguideacknowledgement'] = 'Entendo e quero continuar';
$string['connectionguideadvanced'] = 'Configurações avançadas de OAuth';
$string['connectionguideadvancedhelp'] = 'Normalmente mantenha a descoberta automática. Se o painel pedir o método de registro do cliente, escolha registro dinâmico (DCR). Ative essa opção nas configurações do Moodle MCP caso esteja desabilitada.';
$string['connectionguideadvancedpreview'] = 'Mantenha a descoberta automática OAuth e o registro dinâmico de clientes.';
$string['connectionguideauthentication'] = 'Autenticação';
$string['connectionguideconnected'] = 'Já existe uma conexão OAuth ativa do ChatGPT. Este guia continua disponível para configurar outra conexão.';
$string['connectionguidedocumentation'] = 'Ver guia completo de conexão';
$string['connectionguideconnection'] = 'Conexão';
$string['connectionguidecreate'] = 'Criar como plugin';
$string['connectionguidecreatehelp'] = 'Clique em Criar como plugin no ChatGPT, conclua a instalação, entre no Moodle e autorize as permissões READ/WRITE solicitadas.';
$string['connectionguidedescription'] = 'Descrição (opcional)';
$string['connectionguidedescriptionexample'] = 'Acesse cursos, atividades e ferramentas do Moodle pelo ChatGPT.';
$string['connectionguidedescriptionhelp'] = 'Descreva para que serve o servidor. Por exemplo: Consultar e gerenciar cursos, usuários e atividades no Moodle.';
$string['connectionguideeyebrow'] = 'Moodle MCP · Configuração no ChatGPT';
$string['connectionguidefinalbody'] = 'Abra uma conversa com o novo plugin, conecte usando OAuth e aprove o acesso no Moodle. Você poderá consultar clientes e conexões OAuth na administração do Moodle MCP.';
$string['connectionguidefinaltitle'] = 'Depois de criar o plugin';
$string['connectionguideformtitle'] = 'Adicionar servidor MCP personalizado';
$string['connectionguideicon'] = 'Ícone (opcional)';
$string['connectionguideiconhelp'] = 'Clique no sinal de mais (+) no ChatGPT e faça upload de um ícone PNG do seu Moodle. Prepare uma imagem quadrada de pelo menos 256 × 256 pixels e compacte para no máximo 10 KB. O ícone é opcional, mas ajuda a identificar a conexão.';
$string['connectionguideiconhint'] = 'PNG · 256 × 256 px ou mais · até 10 KB';
$string['connectionguideinstructionsintro'] = 'Siga a numeração do exemplo ao lado.';
$string['connectionguideintro'] = 'Preencha os campos da janela Adicionar servidor MCP personalizado do ChatGPT seguindo este guia. O cadastro é feito no ChatGPT, não nesta página.';
$string['connectionguidename'] = 'Nome';
$string['connectionguidenamehelp'] = 'Digite Moodle MCP ou um nome que identifique a instituição, como Moodle MCP – Minha Escola.';
$string['connectionguideoauthhelp'] = 'Selecione OAuth. O Moodle MCP usa autorização OAuth; não é necessário token manual nem escolher Sem autenticação.';
$string['connectionguidepreviewnotice'] = 'Exemplo dos campos do ChatGPT (não é um formulário de cadastro).';
$string['connectionguidesafety'] = 'Confirmação de segurança';
$string['connectionguidesafetyhelp'] = 'Leia o aviso de segurança e marque Entendo e quero continuar somente se você confia neste servidor Moodle.';
$string['connectionguidesteps'] = 'O que preencher em cada campo';
$string['connectionguidetitle'] = 'Conecte seu Moodle ao ChatGPT';
$string['connectionguidetunnelmode'] = 'Túnel';
$string['connectionguideurlhelp'] = 'Selecione URL do servidor (não Túnel). Copie o endereço do exemplo e cole no ChatGPT. Use a URL HTTPS terminada em /local/mcp/server.php, sem parâmetros adicionais.';
$string['connectionguideurlmode'] = 'URL do servidor';
$string['connections'] = 'Conexões';
$string['dashboard'] = 'Dashboard';
$string['mcp:manage'] = 'Gerenciar o Moodle MCP';
$string['newconnection'] = 'Nova conexão';
$string['oauthclients'] = 'Clientes OAuth';
$string['oauthconsentapplication'] = 'Aplicativo externo';
$string['oauthconsentconnection'] = 'Conexão entre o aplicativo externo e este ambiente Moodle';
$string['oauthconsentdestination'] = 'Seu ambiente Moodle';
$string['oauthconsenteyebrow'] = 'Solicitação de autorização OAuth';
$string['oauthconsentfooter'] = 'Autorize somente aplicativos que você reconhece e considera confiáveis.';
$string['oauthconsentpermissions'] = 'Permissões solicitadas';
$string['oauthconsentpermissionsintro'] = 'Confira o que este aplicativo poderá fazer em seu nome.';
$string['oauthconsentreadsummary'] = 'Consultar informações disponibilizadas pelas ferramentas de leitura do Moodle MCP.';
$string['oauthconsentsecuritybody'] = 'Esta conexão não pode ultrapassar as permissões da sua conta Moodle. Algumas operações sensíveis exigem confirmação adicional. Você poderá revogar o acesso depois.';
$string['oauthconsentsecuritytitle'] = 'Suas permissões do Moodle continuam valendo';
$string['oauthconsenttitle'] = 'Autorizar uma nova conexão';
$string['oauthconsentwritesummary'] = 'Criar, editar ou excluir dados por meio das ferramentas de escrita do Moodle MCP disponíveis à sua conta.';
$string['pluginname'] = 'Moodle MCP';
$string['readapis'] = 'APIs READ';
$string['readpermissions'] = 'Permissões de leitura';
$string['settings'] = 'Configurações';
$string['subplugintype_mcptool'] = 'Ferramenta MCP de atividade';
$string['subplugintype_mcptool_plural'] = 'Ferramentas MCP de atividades';
$string['writeapis'] = 'APIs WRITE';
$string['writepermissions'] = 'Permissões de gravação';
$string['auditside'] = 'Lado';
$string['auditstatus'] = 'Status';
$string['audittool'] = 'Ferramenta';
$string['audituserid'] = 'ID do usuário Moodle';
$string['auditonlychatgpt'] = 'Somente conexões do ChatGPT';
$string['auditprivacy'] = 'Registra chamadas de ferramentas MCP, confirmações, falhas e IDs de entidades, sem armazenar conversas, credenciais ou valores dos conteúdos. Apenas ações enviadas a este servidor MCP podem ser auditadas.';
$string['auditresults'] = 'Registros encontrados: {$a}';
$string['auditexport'] = 'Exportar CSV filtrado (até 5.000 registros)';
$string['auditwhen'] = 'Data/hora';
$string['auditevent'] = 'Evento';
$string['audituser'] = 'Usuário';
$string['auditclient'] = 'Cliente OAuth';
$string['auditconnection'] = 'ID da conexão';
$string['auditobjects'] = 'Campos / IDs das entidades / quantidades';
$string['auditrequestid'] = 'ID da requisição';
$string['auditduration'] = 'Duração (ms)';
$string['auditconfirmed'] = 'Confirmado';
$string['auditnone'] = 'Nenhum registro encontrado.';
