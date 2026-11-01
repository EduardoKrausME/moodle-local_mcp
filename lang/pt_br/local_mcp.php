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
$string['auditprivacy'] = 'Registra chamadas de ferramentas realizadas pelo ChatGPT, Claude ou outros aplicativos de IA por meio do protocolo MCP, incluindo confirmações, falhas e IDs de entidades, sem armazenar conversas, credenciais ou valores dos conteúdos. Somente ações enviadas a este Moodle podem ser auditadas.';
$string['auditrequestid'] = 'ID da requisição';
$string['auditresults'] = 'Registros encontrados: {$a}';
$string['auditside'] = 'Lado';
$string['auditstatus'] = 'Status';
$string['audittool'] = 'Ferramenta';
$string['audituser'] = 'Usuário';
$string['audituserid'] = 'ID do usuário Moodle';
$string['auditwhen'] = 'Data/hora';
$string['authorizationdeniedbody'] = 'Entre com uma conta ativa do Moodle para autorizar o ChatGPT, Claude ou outro aplicativo compatível com o protocolo MCP. Contas de visitante não podem se conectar.';
$string['authorizationdeniedtitle'] = 'Conexão não autorizada';
$string['authorize'] = 'Autorizar conexão';
$string['authorizeheading'] = 'Conectar {$a} ao Moodle';
$string['authorizeintro'] = '{$a} está solicitando acesso a este Moodle.';
$string['backtodashboard'] = 'Voltar ao Dashboard';
$string['cachedef_ratelimit'] = 'Cache de limitação de requisições';
$string['cachedef_tooldefinitions'] = 'Cache de definições das ferramentas';
$string['cancel'] = 'Cancelar';
$string['chatgptsetupcopied'] = 'URL do servidor copiada.';
$string['chatgptsetupcopy'] = 'Copiar URL';
$string['chatgptsetupcopyfallback'] = 'Selecione e copie a URL destacada.';
$string['chatgptsetupdcrdisabled'] = 'O registro dinâmico de clientes OAuth está desativado. Ative essa opção antes de conectar o ChatGPT.';
$string['chatgptsetupdiscoveryhelp'] = 'Se o ChatGPT apresentar erro na descoberta OAuth, verifique se o servidor web direciona este endereço RFC 8414 aos metadados de autorização do Moodle MCP:';
$string['chatgptsetupfinish'] = 'Após concluir a autorização, atualize esta página. Este guia desaparecerá automaticamente quando uma conexão ativa do ChatGPT for identificada.';
$string['chatgptsetuphttpsdisabled'] = 'Este Moodle não usa HTTPS. Para conectar o ChatGPT pelo protocolo MCP, o servidor precisa estar acessível pela internet usando HTTPS.';
$string['chatgptsetupintro'] = 'Conecte este Moodle ao ChatGPT usando o protocolo MCP. Ainda não há uma conexão ativa com o ChatGPT; siga os passos abaixo para configurar o acesso com segurança.';
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
$string['chatgptsetupstep5body'] = 'Abra uma conversa no ChatGPT e utilize a integração Moodle MCP. Quando o ChatGPT solicitar a conexão, entre com uma conta ativa do Moodle (não visitante) e autorize os escopos de leitura e gravação solicitados. As ferramentas continuam limitadas pelas suas permissões no Moodle.';
$string['chatgptsetupstep5title'] = 'Autorize o acesso ao Moodle';
$string['chatgptsetuptitle'] = 'Conecte este Moodle ao ChatGPT';
$string['chatgptsetuptroubleshooting'] = 'Solução de problemas de descoberta OAuth';
$string['clientcolclientid'] = 'ID do cliente';
$string['clientcolcreated'] = 'Criado em';
$string['clientcoldynamic'] = 'Dinâmico';
$string['clientcolenabled'] = 'Ativo';
$string['clientcollastused'] = 'Último uso';
$string['clientcolname'] = 'Nome';
$string['clientcoltype'] = 'Tipo';
$string['connectionactions'] = 'Ações';
$string['connectionactivelist'] = 'Conexões OAuth ativas';
$string['connectionclient'] = 'Cliente';
$string['connectioncreated'] = 'Criada em';
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
$string['connectionguideintro'] = 'Conecte seu Moodle ao ChatGPT usando o protocolo MCP. Preencha os campos da janela Adicionar servidor MCP personalizado seguindo este guia; o cadastro é feito no ChatGPT, não nesta página.';
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
$string['connectionlastused'] = 'Última utilização';
$string['connectionnoneactive'] = 'Não há conexões OAuth ativas.';
$string['connectionrevoke'] = 'Revogar acesso';
$string['connectionrevoked'] = 'A conexão foi revogada e seus tokens foram invalidados.';
$string['connections'] = 'Conexões';
$string['connectionscopes'] = 'Escopos';
$string['connectionuser'] = 'Usuário';
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
$string['dashboardnoactivity'] = 'Nenhuma operação realizada pelo ChatGPT, Claude ou outro aplicativo conectado via MCP foi registrada na auditoria nos últimos 7 dias.';
$string['dashboardoverview'] = 'Visão geral das integrações de IA (MCP)';
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
$string['error'] = 'Erro da API MCP';
$string['importgiftquestionbank'] = 'Importar banco de questões GIFT';
$string['importgiftquestionbank_desc'] = 'Importa até 500 questões GIFT UTF-8 em uma categoria existente (categoryid) ou cria uma nova (name), em uma única operação confirmada.';
$string['mcp:manage'] = 'Gerenciar o Moodle MCP';
$string['newconnection'] = 'Nova conexão';
$string['oauthclientdisable'] = 'Desativar cliente';
$string['oauthclientdisabled'] = 'O cliente OAuth foi desativado e todas as suas conexões e tokens foram revogados.';
$string['oauthclients'] = 'Clientes OAuth';
$string['oauthconsentapplication'] = 'Aplicativo externo';
$string['oauthconsentconnection'] = 'Conexão entre o aplicativo externo e este ambiente Moodle';
$string['oauthconsentdestination'] = 'Seu ambiente Moodle';
$string['oauthconsenteyebrow'] = 'Solicitação de autorização OAuth';
$string['oauthconsentfooter'] = 'Autorize somente aplicativos que você reconhece e considera confiáveis.';
$string['oauthconsentpermissions'] = 'Permissões solicitadas';
$string['oauthconsentpermissionsintro'] = 'Confira o que este aplicativo poderá fazer em seu nome.';
$string['oauthconsentreadsummary'] = 'Permitir que o ChatGPT, Claude ou outro aplicativo conectado via MCP consulte informações disponíveis nas ferramentas de leitura do Moodle.';
$string['oauthconsentredirecturi'] = 'URL de retorno (destino do código de autorização)';
$string['oauthconsentsecuritybody'] = 'Esta conexão não pode ultrapassar as permissões da sua conta Moodle. Algumas operações sensíveis exigem confirmação adicional. Você poderá revogar o acesso depois.';
$string['oauthconsentsecuritytitle'] = 'Suas permissões do Moodle continuam valendo';
$string['oauthconsenttitle'] = 'Autorizar uma nova conexão';
$string['oauthconsentunverifiedbody'] = 'Este cliente foi registrado automaticamente. Seu nome e site não foram verificados. O código de autorização será enviado à URL de retorno exibida acima. Continue apenas se reconhecer esse destino.';
$string['oauthconsentunverifiedtitle'] = 'Aplicativo não verificado';
$string['oauthconsentwritesummary'] = 'Permitir que o ChatGPT, Claude ou outro aplicativo conectado via MCP crie, edite ou exclua dados por meio das ferramentas de escrita disponíveis à sua conta, com confirmação.';
$string['pluginname'] = 'Moodle MCP';
$string['privacy:metadata:local_mcp_access_token'] = 'Armazena identificadores de tokens de acesso OAuth, vínculos com usuários, escopos, horários de uso e endereços IP.';
$string['privacy:metadata:local_mcp_audit'] = 'Armazena metadados de segurança e operações das atividades MCP.';
$string['privacy:metadata:local_mcp_auth_code'] = 'Armazena metadados de códigos de autorização OAuth de uso único vinculados aos usuários.';
$string['privacy:metadata:local_mcp_confirm'] = 'Armazena confirmações vinculadas a usuários para operações de escrita MCP.';
$string['privacy:metadata:local_mcp_connections'] = 'Armazena conexões MCP autorizadas.';
$string['privacy:metadata:local_mcp_refresh_token'] = 'Armazena metadados de tokens de renovação OAuth, vínculos com usuários e datas do ciclo de vida.';
$string['privacy:metadata:mcp_client'] = 'Aplicativos de IA autorizados, como ChatGPT e Claude, conectados pelo protocolo MCP, podem receber dados retornados pelas ferramentas disponíveis, conforme as permissões do usuário e as operações solicitadas.';
$string['privacy:metadata:mcp_client:activities'] = 'Atividades, entregas e informações de conclusão podem ser retornadas por ferramentas MCP autorizadas.';
$string['privacy:metadata:mcp_client:courses'] = 'Dados de cursos e matrículas podem ser retornados por ferramentas MCP autorizadas.';
$string['privacy:metadata:mcp_client:grades'] = 'Notas e informações de avaliação podem ser retornadas por ferramentas MCP autorizadas.';
$string['privacy:metadata:mcp_client:otherdata'] = 'Outros dados acessíveis pelas ferramentas MCP instaladas podem ser retornados quando solicitados por usuários autorizados.';
$string['privacy:metadata:mcp_client:userid'] = 'Identificadores de usuários do Moodle podem ser enviados para identificar o usuário autorizado ou os resultados.';
$string['privacy:metadata:mcp_client:userprofile'] = 'Dados de perfil dos usuários podem ser retornados por ferramentas MCP autorizadas.';
$string['readapis'] = 'APIs READ';
$string['readapiscount'] = 'Ferramentas READ: {$a}';
$string['readapisempty'] = 'Nenhuma ferramenta READ disponível.';
$string['readapisviewall'] = 'Ver todas as ferramentas READ deste plugin';
$string['readpermissions'] = 'Permissões de leitura';
$string['settings'] = 'Configurações';
$string['settingsaccessttl'] = 'Tempo de vida do token de acesso';
$string['settingsaccessttl_desc'] = 'Tempo de vida padrão dos tokens de acesso OAuth.';
$string['settingsauditretention'] = 'Dias de retenção da auditoria';
$string['settingscodettl'] = 'Tempo de vida do código de autorização';
$string['settingscodettl_desc'] = 'Os códigos de autorização têm duração curta e só podem ser utilizados uma vez.';
$string['settingsconfirmationttl'] = 'Tempo de vida da confirmação';
$string['settingsconfirmationttl_desc'] = 'Tempo de vida do token de confirmação de operações WRITE, válido para uso único.';
$string['settingsdynamicregistration'] = 'Registro dinâmico de clientes';
$string['settingsdynamicregistration_desc'] = 'Permite que clientes públicos compatíveis com os padrões registrem URIs de redirecionamento. A aprovação do administrador continua sendo necessária.';
$string['settingslogmessages'] = 'Registrar mensagens JSON do MCP no error_log do PHP';
$string['settingslogmessages_desc'] = 'Rastreamento opcional dos corpos JSON-RPC nos endpoints server/read/write; desativado por padrão e exige $CFG->local_mcp_debugger no config.php. Segredos conhecidos e dados binários são ocultados, mas dados pessoais do Moodle ainda podem aparecer. Ative temporariamente, restrinja o acesso aos logs PHP e desative após o diagnóstico.';
$string['settingsrateoauth'] = 'Requisições OAuth por minuto';
$string['settingsrateread'] = 'Requisições READ por minuto';
$string['settingsratewrite'] = 'Requisições WRITE por minuto';
$string['settingsrefreshttl'] = 'Tempo de vida do token de renovação';
$string['settingsrefreshttl_desc'] = 'Tempo de vida padrão dos tokens de renovação OAuth.';
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
