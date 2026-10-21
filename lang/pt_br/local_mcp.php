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
$string['auditclient'] = 'Cliente OAuth';
$string['auditconfirmed'] = 'Confirmado';
$string['auditconnection'] = 'ID da conexão';
$string['auditduration'] = 'Duração (ms)';
$string['auditevent'] = 'Evento';
$string['auditexport'] = 'Exportar CSV filtrado (até 5.000 registros)';
$string['auditnone'] = 'Nenhum registro encontrado.';
$string['auditobjects'] = 'Campos / IDs das entidades / quantidades';
$string['auditonlychatgpt'] = 'Somente conexões do ChatGPT';
$string['auditprivacy'] = 'Registra chamadas de ferramentas MCP, confirmações, falhas e IDs de entidades, sem armazenar conversas, credenciais ou valores dos conteúdos. Apenas ações enviadas a este servidor MCP podem ser auditadas.';
$string['auditrequestid'] = 'ID da requisição';
$string['auditresults'] = 'Registros encontrados: {$a}';
$string['auditside'] = 'Lado';
$string['auditstatus'] = 'Status';
$string['audittool'] = 'Ferramenta';
$string['audituser'] = 'Usuário';
$string['audituserid'] = 'ID do usuário Moodle';
$string['auditwhen'] = 'Data/hora';
$string['authorizationdeniedbody'] = 'Entre com uma conta ativa do Moodle para autorizar uma conexão MCP. Contas de visitante não podem se conectar.';
$string['authorizationdeniedtitle'] = 'Conexão não autorizada';
$string['authorize'] = 'Autorizar conexão';
$string['authorizeheading'] = 'Conectar {$a} ao Moodle';
$string['authorizeintro'] = '{$a} está solicitando acesso a este Moodle.';
$string['backtodashboard'] = 'Voltar ao Dashboard';
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
$string['chatgptsetupstep5body'] = 'Abra uma conversa e utilize o plugin Moodle MCP. Quando o ChatGPT solicitar a conexão, entre com uma conta ativa do Moodle (não visitante) e autorize os escopos de leitura e gravação solicitados. As ferramentas continuam limitadas pelas suas permissões no Moodle.';
$string['chatgptsetupstep5title'] = 'Autorize o acesso ao Moodle';
$string['chatgptsetuptitle'] = 'Conecte este Moodle ao ChatGPT';
$string['chatgptsetuptroubleshooting'] = 'Solução de problemas de descoberta OAuth';
$string['connectionguideacknowledgement'] = 'Entendo e quero continuar';
$string['connectionguideadvanced'] = 'Configurações avançadas de OAuth';
$string['connectionguideadvancedhelp'] = 'Normalmente mantenha a descoberta automática. Se o painel pedir o método de registro do cliente, escolha registro dinâmico (DCR). Ative essa opção nas configurações do Moodle MCP caso esteja desabilitada.';
$string['connectionguideadvancedpreview'] = 'Mantenha a descoberta automática OAuth e o registro dinâmico de clientes.';
$string['connectionguideauthentication'] = 'Autenticação';
$string['connectionguideconnected'] = 'Já existe uma conexão OAuth ativa do ChatGPT. Este guia continua disponível para configurar outra conexão.';
$string['connectionguideconnection'] = 'Conexão';
$string['connectionguidecreate'] = 'Criar como plugin';
$string['connectionguidecreatehelp'] = 'Clique em Criar como plugin no ChatGPT, conclua a instalação, entre no Moodle e autorize as permissões READ/WRITE solicitadas.';
$string['connectionguidedescription'] = 'Descrição (opcional)';
$string['connectionguidedescriptionexample'] = 'Acesse cursos, atividades e ferramentas do Moodle pelo ChatGPT.';
$string['connectionguidedescriptionhelp'] = 'Descreva para que serve o servidor. Por exemplo: Consultar e gerenciar cursos, usuários e atividades no Moodle.';
$string['connectionguidedocumentation'] = 'Ver guia completo de conexão';
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
$string['dashboardactivitychart'] = 'Chamadas READ e WRITE por dia';
$string['dashboardactivityheading'] = 'Atividade das ferramentas';
$string['dashboardactivityhelp'] = 'Últimos 7 dias, no seu fuso horário. As chamadas incluem execuções, simulações, confirmações pendentes e erros; somente as operações WRITE concluídas alteram dados.';
$string['dashboardcalls'] = 'Chamadas';
$string['dashboardclients'] = 'Clientes OAuth ativos';
$string['dashboardconnections'] = 'Conexões ativas';
$string['dashboarddestructivetoday'] = 'WRITE destrutivas executadas hoje';
$string['dashboardexamples'] = 'Exemplos de APIs';
$string['dashboardmoretools'] = '+{$a} outras';
$string['dashboardnoactivity'] = 'Nenhuma chamada MCP foi registrada na auditoria nos últimos 7 dias.';
$string['dashboardoverview'] = 'Visão geral do MCP';
$string['dashboardplugin'] = 'Plugin';
$string['dashboardpluginschart'] = 'APIs WRITE disponíveis por plugin';
$string['dashboardquicklinks'] = 'Atalhos do painel';
$string['dashboardreadtoday'] = 'Chamadas READ hoje';
$string['dashboardstatuschart'] = 'Estados das chamadas (últimos 7 dias)';
$string['dashboardstatuserror'] = 'Erro';
$string['dashboardstatusok'] = 'Concluída com sucesso';
$string['dashboardstatuspending'] = 'Aguardando confirmação';
$string['dashboardstatuspreview'] = 'Simulação';
$string['dashboardtodaydescription'] = 'Situação das conexões e atividade auditada das ferramentas hoje.';
$string['dashboardtools'] = 'Ferramentas';
$string['dashboardtotaltools'] = 'APIs WRITE disponíveis';
$string['dashboardviewall'] = 'Ver todas as APIs WRITE';
$string['dashboardwritecompletedtoday'] = 'Operações WRITE executadas hoje';
$string['dashboardwritesummary'] = 'APIs WRITE por plugin';
$string['dashboardwritesummaryhelp'] = 'Ferramentas descobertas no registro WRITE ativo, agrupadas pelo componente Moodle.';
$string['dashboardwritetoday'] = 'Chamadas WRITE hoje';
$string['importgiftquestionbank'] = 'Importar banco de questões GIFT';
$string['importgiftquestionbank_desc'] = 'Importa até 500 questões GIFT UTF-8 em uma categoria existente (categoryid) ou cria uma nova (name), em uma única operação confirmada.';
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
$string["oauthconsentredirecturi"] = "URL de retorno (destino do código de autorização)";
$string['oauthconsentsecuritybody'] = 'Esta conexão não pode ultrapassar as permissões da sua conta Moodle. Algumas operações sensíveis exigem confirmação adicional. Você poderá revogar o acesso depois.';
$string['oauthconsentsecuritytitle'] = 'Suas permissões do Moodle continuam valendo';
$string['oauthconsenttitle'] = 'Autorizar uma nova conexão';
$string["oauthconsentunverifiedbody"] = "Este cliente foi registrado automaticamente. Seu nome e site não foram verificados. O código de autorização será enviado à URL de retorno exibida acima. Continue apenas se reconhecer esse destino.";
$string["oauthconsentunverifiedtitle"] = "Aplicativo não verificado";
$string['oauthconsentwritesummary'] = 'Criar, editar ou excluir dados por meio das ferramentas de escrita do Moodle MCP disponíveis à sua conta.';
$string['pluginname'] = 'Moodle MCP';
$string['readapis'] = 'APIs READ';
$string['readapiscount'] = 'Ferramentas READ: {$a}';
$string['readapisempty'] = 'Nenhuma ferramenta READ disponível.';
$string['readapisviewall'] = 'Ver todas as ferramentas READ deste plugin';
$string['readpermissions'] = 'Permissões de leitura';
$string['settings'] = 'Configurações';
$string['subplugintype_mcptool'] = 'Ferramenta MCP de atividade';
$string['subplugintype_mcptool_plural'] = 'Ferramentas MCP de atividades';
$string['writeapis'] = 'APIs WRITE';
$string['writeapiscolcapability'] = 'Permissão';
$string['writeapiscolconfirmation'] = 'Confirmação';
$string['writeapiscoldescription'] = 'Descrição';
$string['writeapiscoldestructive'] = 'Destrutiva';
$string['writeapiscoldryrun'] = 'Simulação (dry-run)';
$string['writeapiscolname'] = 'Nome';
$string['writeapiscount'] = 'Ferramentas WRITE: {$a}';
$string['writeapisempty'] = 'Nenhuma ferramenta WRITE disponível.';
$string['writeapisonlyread'] = 'Plugins instalados com APIs somente READ';
$string['writeapisonlyreadhelp'] = 'Essas integrações estão disponíveis pelo MCP READ, não pelo WRITE. Acesse a lista READ para consultar todas as ferramentas. A ordem de instalação não altera o tipo das APIs.';
$string['writepermissions'] = 'Permissões de gravação';
