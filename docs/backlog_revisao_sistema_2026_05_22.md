# Backlog de Ajustes da Revisao do Sistema - 2026-05-22

Este backlog consolida os ajustes identificados apos nova revisao estatica do sistema, considerando as funcionalidades recentes de cadastro docente, reset de senha, tentativas pendentes e melhorias do prompt de IA.

## P0 - Corrigir cadeia de migrations e schema base

**Problema:** a pasta `database/migrations` ainda tem dois arquivos `002`, e o `001_create_tables.sql` funciona como schema base, mas nao reflete todas as colunas esperadas pelo codigo atual. Alem disso, algumas migrations incrementais tentam adicionar estruturas que ja existem no schema base, como `exercises.status` e `exercise_turmas`.

**Impacto:** uma instalacao limpa ou uma atualizacao em ambiente novo pode falhar antes mesmo do sistema subir.

**Evidencias:**
- `database/migrations/002_create_audit_logs.sql`
- `database/migrations/002_exercise_activation_workflow.sql`
- `database/migrations/001_create_tables.sql`
- `database/migrations/010_teacher_registration.sql`
- `database/migrations/011_answers_deduction_reasons.sql`

**Escopo:**
- Definir se `001_create_tables.sql` sera schema base atualizado ou migration historica.
- Renumerar migrations duplicadas sem quebrar ambientes existentes.
- Criar uma ordem oficial de execucao.
- Atualizar o schema base para conter as colunas atualmente exigidas pelo codigo, ou documentar que sempre deve ser seguido pelas migrations incrementais.
- Evitar `ADD COLUMN` duplicado em ambientes limpos.

**Criterios de aceite:**
- Instalacao limpa executa sem erro.
- Banco antigo consegue aplicar migrations incrementais sem conflito.
- Existe documento com ordem oficial de migrations.
- Colunas recentes ficam garantidas: `attempts.turma_id`, `users.must_change_password`, `users.password_reset_token_hash`, `users.registration_note`, `answers.deduction_reasons_json`.

**Risco:** alto.

## P0 - Tornar migrations recentes seguras para bases com dados existentes

**Problema:** a migration `011_answers_deduction_reasons.sql` adiciona chave estrangeira entre `injection_logs.answer_id` e `answers.id`, mas bases antigas podem ter logs com `answer_id` apontando para respostas apagadas, pois antes nao havia FK.

**Impacto:** a migration pode falhar em producao se existirem logs orfaos.

**Evidencias:**
- `database/migrations/011_answers_deduction_reasons.sql`
- `database/migrations/001_create_tables.sql`
- `app/Services/OpenAIService.php`

**Escopo:**
- Antes de criar a FK, limpar ou neutralizar logs orfaos com `answer_id` invalido.
- Adicionar passo de verificacao pre-migration.
- Avaliar se a FK deve usar `ON DELETE SET NULL`, mantendo historico sem quebrar exclusoes.
- Documentar rollback seguro.

**Criterios de aceite:**
- Migration roda mesmo em base com logs antigos.
- Logs orfaos nao impedem deploy.
- Logs validos continuam associados as respostas.
- Exclusao de tentativa/resposta nao quebra o historico de seguranca.

**Risco:** alto.

## P1 - Revalidar sessao do usuario contra o banco

**Problema:** `Core\Auth` usa os dados gravados na sessao no momento do login. Se um admin inativar, rejeitar, trocar role ou exigir nova senha de um usuario ja logado, a sessao pode continuar com permissoes antigas ate logout.

**Impacto:** usuario desativado ou alterado pode manter acesso temporario.

**Evidencias:**
- `core/Auth.php`
- `app/Controllers/AdminController.php`
- `app/Models/User.php`

**Escopo:**
- Recarregar status, role e `must_change_password` do banco em `requireAuth()`.
- Encerrar sessao se o usuario estiver `inactive` ou `rejected`.
- Atualizar sessao se role ou obrigatoriedade de troca de senha mudarem.
- Evitar consulta excessiva, se necessario com cache curto em sessao.

**Criterios de aceite:**
- Usuario inativado perde acesso na proxima requisicao autenticada.
- Troca de role passa a refletir sem exigir logout manual.
- `must_change_password` definido pelo admin redireciona para troca de senha na proxima requisicao.
- Nao quebra login publico nem reset por token.

**Risco:** medio-alto.

## P1 - Bloquear acesso direto ao resultado de tentativa nao corrigida

**Problema:** a lista do aluno so mostra link de resultado para tentativas `graded`, mas a rota `/student/attempts/{id}/result` nao valida explicitamente o status. Se acessada diretamente com tentativa `submitted`, a view pode exibir nota nula como `0.0`.

**Impacto:** aluno pode interpretar tentativa pendente como nota zero.

**Evidencias:**
- `app/Controllers/AttemptController.php`
- `views/student/results/show.php`
- `views/student/exercises/show.php`

**Escopo:**
- Em `AttemptController::result`, permitir resultado apenas para status `graded`.
- Para status `submitted`, redirecionar para o exercicio com mensagem "Em correcao".
- Para status `in_progress`, redirecionar para a tentativa em andamento ou para o exercicio.

**Criterios de aceite:**
- Tentativa pendente nao mostra pagina de resultado.
- Tentativa pendente nunca aparece como nota `0.0`.
- Usuario recebe mensagem clara de que a correcao ainda esta em andamento.

**Risco:** medio.

## P1 - Registrar auditoria quando a correcao inicial por IA falhar

**Problema:** o reprocessamento registra auditoria em caso de falha, mas a falha inicial durante `submit()` atualmente fica apenas em `error_log`.

**Impacto:** suporte e administracao nao conseguem rastrear facilmente falhas iniciais da IA pela tela de auditoria.

**Evidencias:**
- `app/Controllers/AttemptController.php`
- `app/Services/AttemptGradingService.php`

**Escopo:**
- Registrar auditoria `student.attempt.grading_failed` ou equivalente quando a avaliacao inicial falhar.
- Guardar `attempt_id`, `exercise_id`, `student_id` e mensagem tecnica resumida.
- Evitar expor dados sensiveis da resposta do aluno no metadata.

**Criterios de aceite:**
- Falha inicial aparece na auditoria.
- Tentativa permanece `submitted`.
- Painel de pendencias continua exibindo a tentativa para reprocessamento.

**Risco:** medio.

## P1 - Separar historico de solicitacoes docentes de docentes criados manualmente

**Problema:** o historico de solicitacoes docentes consulta todos os usuarios `teacher` com status `active` ou `rejected`. Isso pode misturar docentes criados manualmente pelo admin com docentes que realmente passaram pelo fluxo publico de solicitacao.

**Impacto:** historico administrativo pode ficar ambigueo.

**Evidencias:**
- `app/Models/User.php`
- `app/Controllers/AdminController.php`
- `views/admin/teacher_requests/history.php`

**Escopo:**
- Criar um marcador explicito de origem da solicitacao, como `registration_source` ou `teacher_registration_requested_at`.
- Filtrar historico apenas para solicitacoes do fluxo publico.
- Manter docentes manuais visiveis na tela geral de usuarios.

**Criterios de aceite:**
- Historico de solicitacoes mostra apenas cadastros solicitados publicamente.
- Docentes criados manualmente nao aparecem como solicitacao aprovada.
- Auditoria continua mostrando aprovacao/rejeicao.

**Risco:** medio.

## P2 - Exibir motivos de desconto da IA para professor/admin

**Problema:** `deduction_reasons` agora sao persistidos, mas ainda nao aparecem em uma tela operacional.

**Impacto:** o dado existe no banco, mas ainda tem pouco valor pedagogico e de auditoria.

**Evidencias:**
- `app/Models/Answer.php`
- `app/Services/AttemptGradingService.php`
- `views/student/results/show.php`

**Escopo:**
- Criar exibicao controlada dos motivos de desconto no detalhe da tentativa para professor/admin.
- Traduzir codigos internos para labels em portugues.
- Evitar mostrar ao aluno se a intencao for manter feedback simplificado.

**Criterios de aceite:**
- Professor/admin ve os motivos salvos por questao.
- Codigos ficam legiveis.
- Respostas antigas sem motivos continuam renderizando normalmente.

**Risco:** baixo-medio.

## P2 - Remover metodos legados de reset por senha temporaria

**Problema:** apos o reset por token, ainda existem metodos legados sem uso para senha temporaria.

**Impacto:** aumenta ambiguidade de manutencao e pode levar um futuro ajuste a reintroduzir fluxo menos seguro.

**Evidencias:**
- `app/Models/User.php`
- `app/Controllers/AdminController.php`

**Escopo:**
- Remover `User::resetPassword()`, se realmente nao houver chamadas.
- Remover `AdminController::generateTemporaryPassword()`, se realmente nao houver chamadas.
- Confirmar com busca global antes da remocao.

**Criterios de aceite:**
- Busca por `resetPassword(` nao encontra metodo legado de senha temporaria sem uso.
- Busca por `generateTemporaryPassword` nao encontra metodo morto.
- Fluxo por token continua funcionando.

**Risco:** baixo.

## P2 - Melhorar documentacao operacional de configuracoes

**Problema:** novas chaves como `OPENAI_MODEL` e `teacher_registration_enabled` existem, mas ainda falta uma documentacao operacional unica para deploy, ambiente e administracao.

**Impacto:** dificulta manutencao em ambiente de producao.

**Evidencias:**
- `.env.example`
- `config/openai.php`
- `app/Models/SystemSetting.php`
- `docs/backlog_cadastro_docente.md`

**Escopo:**
- Criar documento `docs/deploy_operacional.md`.
- Documentar variaveis `.env`.
- Documentar ordem de migrations.
- Documentar como habilitar/desabilitar cadastro docente.
- Documentar rotina de reprocessamento de tentativas pendentes.

**Criterios de aceite:**
- Novo mantenedor consegue subir ambiente limpo seguindo o documento.
- Admin entende configuracoes de cadastro docente e IA.
- Ordem de migrations fica visivel.

**Risco:** baixo.

## Ordem Sugerida

1. Corrigir cadeia de migrations e schema base.
2. Tornar migrations recentes seguras para bases com dados existentes.
3. Revalidar sessao do usuario contra o banco.
4. Bloquear acesso direto ao resultado de tentativa nao corrigida.
5. Registrar auditoria quando a correcao inicial por IA falhar.
6. Separar historico de solicitacoes docentes de docentes criados manualmente.
7. Remover metodos legados de reset por senha temporaria.
8. Exibir motivos de desconto da IA para professor/admin.
9. Melhorar documentacao operacional de configuracoes.

## Observacoes

- Os dois primeiros itens devem vir antes de qualquer novo deploy em ambiente limpo ou base antiga.
- A revalidacao de sessao e importante para fechar lacunas de seguranca administrativa.
- O bloqueio do resultado pendente evita ruído pedagogico, especialmente quando a IA falhar e a tentativa ficar em `submitted`.
