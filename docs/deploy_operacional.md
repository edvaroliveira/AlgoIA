# Deploy Operacional

Este documento resume os passos operacionais para subir ou atualizar o AlgoIA.

## Banco de Dados

### Instalacao limpa

Para um ambiente novo, use o schema consolidado:

1. Criar o banco vazio com charset `utf8mb4`.
2. Executar `database/migrations/001_create_tables.sql`.
3. Nao executar as migrations incrementais `002` a `014` em seguida, pois elas existem para atualizar bases antigas.

O arquivo `001_create_tables.sql` contem o schema atual consolidado, incluindo auditoria, configuracoes, cadastro docente, contexto de turma em tentativas, reset por token e motivos de desconto da IA.

### Atualizacao de base existente

Para um ambiente que ja foi criado com schema antigo, nao reexecute `001_create_tables.sql`. Aplique as migrations incrementais ainda nao executadas, nesta ordem:

1. `002_create_audit_logs.sql`
2. `002_exercise_activation_workflow.sql`
3. `003_exercise_publication_by_turma.sql`
4. `004_fix_attempts_exercise_delete_cascade.sql`
5. `005_add_admin_role.sql`
6. `006_add_admin_review_flags.sql`
7. `007_attempt_turma_context.sql`
8. `008_user_must_change_password.sql`
9. `009_password_reset_tokens.sql`
10. `010_teacher_registration.sql`
11. `011_answers_deduction_reasons.sql`
12. `012_user_registration_source.sql`
13. `013_login_attempts.sql`
14. `014_grading_jobs.sql`
15. `015_fix_exercises_turma_fk.sql`

Observacao: existem dois arquivos iniciados por `002` por historico do projeto. A ordem acima e a referencia oficial.

A migration `015` recria a FK `fk_ex_turma` com `ON DELETE SET NULL`, permitindo excluir turma com exercicio referenciado sem erro. Esse comportamento ja vem no schema consolidado `001` para instalacoes limpas; a `015` corrige apenas bases criadas com a versao antiga da `001`.

As migrations `010`, `011`, `012`, `013` e `014` usam verificacoes ou criacao idempotente para evitar erro quando uma coluna, indice, chave estrangeira ou tabela ja existir. Ainda assim, migrations historicas anteriores devem ser aplicadas uma unica vez e na ordem indicada.

Antes de atualizar uma base de producao:

1. Fazer backup do banco.
2. Confirmar quais migrations ja foram aplicadas.
3. Aplicar somente as migrations pendentes.
4. Se a base recebeu ajustes manuais, validar colunas existentes antes de executar arquivos antigos com `ADD COLUMN`.
5. Executar `php bin/smoke_static.php` para validar invariantes basicos do codigo antes do smoke test funcional.
6. Executar `php bin/smoke_schema.php` apos aplicar migrations para confirmar que o schema esperado esta disponivel.

## Variaveis de Ambiente

Configurar no `.env`:

- `APP_NAME`
- `APP_ENV`
- `APP_URL`
- `APP_DEBUG`
- `DB_HOST`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `OPENAI_API_KEY`
- `OPENAI_MODEL`

`OPENAI_MODEL` tem fallback em `config/openai.php`, mas deve ser definido no ambiente para facilitar troca sem edicao de codigo.

## Cadastro Publico de Docentes

O cadastro publico de docentes nasce desabilitado.

Para habilitar ou desabilitar:

1. Entrar como administrador.
2. Acessar a area de solicitacoes docentes.
3. Usar a acao de alternar cadastro publico.

A configuracao e salva em `system_settings.teacher_registration_enabled` e toda alteracao gera auditoria.

## Correcao por IA

Quando a avaliacao automatica falhar:

- A tentativa fica com status `submitted`.
- O aluno ve a tentativa como "Em correcao".
- Admin e docente visualizam a pendencia nos paineis de correcoes pendentes.
- O reprocessamento pode ser acionado pelo painel.
- A falha inicial e as falhas de reprocessamento sao registradas em auditoria.

O submit do aluno apenas enfileira a correcao em `grading_jobs`. Configure um cron ou tarefa agendada para executar:

```bash
php bin/process_grading_jobs.php 10
```

O argumento numerico define o maximo de jobs processados por execucao. Jobs com falha ficam recuperaveis para nova tentativa automatica ou reprocessamento manual.
Jobs que ficarem travados como `processing` por mais de 15 minutos sao recuperados pelo proprio worker e voltam ao ciclo de tentativa. Quando uma tentativa e corrigida manualmente, o job correspondente e marcado como concluido para evitar alerta falso.

Para validar a fila sem chamar a OpenAI, use:

```bash
php bin/process_grading_jobs.php --dry-run 10
```

## Prompt e Auditoria Pedagogica

O prompt de IA usa rubrica por faixa de pontuacao e retorna `deduction_reasons`.

Os motivos de desconto sao salvos em `answers.deduction_reasons_json` e podem ser consultados por professor/admin no detalhe da tentativa corrigida.

Tentativas suspeitas de prompt injection sao registradas em `injection_logs`.

### Politica de privacidade e retencao dos `injection_logs`

- **Redacao por truncamento:** o registro guarda apenas um trecho redigido da resposta do aluno (ate 500 caracteres, com marcador de truncamento), nunca o conteudo integral. Vide `OpenAIService::buildInjectionLogSummary`.
- **Uso restrito:** o conteudo serve para revisao manual de incidentes; as telas de professor/admin usam apenas a contagem de ocorrencias (`injection_flag_count`), nao o texto.
- **Retencao:** registros sao apagados apos 180 dias (`App\Models\InjectionLog::RETENTION_DAYS`). A limpeza roda automaticamente ao final de cada execucao do worker `bin/process_grading_jobs.php`, portanto nao requer cron adicional alem do ja configurado para a fila.
