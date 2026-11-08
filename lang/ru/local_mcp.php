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

$string['audit'] = 'Журнал аудита';
$string['auditclient'] = 'Клиент OAuth';
$string['auditconfirmed'] = 'Подтверждено';
$string['auditconnection'] = 'Идентификатор подключения';
$string['auditduration'] = 'Длительность (мс)';
$string['auditevent'] = 'Событие';
$string['auditexport'] = 'Экспортировать отфильтрованный CSV (до 5000 строк)';
$string['auditnone'] = 'Подходящие записи аудита не найдены.';
$string['auditobjects'] = 'Имена полей / идентификаторы сущностей / количество';
$string['auditonlychatgpt'] = 'Только подключения ChatGPT';
$string['auditprivacy'] = 'Регистрирует вызовы инструментов через MCP приложениями ChatGPT, Claude и другими системами ИИ, включая подтверждения, ошибки и безопасные идентификаторы сущностей. Тексты переписок, учетные данные и значения содержимого не сохраняются. В журнал попадают только операции, отправленные на этот сайт Moodle.';
$string['auditrequestid'] = 'Идентификатор запроса';
$string['auditresults'] = 'Найдено записей аудита: {$a}';
$string['auditside'] = 'Сторона';
$string['auditstatus'] = 'Статус';
$string['audittool'] = 'Инструмент';
$string['audituser'] = 'Пользователь';
$string['audituserid'] = 'Идентификатор пользователя Moodle';
$string['auditwhen'] = 'Дата и время';
$string['authorizationdeniedbody'] = 'Войдите в действующую учетную запись Moodle, чтобы разрешить доступ ChatGPT, Claude или другому приложению ИИ с поддержкой протокола MCP. Гостевые учетные записи подключаться не могут.';
$string['authorizationdeniedtitle'] = 'Подключение не разрешено';
$string['authorize'] = 'Разрешить подключение';
$string['authorizeheading'] = 'Подключить {$a} к Moodle';
$string['authorizeintro'] = '{$a} запрашивает доступ к этому сайту Moodle.';
$string['backtodashboard'] = 'Вернуться на панель управления';
$string['cachedef_ratelimit'] = 'Кеш ограничений частоты запросов';
$string['cachedef_tooldefinitions'] = 'Кеш определений инструментов';
$string['cancel'] = 'Отмена';
$string['chatgptsetupcopied'] = 'URL сервера скопирован.';
$string['chatgptsetupcopy'] = 'Копировать URL';
$string['chatgptsetupcopyfallback'] = 'Выделите и скопируйте подсвеченный URL.';
$string['chatgptsetupdcrdisabled'] = 'Динамическая регистрация клиентов OAuth отключена. Включите ее перед подключением ChatGPT.';
$string['chatgptsetupdiscoveryhelp'] = 'Если обнаружение OAuth завершается ошибкой, проверьте метаданные, опубликованные Moodle MCP по этому адресу:';
$string['chatgptsetupfinish'] = 'После завершения авторизации обновите страницу. Руководство автоматически исчезнет, когда будет обнаружено активное подключение ChatGPT.';
$string['chatgptsetuphttpsdisabled'] = 'Для этого сайта Moodle не настроен адрес HTTPS. Чтобы подключить ChatGPT по протоколу MCP, сервер должен быть доступен из Интернета по HTTPS.';
$string['chatgptsetupintro'] = 'Подключите этот сайт Moodle к ChatGPT по протоколу MCP. Активных подключений ChatGPT пока нет. Выполните следующие действия для настройки безопасного доступа.';
$string['chatgptsetupnewtab'] = 'открывается в новой вкладке';
$string['chatgptsetupopenchatgpt'] = 'Открыть ChatGPT';
$string['chatgptsetupopensettings'] = 'Открыть настройки Moodle MCP';
$string['chatgptsetupserverlabel'] = 'URL сервера MCP';
$string['chatgptsetupstep1body'] = 'Нажмите кнопку ниже, чтобы открыть ChatGPT в новой вкладке и войти в свою учетную запись.';
$string['chatgptsetupstep1title'] = 'Открыть ChatGPT';
$string['chatgptsetupstep2body'] = 'В веб-версии ChatGPT откройте Plugins, нажмите + и выберите Add custom MCP server. Укажите имя Moodle MCP и выберите аутентификацию OAuth с динамической регистрацией клиентов (DCR).';
$string['chatgptsetupstep2title'] = 'Добавить пользовательский сервер MCP';
$string['chatgptsetupstep3body'] = 'Вставьте следующий адрес в поле Server URL в ChatGPT:';
$string['chatgptsetupstep3title'] = 'Введите URL этого сервера Moodle';
$string['chatgptsetupstep4body'] = 'Прочитайте предупреждение о безопасности, выберите I understand and want to continue и нажмите Create as a plugin. При необходимости установите созданный плагин.';
$string['chatgptsetupstep4title'] = 'Создать и установить плагин';
$string['chatgptsetupstep5body'] = 'Откройте диалог с ChatGPT и используйте интеграцию Moodle MCP. По запросу войдите в действующую учетную запись Moodle (не гостевую) и одобрите необходимые разрешения на чтение и запись. Инструменты ограничены вашими правами в Moodle.';
$string['chatgptsetupstep5title'] = 'Разрешить доступ к Moodle';
$string['chatgptsetuptitle'] = 'Подключить сайт Moodle к ChatGPT';
$string['chatgptsetuptroubleshooting'] = 'Устранение неполадок обнаружения OAuth';
$string['clientcolclientid'] = 'Идентификатор клиента';
$string['clientcolcreated'] = 'Создан';
$string['clientcoldynamic'] = 'Динамический';
$string['clientcolenabled'] = 'Включен';
$string['clientcollastused'] = 'Последнее использование';
$string['clientcolname'] = 'Название';
$string['clientcoltype'] = 'Тип';
$string['connectionactions'] = 'Действия';
$string['connectionactivelist'] = 'Активные подключения OAuth';
$string['connectionclient'] = 'Клиент';
$string['connectioncreated'] = 'Создано';
$string['connectionguideoauthresource'] = 'Ресурс';
$string['connectionguideoauthserverbase'] = 'Базовый URL сервера авторизации';
$string['connectionguideoauthregisterurl'] = 'URL регистрации';
$string['connectionguideoauthtokenurl'] = 'URL токена';
$string['connectionguideoauthauthorizeurl'] = 'URL авторизации';
$string['connectionguideacknowledgementhelp'] = 'В ChatGPT прочитайте уведомление о безопасности, установите флажок подтверждения и завершите создание кнопкой Create as plugin. На этой странице поля показаны только для примера.';
$string['connectionguiderisknotice'] = 'Пользовательские серверы MCP могут получать доступ к данным в пределах предоставленных разрешений. Подключайтесь только к доверенным серверам.';
$string['connectionguideacknowledgement'] = 'Я понимаю и хочу продолжить';
$string['connectionguideadvanced'] = 'Расширенные настройки OAuth';
$string['connectionguideadvancedhelp'] = 'ChatGPT обычно обнаруживает эти конечные точки автоматически. Если требуется ручной ввод, скопируйте значения ниже, особенно базовый URL сервера авторизации, оканчивающийся на /local/mcp/server.php.';
$string['connectionguideadvancedpreview'] = 'Просмотрите обнаруженные конечные точки OAuth или введите их вручную в ChatGPT.';
$string['connectionguideauthentication'] = 'Аутентификация';
$string['connectionguideconnected'] = 'Обнаружено активное подключение ChatGPT через OAuth. Эту инструкцию можно использовать для настройки еще одного подключения.';
$string['connectionguideconnection'] = 'Подключение';
$string['connectionguidecreate'] = 'Создать как плагин';
$string['connectionguidecreatehelp'] = 'Нажмите Create as plugin в ChatGPT, завершите установку, затем войдите в Moodle и одобрите запрошенные права ЧТЕНИЯ/ЗАПИСИ.';
$string['connectionguidedescription'] = 'Описание (необязательно)';
$string['connectionguidedescriptionexample'] = 'Доступ к курсам, занятиям и инструментам Moodle через ChatGPT.';
$string['connectionguidedescriptionhelp'] = 'Опишите назначение сервера. Например: Доступ к курсам, пользователям и занятиям Moodle.';
$string['connectionguidedocumentation'] = 'Прочитать полное руководство по подключению';
$string['connectionguideeyebrow'] = 'Moodle MCP · Настройка ChatGPT';
$string['connectionguidefinalbody'] = 'Откройте диалог с новым плагином, подключитесь через OAuth и разрешите доступ в Moodle. Авторизованные клиенты OAuth и подключения можно просматривать в разделе администрирования Moodle MCP.';
$string['connectionguidefinaltitle'] = 'После создания плагина';
$string['connectionguideformtitle'] = 'Добавить пользовательский сервер MCP';
$string['connectionguideicon'] = 'Значок (необязательно)';
$string['connectionguideiconhelp'] = 'Нажмите значок плюса в ChatGPT и загрузите собственный значок PNG. Используйте квадратное изображение размером не менее 256 × 256 пикселей и не более 10 КБ. Значок необязателен, но помогает отличать ваш Moodle.';
$string['connectionguideiconhint'] = 'Только PNG. Рекомендуется: не менее 256 × 256 пикселей, до 10 КБ.';
$string['connectionguideinstructionsintro'] = 'Сопоставьте каждое пронумерованное поле с примером слева.';
$string['connectionguideintro'] = 'Подключите Moodle к ChatGPT по протоколу MCP. Используйте поля диалога Add custom MCP server; эта страница служит иллюстрированным руководством, а само подключение создается в ChatGPT.';
$string['connectionguidename'] = 'Название';
$string['connectionguidenamehelp'] = 'Введите Moodle MCP или другое узнаваемое название, например Moodle MCP – Моя школа.';
$string['connectionguideoauthhelp'] = 'Выберите OAuth. Moodle MCP использует поток авторизации OAuth; ручной токен и вариант No authentication не нужны.';
$string['connectionguidepreviewnotice'] = 'Предварительный просмотр полей ChatGPT. Чтобы создать подключение, откройте ChatGPT и заполните форму.';
$string['connectionguidesafety'] = 'Подтверждение безопасности';
$string['connectionguidesafetyhelp'] = 'Прочитайте предупреждение и выберите I understand and want to continue, только если доверяете этому серверу Moodle.';
$string['connectionguidesteps'] = 'Как заполнить поля';
$string['connectionguidetitle'] = 'Подключить Moodle к ChatGPT';
$string['connectionguidetunnelmode'] = 'Туннель';
$string['connectionguideurlhelp'] = 'Выберите Server URL (не Tunnel). Скопируйте адрес из примера и вставьте его в ChatGPT. Используйте HTTPS-адрес, оканчивающийся на /local/mcp/server.php, без дополнительных параметров.';
$string['connectionguideurlmode'] = 'URL сервера';
$string['connectionlastused'] = 'Последнее использование';
$string['connectionnoneactive'] = 'Активных подключений OAuth нет.';
$string['connectionrevoke'] = 'Отозвать доступ';
$string['connectionrevoked'] = 'Подключение отозвано, а его токены аннулированы.';
$string['connections'] = 'Подключения';
$string['connectionscopes'] = 'Области доступа';
$string['connectionuser'] = 'Пользователь';
$string['dashboard'] = 'Панель управления';
$string['dashboardactivitychart'] = 'Запросы и изменения по дням';
$string['dashboardactivityheading'] = 'Активность инструментов';
$string['dashboardactivityhelp'] = 'Последние 7 дней в вашем часовом поясе. Учитываются запросы, предварительные просмотры, ожидающие подтверждения операции и ошибки. Данные Moodle изменяются только после утвержденных и завершенных операций.';
$string['dashboardcalls'] = 'Вызовы';
$string['dashboardclients'] = 'Активные клиенты OAuth';
$string['dashboardconnections'] = 'Активные подключения';
$string['dashboarddestructivetoday'] = 'Удаляющие изменения, выполненные сегодня';
$string['dashboardexamples'] = 'Примеры доступных действий';
$string['dashboardmoretools'] = 'Еще {$a}';
$string['dashboardnoactivity'] = 'За последние 7 дней не зарегистрировано операций ChatGPT, Claude или других приложений, подключенных через MCP.';
$string['dashboardoverview'] = 'Обзор интеграции ИИ (MCP)';
$string['dashboardplugin'] = 'Плагин';
$string['dashboardpluginschart'] = 'Доступные действия изменения по плагинам';
$string['dashboardquicklinks'] = 'Быстрые ссылки панели управления';
$string['dashboardreadtoday'] = 'Запросы сегодня';
$string['dashboardstatuschart'] = 'Статусы вызовов (последние 7 дней)';
$string['dashboardstatuserror'] = 'Ошибка';
$string['dashboardstatusok'] = 'Успешно завершено';
$string['dashboardstatuspending'] = 'Ожидает подтверждения';
$string['dashboardstatuspreview'] = 'Пробный запуск';
$string['dashboardtodaydescription'] = 'Состояние подключений приложений ИИ и операций, зарегистрированных сегодня.';
$string['dashboardtools'] = 'Инструменты';
$string['dashboardtotaltools'] = 'Доступные действия изменения';
$string['dashboardviewall'] = 'Показать все доступные действия изменения';
$string['dashboardwritecompletedtoday'] = 'Изменения, завершенные сегодня';
$string['dashboardwritesummary'] = 'Доступные действия изменения по плагинам';
$string['dashboardwritesummaryhelp'] = 'Действия изменения, автоматически обнаруженные в включенных плагинах Moodle.';
$string['dashboardwritetoday'] = 'Запросы на изменение сегодня';
$string['error'] = 'Ошибка API MCP';
$string['importgiftquestionbank'] = 'Импорт банка вопросов GIFT';
$string['importgiftquestionbank_desc'] = 'Импорт до 500 вопросов GIFT в кодировке UTF-8 в существующую категорию (categoryid) либо создание новой категории (name) за одну подтвержденную операцию.';
$string['mcp:manage'] = 'Управление Moodle MCP';
$string['newconnection'] = 'Новое подключение';
$string['oauthclientdisable'] = 'Отключить клиента';
$string['oauthclientdisabled'] = 'Клиент OAuth отключен, все его подключения и токены отозваны.';
$string['oauthclients'] = 'Клиенты OAuth';
$string['oauthconsentapplication'] = 'Внешнее приложение';
$string['oauthconsentconnection'] = 'Подключение между внешним приложением и этим сайтом Moodle';
$string['oauthconsentdestination'] = 'Ваш сайт Moodle';
$string['oauthconsenteyebrow'] = 'Запрос авторизации OAuth';
$string['oauthconsentfooter'] = 'Разрешайте доступ только знакомым приложениям, которым доверяете.';
$string['oauthconsentpermissions'] = 'Запрошенные разрешения';
$string['oauthconsentpermissionsintro'] = 'Проверьте, какие действия приложение сможет выполнять от вашего имени.';
$string['oauthconsentreadsummary'] = 'Разрешить ChatGPT, Claude или другому приложению через MCP просматривать информацию, доступную в инструментах Moodle только для чтения.';
$string['oauthconsentsecuritybody'] = 'Это подключение не может превышать права вашей учетной записи Moodle. Некоторые чувствительные операции требуют дополнительного подтверждения. Позднее доступ можно отозвать.';
$string['oauthconsentsecuritytitle'] = 'Ваши права Moodle продолжают действовать';
$string['oauthconsenttitle'] = 'Разрешить новое подключение';
$string['oauthconsentwritesummary'] = 'Разрешить ChatGPT, Claude или другому приложению через MCP создавать, изменять и удалять данные с помощью доступных вашей учетной записи инструментов записи при обязательном подтверждении.';
$string['pluginname'] = 'Moodle MCP';
$string['privacy:metadata:local_mcp_access_token'] = 'Хранит идентификаторы токенов доступа OAuth, связи с пользователями, области доступа, время использования и IP-адреса.';
$string['privacy:metadata:local_mcp_audit'] = 'Хранит метаданные безопасности и операций для активности MCP.';
$string['privacy:metadata:local_mcp_auth_code'] = 'Хранит метаданные одноразовых кодов авторизации OAuth, связанных с пользователем.';
$string['privacy:metadata:local_mcp_confirm'] = 'Хранит связанные с пользователем записи подтверждений для операций записи MCP.';
$string['privacy:metadata:local_mcp_connections'] = 'Хранит авторизованные подключения MCP.';
$string['privacy:metadata:local_mcp_refresh_token'] = 'Хранит метаданные токенов обновления OAuth, связи с пользователями и отметки времени их жизненного цикла.';
$string['privacy:metadata:mcp_client'] = 'Авторизованные приложения ИИ, например ChatGPT и Claude, подключенные по протоколу MCP, могут получать информацию из активных инструментов в пределах прав пользователя и запрошенных операций.';
$string['privacy:metadata:mcp_client:activities'] = 'Авторизованные инструменты MCP могут возвращать сведения о занятиях, отправленных работах и завершении.';
$string['privacy:metadata:mcp_client:courses'] = 'Авторизованные инструменты MCP могут возвращать сведения о курсах и зачислениях.';
$string['privacy:metadata:mcp_client:grades'] = 'Авторизованные инструменты MCP могут возвращать оценки и результаты оценивания.';
$string['privacy:metadata:mcp_client:otherdata'] = 'Другие данные из установленных инструментов MCP могут предоставляться по запросу авторизованного пользователя.';
$string['privacy:metadata:mcp_client:userid'] = 'Идентификаторы пользователей Moodle могут передаваться для определения авторизованного пользователя или результатов.';
$string['privacy:metadata:mcp_client:userprofile'] = 'Авторизованные инструменты MCP могут возвращать сведения профиля пользователя.';
$string['readapis'] = 'API ЧТЕНИЯ';
$string['readapiscount'] = 'Инструментов ЧТЕНИЯ: {$a}';
$string['readapisempty'] = 'Нет доступных инструментов ЧТЕНИЯ.';
$string['readapisviewall'] = 'Показать все инструменты ЧТЕНИЯ этого плагина';
$string['readpermissions'] = 'Разрешения на чтение';
$string['settings'] = 'Настройки';
$string['settingsaccessttl'] = 'Срок действия токена доступа';
$string['settingsaccessttl_desc'] = 'Срок действия токенов доступа OAuth по умолчанию.';
$string['settingsauditretention'] = 'Срок хранения аудита (дни)';
$string['settingscodettl'] = 'Срок действия кода авторизации';
$string['settingscodettl_desc'] = 'Коды авторизации действуют недолго и используются однократно.';
$string['settingsconfirmationttl'] = 'Срок действия подтверждения';
$string['settingsconfirmationttl_desc'] = 'Срок действия одноразового токена подтверждения ЗАПИСИ.';
$string['settingslogmessages'] = 'Записывать JSON-сообщения MCP в PHP error_log';
$string['settingsdebuglogging'] = 'Записывать диагностические события MCP в PHP error_log';
$string['settingsdebuglogging_desc'] = 'Регистрирует этапы вызовов MCP, результаты и очищенные метаданные исключений. По умолчанию отключено. Изменения config.php не требуются. Включайте временно для диагностики и ограничивайте доступ к журналам PHP.';
$string['settingslogmessages_desc'] = 'Дополнительная запись тела JSON-RPC на конечных точках server/read/write; по умолчанию отключена и действует только вместе с диагностическим журналом MCP. Известные секреты и двоичные данные скрываются, однако персональные данные Moodle могут попасть в журнал. Включайте временно, ограничивайте доступ к журналам PHP и затем отключайте.';
$string['settingsrateoauth'] = 'Запросов OAuth в минуту';
$string['settingsrateread'] = 'Запросов ЧТЕНИЯ в минуту';
$string['settingsratewrite'] = 'Запросов ЗАПИСИ в минуту';
$string['settingsrefreshttl'] = 'Срок действия токена обновления';
$string['settingsrefreshttl_desc'] = 'Срок действия токенов обновления OAuth по умолчанию.';
$string['subplugintype_mcptool'] = 'Инструмент активности MCP';
$string['subplugintype_mcptool_plural'] = 'Инструменты активности MCP';
$string['writeapis'] = 'API ЗАПИСИ';
$string['writeapiscolcapability'] = 'Право доступа';
$string['writeapiscolconfirmation'] = 'Подтверждение';
$string['writeapiscoldescription'] = 'Описание';
$string['writeapiscoldestructive'] = 'Разрушительное действие';
$string['writeapiscoldryrun'] = 'Пробный запуск';
$string['writeapiscolname'] = 'Название';
$string['writeapiscount'] = 'Инструментов ЗАПИСИ: {$a}';
$string['writeapisempty'] = 'Нет доступных инструментов ЗАПИСИ.';
$string['writeapisonlyread'] = 'Установленные плагины с API только для ЧТЕНИЯ';
$string['writeapisonlyreadhelp'] = 'Эти интеграции доступны через MCP ЧТЕНИЕ, но не ЗАПИСЬ. Откройте списки их API ЧТЕНИЯ, чтобы увидеть все инструменты. Порядок установки не меняет тип API.';
$string['writepermissions'] = 'Разрешения на запись';

$string['connectionguidetablabel'] = 'Выберите приложение ИИ';
$string['connectionguidetabother'] = 'Другой ИИ';
$string['connectionguideclaudetitle'] = 'Подключить Moodle к Claude';
$string['connectionguideclaudeintro'] = 'В Claude добавьте пользовательский веб-коннектор, указывающий на этот сервер MCP. Вход в Moodle осуществляется через OAuth.';
$string['connectionguideclaudestep1'] = 'В Claude откройте Customize > Connectors > Add custom connector. Владельцы организаций могут использовать Organization settings > Connectors > Add > Custom > Web.';
$string['connectionguideclaudestep2'] = 'Назовите коннектор, вставьте указанный выше URL сервера MCP и перейдите к настройкам аутентификации.';
$string['connectionguideclaudestep3'] = 'Выберите Sign in now или Sign in when needed, затем в OAuth client выберите Register automatically (DCR). Не выбирайте No sign in. Создавать секрет клиента не требуется.';
$string['connectionguideclaudestep4'] = 'Завершите добавление коннектора, нажмите Connect, войдите в Moodle и предоставьте необходимые разрешения. Включите коннектор для диалога с Claude перед использованием инструментов.';
$string['connectionguideclaudenote'] = 'Claude должен иметь доступ к серверу MCP по HTTPS. Для записи нужны область доступа mcp:write, права Moodle и явное подтверждение перед внесением изменений.';
$string['connectionguideopenclaude'] = 'Открыть Claude';
$string['connectionguideclaudedocs'] = 'Официальная документация Claude';
$string['connectionguideothertitle'] = 'Подключить другие приложения ИИ';
$string['connectionguideotherintro'] = 'Используйте эти настройки в клиентах, поддерживающих удаленные серверы MCP через Streamable HTTP и необходимый Moodle поток OAuth. Названия параметров различаются в разных приложениях.';
$string['connectionguideotherstep1'] = 'Добавьте удаленный сервер MCP с транспортом Streamable HTTP и укажите HTTPS-адрес выше. Не помещайте токен веб-службы Moodle в URL.';
$string['connectionguideotherstep2'] = 'Настройте OAuth Authorization Code с PKCE S256. Если поддерживается, используйте динамическую регистрацию клиентов (RFC 7591).';
$string['connectionguideotherstep3'] = 'Для обнаружения OAuth используйте базовый URL сервера авторизации, оканчивающийся на /local/mcp/server.php. Для ручной настройки используйте конечные точки ниже и задайте ресурс OAuth равным URL конечной точки MCP.';
$string['connectionguideotherstep4'] = 'Войдите в Moodle, одобрите mcp:read и при необходимости mcp:write. Проверьте доступные инструменты и отдельно подтверждайте каждую операцию записи.';
$string['connectionguideotheradvanced'] = 'URL для клиентов без автоматического обнаружения.';
$string['connectionguideothernote'] = 'Совместимость зависит от поддержки клиентом удаленного MCP, OAuth, PKCE и регистрации клиентов. local_mcp не использует токены в URL для аутентификации, а ручная настройка не требует доступа к Nginx.';
$string['connectionguidechatgptstarttitle'] = 'Начало работы с ChatGPT';
$string['connectionguidechatgptstartintro'] = 'Откройте раздел Plugins и выполните следующие действия, чтобы добавить этот сервер Moodle MCP.';
$string['connectionguidechatgptstartstep1'] = 'Откройте ChatGPT Plugins с помощью кнопки ниже.';
$string['connectionguidechatgptstartstep2'] = 'Нажмите Add в правом верхнем углу.';
$string['connectionguidechatgptstartstep3'] = 'Выберите Add custom MCP server и используйте URL из формы ниже.';
$string['connectionguidechatgptstartaction'] = 'Открыть ChatGPT Plugins';
$string['connectionguideclaudestarttitle'] = 'Начало работы с Claude';
$string['connectionguideclaudestartintro'] = 'Добавьте этот Moodle как пользовательский коннектор в настройках коннекторов Claude.';
$string['connectionguideclaudestartstep1'] = 'В Claude откройте Customize > Connectors.';
$string['connectionguideclaudestartstep2'] = 'Выберите Add custom connector, затем Web.';
$string['connectionguideclaudestartstep3'] = 'Введите URL сервера MCP, показанный ниже, и подключите учетную запись Moodle через OAuth.';
