# Operações MCP para tarefas e exclusão segura de módulos

Após atualizar os arquivos no servidor Moodle e executar a atualização de plugins, faça nova descoberta das ferramentas MCP e confirme a presença de `assign_create_activity` e `delete_course_module`.

## Criar tarefa

`assign_create_activity` aceita `courseid`, `sectionnum`, `name`, `intro` em HTML, `grade`, `file_enabled`, `onlinetext_enabled`, `maxfiles` e datas Unix opcionais. Por padrão, permite envio de um arquivo e correção docente, sem prazo pré-definido. A operação bloqueia submissões desativadas e nomes de tarefas repetidos na mesma seção. Exige `moodle/course:manageactivities`, `mod/assign:addinstance` e confirmação MCP antes da gravação.

## Excluir módulo

`delete_course_module` exige ID do curso, CMID, número da seção, tipo do módulo e nome exato. A prévia verifica os valores e as permissões, e o execute repete as verificações antes de excluir pelo Moodle. A exclusão é síncrona, permanente e exige confirmação separada. Não tente apagar uma página diferente da descrita na prévia; nunca execute exclusões apenas pelo nome.

## Correções pendentes do curso S-001 (curso ID 48)

Verificar cada par antes de solicitar exclusão:

| Seção | Preservar | Remover (apenas após confirmação) |
| --- | --- | --- |
| 1 | Página 481 | Página 486 |
| 4 | Página 482 | Página 485 |
| 5 | Página 480 | Página 488 |
| 6 | Página 483 | Página 489 |

Criar tarefas avaliativas reais para as unidades 1 a 7 a partir do conteúdo de `cursos/S-001/secoes/*/02-atividade-pratica.md` no repositório Citta, e criar a entrega do projeto integrador na seção 8 conforme `99-atividade-integradora/01-orientacoes.md`. Preserve os roteiros publicados, os questionários 498 a 504 e as 21 questões vinculadas. Confira as atividades no Moodle após cada criação.

**Importante:** o commit do GitHub não instala automaticamente o código no Moodle. São necessárias publicação no servidor, atualização de plugins, limpeza de caches se necessário, e redescoberta das ferramentas da conexão. A integração não deve ser tratada como pronta antes desse procedimento.
