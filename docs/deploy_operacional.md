# Deploy Operacional

Este documento resume os passos operacionais para subir ou atualizar o AlgoIA.

## Banco de Dados

### Instalacao limpa

Para um ambiente novo, use o schema consolidado:

1. Criar o banco vazio com charset `utf8mb4`.
2. Executar `database/migrations/001_create_tables.sql`.
3. Nao executar as migrations incrementais `002` a `012` em seguida, pois elas existem para atualizar bases antigas.

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

Observacao: existem dois arquivos iniciados por `002` por historico do projeto. A ordem acima e a referencia oficial.

As migrations `010`, `011` e `012` usam verificacoes em `INFORMATION_SCHEMA` para evitar erro quando uma coluna, indice ou chave estrangeira ja existir. Ainda assim, migrations historicas anteriores devem ser aplicadas uma unica vez e na ordem indicada.

Antes de atualizar uma base de producao:

1. Fazer backup do banco.
2. Confirmar quais migrations ja foram aplicadas.
3. Aplicar somente as migrations pendentes.
4. Se a base recebeu ajustes manuais, validar colunas existentes antes de executar arquivos antigos com `ADD COLUMN`.

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

## Prompt e Auditoria Pedagogica

O prompt de IA usa rubrica por faixa de pontuacao e retorna `deduction_reasons`.

Os motivos de desconto sao salvos em `answers.deduction_reasons_json` e podem ser consultados por professor/admin no detalhe da tentativa corrigida.

Tentativas suspeitas de prompt injection sao registradas em `injection_logs` sem armazenar o conteudo completo da resposta do aluno.
