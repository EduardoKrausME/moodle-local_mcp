# Diagnóstico do upload de capas dos cursos

A operação `set_course_image` aceita **PNG original**, JPEG ou WebP, enviados como `image_base64` (inclusive URI `data:image/png;base64,...`) ou `image_url` HTTPS pública. O limite por capa é 5 MiB decodificados. Referências `/mnt/data/...`, arquivos privados do ChatGPT ou URLs protegidas não são acessíveis ao servidor Moodle.

## Instalação

Implante a versão **1.3.8** do `local_mcp` no servidor Moodle (a atualização do GitHub não instala automaticamente a versão no servidor). Em seguida, execute a atualização normal do Moodle e limpe os caches:

```bash
cd /CAMINHO/DO/MOODLE
sudo -u apache php admin/cli/upgrade.php --non-interactive
sudo -u apache php admin/cli/purge_caches.php
```

Troque `apache` pelo usuário do PHP/servidor web se necessário. Confirme a versão de `local_mcp` na administração de plugins.

## Reprodução e logs

Depois de instalar a versão, faça **uma** tentativa de upload de PNG via `set_course_image` para um curso de teste da Citta ou para outro curso autorizado, usando a imagem real. Uma primeira resposta `confirmation_required` é **esperada**; a segunda chamada exige o mesmo payload e o token de confirmação retornado.

Na instalação Apache/PHP usada anteriormente, acompanhe em outro terminal:

```bash
sudo tail -F /var/log/httpd/php-error.log | grep --line-buffered -E 'course_image_|"tool":"set_course_image"'
```

Ou salve as linhas relevantes:

```bash
sudo grep '\[local_mcp\]' /var/log/httpd/php-error.log | tail -n 300 > /tmp/mcp-capas.log
```

Envie no chat o conteúdo de `/tmp/mcp-capas.log`, após conferir se há tokens ou outros dados privados em mensagens de terceiros. Também pode colar só as linhas do mesmo `request` (identificador de 12 caracteres), incluindo a linha `exception`.

Os eventos novos são:

- `course_image_preview_started` e `course_image_preview_ready`: criação da prévia/etapa de confirmação.
- `course_image_replace_started`, `course_image_source_received`: recebimento do payload.
- `course_image_download_started`, `course_image_download_finished`: apenas para URL HTTPS; o código HTTP aparece como `download_status`, sem registrar a URL.
- `course_image_decoded`: formato detectado, dimensões e bytes aceitos pelo decodificador.
- `course_image_storage_started`, `course_image_storage_committed`: operação do File API do Moodle.
- `course_image_replace_completed`: todo o fluxo terminou.
- `exception`: classe, código de erro, arquivo/linha e `phase` (`decode`, `file_storage` ou `post_commit`).

**Importante:** se `course_image_storage_committed` vier antes de uma falha `post_commit`, a imagem pode já estar gravada. Antes de repetir o upload, confira a imagem do curso com `get_course_image`.

A instrumentação **não registra** conteúdo Base64, URL completa, parâmetros de query, token de autenticação, token de confirmação ou arquivos enviados. Ela usa o `error_log()` do PHP e mantém o mesmo `request` em todos os eventos de uma tentativa.

Se nada aparecer no log Apache, localize o destino real do `error_log` no PHP-FPM/Apache com `phpinfo()` restrito a administradores ou confira a configuração de `error_log` do pool, sem expor publicamente o phpinfo.
