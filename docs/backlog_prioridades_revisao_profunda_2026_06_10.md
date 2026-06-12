# Backlog de Prioridades da Revisao Profunda - 2026-06-10

Este backlog transforma os principais achados da revisao profunda do AlgoIA em
itens executaveis de engenharia. O foco e preservar dados academicos, garantir
consistencia das tentativas, endurecer a fila de correcao e ampliar a cobertura
automatizada dos fluxos criticos.

O documento foi atualizado apos uma segunda reavaliacao profunda em 2026-06-10.
Essa reavaliacao confirmou implementacoes parciais dos itens originais e
identificou novas falhas nas transicoes de submit, schema consolidado, lease do
worker, reprocessamento manual e cobertura de testes.

## Premissas

- Preservacao de tentativas, respostas, notas e auditoria tem prioridade sobre
  conveniencia de exclusao.
- Mudancas de estado criticas devem ser atomicas e protegidas contra requests
  concorrentes.
- Novas alteracoes de schema devem ser feitas por migrations incrementais; nao
  editar migrations historicas ja aplicadas em producao.
- O comportamento de moderacao deve ser consistente entre publicacao, exibicao
  ao aluno e correcao por IA.
- Itens P0 devem ser entregues antes de novas evolucoes funcionais.

## Modelo de Prioridade

- **P0:** risco direto de perda de dados ou quebra da integridade da avaliacao.
- **P1:** risco operacional relevante, inconsistencia funcional ou seguranca.
- **P2:** confiabilidade de deploy, testes e manutencao preventiva.

## Resumo Executivo Atualizado

| ID | Item | Prioridade | Situacao |
|---|---|---|---|
| RP-01 | Bloquear exclusao destrutiva de exercicios publicados | P0 | Implementado; manter teste |
| RP-02 | Tornar o submit da tentativa atomico | P0 | Parcial; ver RP-09 e RP-10 |
| RP-03 | Proteger inicio e limite de tentativas contra concorrencia | P0 | Parcial; exige teste MySQL e RP-10 |
| RP-04 | Aplicar bloqueio de moderacao por questao | P1 | Pendente |
| RP-05 | Implementar lease seguro e heartbeat no worker | P1 | Parcial; heartbeat desconectado |
| RP-06 | Neutralizar formulas nas exportacoes CSV | P1 | Implementado; melhorar teste |
| RP-07 | Criar testes de integracao dos fluxos criticos | P1 | Pendente |
| RP-08 | Alinhar migrations, documentacao e smoke de schema | P2 | Parcial; ver RP-11 |
| RP-09 | Corrigir falso sucesso e auditoria duplicada no submit | P0 | Novo |
| RP-10 | Revalidar publicacao dentro das transacoes | P1 | Novo |
| RP-11 | Manter schema consolidado compativel com o runtime | P0 | Novo |
| RP-12 | Unificar reprocessamento manual e worker | P1 | Novo |
| RP-13 | Endurecer transicoes, heartbeat e recuperacao de jobs | P1 | Novo |
| RP-14 | Substituir testes replicados por testes da implementacao real | P1 | Novo |
| RP-15 | Restringir mudancas de role que violem o dominio | P2 | Novo |

---

## Epic RP-01 - Preservacao do Historico Academico

### RP-01 Bloquear exclusao destrutiva de exercicios publicados

**Prioridade:** P0

**Situacao apos reavaliacao:** implementado. Manter como regra de nao regressao.

**Problema:** o docente pode excluir um exercicio em qualquer estado. Como as
tentativas possuem FK com `ON DELETE CASCADE`, a acao pode apagar tentativas,
respostas, notas, jobs e parte da rastreabilidade academica.

**Objetivo:** permitir exclusao fisica somente quando ela nao puder destruir
historico academico.

**Escopo:**

- Permitir exclusao fisica apenas de exercicio em estado `draft`, sem tentativas.
- Bloquear exclusao de exercicios `ready`, `active` ou que possuam tentativas.
- Exibir mensagem clara informando por que a exclusao foi bloqueada.
- Avaliar a criacao futura de arquivamento logico para exercicios encerrados.
- Registrar auditoria tanto da exclusao permitida quanto da tentativa bloqueada.

**Criterios de aceite:**

- Docente consegue excluir um rascunho proprio sem tentativas.
- Docente nao consegue excluir exercicio publicado ou com tentativa associada.
- Tentativas, respostas, notas e jobs existentes permanecem preservados.
- Request manipulado diretamente recebe bloqueio no backend.
- Existe teste automatizado para os cenarios permitido e bloqueado.

**Arquivos candidatos:**

- `app/Controllers/ExerciseController.php`
- `app/Models/Exercise.php`
- `views/teacher/exercises/show.php`
- `bin/run_db_tests.php` ou nova suite de integracao

**Risco de implementacao:** baixo. A principal decisao futura e se exercicios
nao excluiveis devem ganhar estado de arquivamento.

---

## Epic RP-02 - Consistencia Transacional de Tentativas

### RP-02 Tornar o submit da tentativa atomico

**Prioridade:** P0

**Situacao apos reavaliacao:** parcialmente implementado por
`AttemptSubmissionService`. A transacao protege respostas, estado e enqueue,
mas o controller ainda comunica sucesso apos rollback e a publicacao nao e
revalidada dentro da operacao protegida. Complementar com RP-09 e RP-10.

**Problema:** o submit salva respostas, altera a tentativa para `submitted` e
cria o job de correcao em operacoes separadas. Falha ou concorrencia entre essas
etapas pode deixar respostas sobrescritas, tentativa sem job ou auditoria
inconsistente.

**Objetivo:** garantir que uma tentativa seja enviada uma unica vez, com todas
as respostas persistidas e job de correcao criado de forma consistente.

**Escopo:**

- Extrair um servico transacional de submissao de tentativa.
- Bloquear a linha da tentativa com `SELECT ... FOR UPDATE`.
- Revalidar ownership, estado `in_progress` e janela da publicacao dentro da
  operacao protegida.
- Salvar todas as respostas, inclusive vazias.
- Alterar a tentativa para `submitted` apenas se o estado ainda for valido.
- Criar ou garantir o job de correcao na mesma unidade transacional.
- Tornar o submit idempotente para repeticao acidental do mesmo request.
- Registrar auditoria somente apos confirmacao da operacao.

**Criterios de aceite:**

- Falha ao salvar uma resposta impede a transicao para `submitted`.
- Falha ao criar o job nao deixa tentativa enviada sem mecanismo de correcao.
- Dois submits concorrentes nao sobrescrevem respostas nem duplicam efeitos.
- Apenas uma auditoria de submissao bem-sucedida e registrada.
- Repetir o request apos sucesso retorna resultado controlado e nao altera dados.

**Arquivos candidatos:**

- novo `app/Services/AttemptSubmissionService.php`
- `app/Controllers/AttemptController.php`
- `app/Models/Attempt.php`
- `app/Models/Answer.php`
- `app/Models/GradingJob.php`

**Observacao tecnica:** o enqueue atual usa `ON DUPLICATE KEY`, mas isso sozinho
nao torna o fluxo completo atomico.

### RP-03 Proteger inicio e limite de tentativas contra concorrencia

**Prioridade:** P0

**Dependencia:** RP-02, para reutilizar o padrao transacional definido.

**Situacao apos reavaliacao:** parcialmente implementado por
`AttemptStartService` e migration `018`. A eficacia depende de gap lock,
isolamento e indice MySQL reais; ainda nao existe teste de concorrencia no CI.

**Problema:** contagem de tentativas usadas, busca de tentativa em andamento e
criacao de nova tentativa sao operacoes separadas. Requests simultaneos podem
criar mais de uma tentativa em andamento ou ultrapassar `max_attempts`.

**Objetivo:** garantir o limite definido pela publicacao mesmo sob requests
concorrentes.

**Escopo:**

- Extrair um servico transacional de inicio de tentativa.
- Bloquear o contexto relevante do aluno/publicacao durante a verificacao.
- Reutilizar tentativa `in_progress` existente de forma deterministica.
- Impedir que duas tentativas `in_progress` sejam criadas para o mesmo aluno,
  exercicio e turma.
- Avaliar constraint, tabela de controle ou lock explicito compativel com MySQL.
- Preservar a regra de `max_attempts = 0` como ilimitado.

**Criterios de aceite:**

- Requests concorrentes de inicio retornam a mesma tentativa em andamento.
- O limite de tentativas nao pode ser ultrapassado por concorrencia.
- Limite ilimitado continua funcionando.
- Tentativas de turmas diferentes permanecem isoladas.
- Existe teste de concorrencia ou teste transacional equivalente documentado.

**Arquivos candidatos:**

- novo `app/Services/AttemptStartService.php`
- `app/Controllers/AttemptController.php`
- `app/Models/Attempt.php`
- nova migration incremental, se necessaria

**Risco de implementacao:** medio. MySQL nao suporta indice unico parcial simples
para apenas linhas `in_progress`; a estrategia de lock deve ser definida antes
da migration.

---

## Epic RP-03 - Moderacao Consistente

### RP-04 Aplicar bloqueio de moderacao por questao

**Prioridade:** P1

**Situacao apos reavaliacao:** pendente. O bloqueio da questao continua sem
efeito na exibicao, autosave, submit, correcao e nota maxima.

**Problema:** o admin consegue marcar uma questao como `blocked`, mas as
consultas do aluno e o pipeline de correcao continuam carregando a questao.

**Objetivo:** fazer o estado de moderacao da questao produzir um comportamento
previsivel e seguro em todo o sistema.

**Decisao de produto necessaria:**

- Opcao recomendada: bloquear automaticamente o exercicio/publicacoes quando
  qualquer questao ativa for bloqueada.
- Alternativa: excluir a questao bloqueada do fluxo do aluno e recalcular a
  pontuacao maxima, exigindo regras adicionais para tentativas existentes.

**Escopo recomendado:**

- Ao bloquear uma questao, fechar as publicacoes do exercicio ou bloquear o
  exercicio como um todo.
- Impedir nova publicacao enquanto houver questao bloqueada.
- Impedir inicio, autosave e submit quando o exercicio estiver bloqueado.
- Definir tratamento de tentativas `in_progress` e `submitted` existentes.
- Exibir a causa do bloqueio somente para professor/admin.
- Registrar na auditoria os efeitos aplicados pela moderacao.

**Criterios de aceite:**

- Questao bloqueada nunca e exibida ou enviada para correcao como se estivesse
  aprovada.
- Exercicio com questao bloqueada nao pode ser publicado ou respondido.
- Professor/admin ve o motivo e o impacto operacional.
- Aluno recebe mensagem generica, sem acesso a nota interna de moderacao.
- Desbloqueio nao reabre publicacoes automaticamente sem acao explicita.

**Arquivos candidatos:**

- `app/Controllers/AdminExerciseController.php`
- `app/Models/Exercise.php`
- `app/Models/Question.php`
- `app/Services/AttemptGradingService.php`

---

## Epic RP-04 - Resiliencia da Fila de Correcao

### RP-05 Implementar lease seguro e heartbeat no worker

**Prioridade:** P1

**Situacao apos reavaliacao:** parcialmente implementado. `worker_id` e
`renewLease()` existem, mas o heartbeat nao e chamado pelo processamento.
Complementar com RP-13.

**Problema:** jobs em `processing` sao recuperados apos 15 minutos, mas o worker
nao atualiza heartbeat durante uma correcao longa. Um segundo worker pode
recuperar e processar o mesmo job enquanto o primeiro ainda esta ativo.

**Objetivo:** impedir processamento concorrente do mesmo job sem perder a
capacidade de recuperar workers realmente interrompidos.

**Escopo:**

- Adicionar identificador de lock/worker ao job.
- Renovar `locked_at` durante o processamento, ao menos entre respostas.
- Exigir o identificador do lock para concluir ou falhar um job.
- Recuperar apenas jobs cujo lease realmente expirou.
- Limpar lock ao marcar job como `completed` ou `failed`.
- Tornar configuravel o timeout de lease.
- Registrar recuperacao de job travado em auditoria ou log operacional.

**Criterios de aceite:**

- Dois workers nao avaliam simultaneamente o mesmo job.
- Worker ativo renova o lease antes da expiracao.
- Worker interrompido continua recuperavel apos o timeout.
- Worker antigo nao consegue marcar como concluido um job assumido por outro.
- Testes cobrem claim, heartbeat, expiracao, recuperacao e conclusao.

**Arquivos candidatos:**

- nova migration incremental para `grading_jobs`
- `app/Models/GradingJob.php`
- `app/Services/GradingJobProcessor.php`
- `app/Services/AttemptGradingService.php`
- `bin/process_grading_jobs.php`

**Risco de implementacao:** medio-alto. A alteracao deve preservar jobs
existentes e permitir deploy gradual entre codigo e migration.

---

## Epic RP-05 - Seguranca das Exportacoes

### RP-06 Neutralizar formulas nas exportacoes CSV

**Prioridade:** P1

**Situacao apos reavaliacao:** implementado. O teste atual replica a funcao em
um helper local; substituir por teste da implementacao real conforme RP-14.

**Problema:** valores controlados por usuarios sao exportados diretamente para
CSV. Planilhas podem interpretar celulas iniciadas por `=`, `+`, `-` ou `@`
como formulas.

**Objetivo:** garantir que todos os CSVs administrativos sejam tratados como
dados, nunca como formulas executaveis.

**Escopo:**

- Criar sanitizador central para celulas CSV.
- Prefixar valores perigosos de acordo com estrategia compativel com Excel e
  LibreOffice.
- Aplicar o sanitizador a todas as exportacoes CSV.
- Preservar valores numericos e datas gerados internamente quando apropriado.
- Documentar o comportamento.

**Criterios de aceite:**

- Nome, email, titulo ou metadado iniciado por `=`, `+`, `-` ou `@` nao executa
  como formula ao abrir a exportacao.
- Exportacoes existentes continuam legiveis.
- A protecao e centralizada e nao depende de cada controller.
- Testes cobrem todos os prefixos perigosos e valores normais.

**Arquivos candidatos:**

- `app/Controllers/AdminBaseController.php`
- `app/Controllers/AdminAuditController.php`
- `bin/run_tests.php`

---

## Epic RP-06 - Testes dos Fluxos Criticos

### RP-07 Criar testes de integracao dos fluxos criticos

**Prioridade:** P1

**Situacao apos reavaliacao:** pendente. A suite atual passa, mas nao executa os
servicos transacionais reais nem concorrencia MySQL.

**Dependencias:** implementar junto com RP-01 a RP-06, tornando os testes parte
dos criterios de conclusao de cada item.

**Problema:** a suite atual cobre utilitarios, invariantes estaticas e poucos
metodos de dados, mas nao protege os fluxos com maior risco de regressao.

**Objetivo:** criar uma malha automatizada para as regras de negocio e
transicoes de estado que sustentam o produto.

**Escopo minimo:**

- Exclusao permitida e bloqueada de exercicio.
- Inicio concorrente e limite de tentativas.
- Submit atomico, idempotente e com falhas simuladas.
- Moderacao de exercicio e questao.
- Claim, heartbeat, retry e recuperacao de jobs.
- Autorizacao negativa entre aluno, teacher e admin.
- Exportacao CSV com formula injection neutralizada.
- Fluxo ponta a ponta: aluno inicia, responde, envia, job corrige e resultado
  fica disponivel.

**Criterios de aceite:**

- Testes rodam de forma repetivel sem depender do banco de producao.
- Banco de teste pode ser recriado automaticamente.
- CI executa a nova suite.
- Cada bug corrigido neste backlog possui teste de regressao.
- Falha na suite impede merge.

**Estrategia sugerida:**

- Manter testes puros em `bin/run_tests.php`.
- Expandir testes de dados onde o SQL for portavel.
- Criar runner MySQL/MariaDB dedicado para fluxos que dependem de `FOR UPDATE`,
  `NOW()`, `DATE_ADD` e concorrencia real.

---

## Epic RP-07 - Confiabilidade de Deploy

### RP-08 Alinhar migrations, documentacao e smoke de schema

**Prioridade:** P2

**Situacao apos reavaliacao:** parcialmente implementado. A documentacao e o
smoke foram atualizados para `016-018`, mas o schema consolidado `001` ainda nao
contem `grading_jobs.worker_id` nem o indice de inicio concorrente. Complementar
com RP-11.

**Problema original:** existia `database/migrations/016_user_avatar.sql`, mas
README e guia operacional paravam na migration 015.

**Objetivo:** garantir que deploys existentes recebam a coluna necessaria para
avatar e que divergencias sejam detectadas antes da liberacao.

**Escopo:**

- Manter migrations `016-018` na ordem oficial de atualizacao.
- Atualizar README e documentacao operacional.
- Adicionar `users.avatar_path` ao smoke de schema.
- Revisar mencoes a intervalos `002-014` e `002-015`.
- Definir checklist para confirmar migration aplicada em producao.

**Criterios de aceite:**

- Guia de deploy lista a migration 016 na ordem correta.
- README nao apresenta intervalo desatualizado.
- `smoke_schema.php` falha quando `users.avatar_path` estiver ausente.
- `smoke_schema.php` valida `grading_jobs.worker_id`.
- Instalacao limpa via `001_create_tables.sql` continua valida.

**Arquivos candidatos:**

- `docs/deploy_operacional.md`
- `README.md`
- `bin/smoke_schema.php`

---

## Epic RP-08 - Correcoes da Segunda Reavaliacao

### RP-09 Corrigir falso sucesso e auditoria duplicada no submit

**Prioridade:** P0

**Problema:** quando `AttemptSubmissionService` lanca uma excecao, a transacao e
revertida, mas o controller registra `student.attempt.submitted` e informa que a
tentativa foi enviada. Em repeticoes idempotentes, o controller tambem registra
novas auditorias de submissao.

**Objetivo:** comunicar e auditar apenas o estado realmente confirmado no banco.

**Escopo:**

- Diferenciar falha de submissao atomica de indisponibilidade posterior da fila.
- Nao registrar `student.attempt.submitted` quando houver rollback.
- Nao informar que a tentativa foi enviada se ela continuar `in_progress`.
- Fazer o servico retornar resultado estruturado, distinguindo nova submissao de
  repeticao idempotente.
- Registrar auditoria de submissao somente na primeira transicao bem-sucedida.
- Registrar falha tecnica com acao especifica, sem afirmar que houve envio.

**Criterios de aceite:**

- Falha em resposta, update ou enqueue mantem a tentativa `in_progress`.
- O aluno recebe mensagem de falha e pode tentar novamente.
- Nenhum evento `student.attempt.submitted` e gravado apos rollback.
- Submit idempotente nao duplica auditoria nem altera respostas.
- Testes simulam falha em cada etapa da transacao.

**Arquivos candidatos:**

- `app/Controllers/AttemptController.php`
- `app/Services/AttemptSubmissionService.php`
- `app/Services/AuditService.php`

### RP-10 Revalidar publicacao dentro das transacoes

**Prioridade:** P1

**Problema:** janela, matricula e publicacao sao verificadas pelo controller
antes da transacao. Um admin ou docente pode fechar ou alterar a publicacao
entre a validacao externa e o commit do inicio ou submit.

**Objetivo:** garantir que a decisao final use o estado bloqueado e atual da
publicacao.

**Escopo:**

- Revalidar publicacao, janela, turma ativa, matricula ativa e moderacao dentro
  de `AttemptStartService` e `AttemptSubmissionService`.
- Bloquear a linha de `exercise_turmas` relevante durante a operacao.
- Nao confiar em `maxAttempts` recebido previamente pelo controller.
- Definir mensagem especifica para prazo encerrado durante a transacao.

**Criterios de aceite:**

- Publicacao fechada antes do commit impede inicio e submit.
- Alteracao concorrente de `max_attempts` e respeitada.
- Aluno removido ou inativado na turma nao inicia nem envia tentativa.
- Exercicio bloqueado durante a operacao nao recebe novo submit.
- Teste MySQL cobre fechamento concorrente da publicacao.

**Arquivos candidatos:**

- `app/Services/AttemptStartService.php`
- `app/Services/AttemptSubmissionService.php`
- `app/Models/Exercise.php`

### RP-11 Manter schema consolidado compativel com o runtime

**Prioridade:** P0

**Problema:** a instalacao limpa aplica somente `001_create_tables.sql`, mas esse
schema nao possui `grading_jobs.worker_id` nem
`idx_attempts_student_exercise_turma`, exigidos pelo codigo e pela protecao de
concorrencia atuais.

**Objetivo:** garantir que uma instalacao limpa produza exatamente o schema
esperado pelo runtime atual.

**Escopo:**

- Adicionar `grading_jobs.worker_id` ao schema consolidado.
- Adicionar `idx_attempts_student_exercise_turma` ao schema consolidado.
- Revisar os scripts `000_reset_test_*` para manter equivalencia.
- Fazer o smoke validar tambem os indices criticos, nao apenas colunas.
- Criar validacao automatizada de instalacao limpa.

**Criterios de aceite:**

- Aplicar apenas `001_create_tables.sql` permite iniciar e processar jobs.
- Schema limpo passa integralmente no smoke.
- Worker nao falha por ausencia de `worker_id`.
- Protecao de inicio concorrente encontra o indice esperado.
- CI detecta divergencia futura entre schema consolidado e runtime.

**Arquivos candidatos:**

- `database/migrations/001_create_tables.sql`
- `database/migrations/000_reset_test_database_full_schema_hostgator.sql`
- `database/migrations/000_reset_test_tables_full_schema_hostgator.sql`
- `bin/smoke_schema.php`
- `.github/workflows/ci.yml`

### RP-12 Unificar reprocessamento manual e worker

**Prioridade:** P1

**Problema:** reprocessamento manual chama `AttemptGradingService` diretamente,
sem reivindicar o job. Um worker pode corrigir a mesma tentativa ao mesmo tempo.

**Objetivo:** garantir uma unica posse operacional para qualquer correcao.

**Escopo:**

- Fazer reprocessamento manual enfileirar ou reivindicar o job pelo mesmo
  protocolo usado pelo worker.
- Impedir chamada direta concorrente de `gradeSubmittedAttempt`.
- Preservar autoria administrativa na auditoria.
- Exibir ao admin/docente que a tentativa foi colocada em reprocessamento.

**Criterios de aceite:**

- Worker e reprocessamento manual nunca avaliam a mesma tentativa em paralelo.
- Acao manual nao sobrescreve job pertencente a worker ativo.
- Nao ocorrem chamadas duplicadas a IA.
- Auditoria distingue retry solicitado de correcao concluida.
- Teste cobre disputa entre retry manual e worker.

**Arquivos candidatos:**

- `app/Controllers/AttemptController.php`
- `app/Models/GradingJob.php`
- `app/Services/GradingJobProcessor.php`
- `app/Services/AttemptGradingService.php`

### RP-13 Endurecer transicoes, heartbeat e recuperacao de jobs

**Prioridade:** P1

**Problema:** `renewLease()` nao e chamado; `markCompleted()` nao exige status
`processing`; e um job interrompido na ultima tentativa pode ficar preso em
`processing`, pois a recuperacao exige `attempts < MAX_ATTEMPTS`.

**Objetivo:** tornar a maquina de estados da fila consistente e recuperavel.

**Escopo:**

- Renovar lease entre respostas e antes/depois de chamadas demoradas.
- Interromper processamento quando a renovacao falhar.
- Exigir `status = processing` e ownership correto para concluir ou falhar.
- Ao recuperar job esgotado, move-lo para `failed` terminal e limpar ownership.
- Limpar `worker_id` e `locked_at` em todas as transicoes de saida.
- Tornar timeout de lease configuravel.

**Criterios de aceite:**

- Worker ativo nao e recuperado como stale.
- Worker antigo nao conclui job recuperado ou reivindicado por outro.
- Job interrompido na ultima tentativa aparece como falha recuperavel pelo admin.
- Nenhum job permanece permanentemente `processing` apos expirar.
- Testes cobrem todas as transicoes e ownership.

**Arquivos candidatos:**

- `app/Models/GradingJob.php`
- `app/Services/GradingJobProcessor.php`
- `app/Services/AttemptGradingService.php`
- `config/app.php` ou configuracao dedicada

### RP-14 Substituir testes replicados por testes da implementacao real

**Prioridade:** P1

**Problema:** parte dos testes replica SQL e funcoes em helpers locais. Esses
testes podem passar mesmo quando o codigo de producao deixa de aplicar a regra.

**Objetivo:** testar os componentes reais e os cenarios de concorrencia que
sustentam as correcoes.

**Escopo:**

- Testar diretamente `AttemptStartService` e `AttemptSubmissionService`.
- Testar diretamente a protecao CSV real, sem copiar sua implementacao.
- Testar metodos reais de `GradingJob`, incluindo heartbeat e ownership.
- Adicionar MySQL/MariaDB descartavel no CI.
- Executar testes concorrentes com dois processos ou duas conexoes.
- Validar instalacao limpa pelo schema consolidado.

**Criterios de aceite:**

- Remover helpers de teste que duplicam a logica de producao.
- Uma regressao no metodo real faz o teste correspondente falhar.
- CI cobre transacoes, `FOR UPDATE`, gap locks e recovery de jobs.
- Testes continuam isolados do banco de producao.

**Arquivos candidatos:**

- `bin/run_tests.php`
- `bin/run_db_tests.php`
- novo runner de integracao MySQL
- `.github/workflows/ci.yml`

### RP-15 Restringir mudancas de role que violem o dominio

**Prioridade:** P2

**Problema:** o admin pode alterar livremente a role de usuarios que possuem
turmas, exercicios, matriculas ou tentativas, criando relacoes semanticamente
inconsistentes.

**Objetivo:** impedir transicoes de role incompativeis com dados existentes.

**Escopo:**

- Definir matriz de transicoes permitidas.
- Bloquear mudanca de teacher com turmas/exercicios para student.
- Bloquear mudanca de student com matriculas/tentativas para teacher/admin sem
  processo explicito.
- Exibir motivo e orientar a acao administrativa correta.
- Registrar tentativas bloqueadas na auditoria.

**Criterios de aceite:**

- Nenhuma mudanca de role deixa entidades com proprietario de papel invalido.
- Transicoes bloqueadas retornam mensagem clara.
- Transicoes permitidas continuam disponiveis.
- Testes cobrem usuarios com e sem dependencias de dominio.

**Arquivos candidatos:**

- `app/Controllers/AdminUserController.php`
- `app/Models/User.php`

---

## Definicao de Pronto Global

Um item deste backlog so pode ser considerado concluido quando:

- criterios de aceite estiverem atendidos;
- houver teste de regressao automatizado ou justificativa documentada;
- lint, smoke estatico e suites de teste passarem;
- migrations novas forem incrementais e documentadas;
- mensagens ao usuario forem claras e sem detalhes tecnicos;
- autorizacao por perfil, ownership e contexto de turma forem revalidadas;
- documentacao operacional afetada estiver atualizada.

## Sequenciamento Recomendado

### Entrega 1 - Bloqueadores de producao

1. RP-09 Corrigir falso sucesso e auditoria duplicada no submit.
2. RP-11 Manter schema consolidado compativel com o runtime.
3. Concluir RP-02 com os criterios ainda pendentes.

### Entrega 2 - Consistencia concorrente

4. RP-10 Revalidar publicacao dentro das transacoes.
5. Concluir RP-03 e validar concorrencia MySQL.
6. RP-12 Unificar reprocessamento manual e worker.
7. RP-13 Endurecer transicoes, heartbeat e recuperacao de jobs.

### Entrega 3 - Moderacao e dominio

8. RP-04 Aplicar bloqueio de moderacao por questao.
9. RP-15 Restringir mudancas de role.

### Entrega 4 - Qualidade e nao regressao

10. RP-14 Substituir testes replicados por testes reais.
11. Concluir RP-07 com suite de integracao e CI.
12. Concluir RP-08 e manter RP-01/RP-06 como regras de nao regressao.

## Validacao Final Recomendada

Antes de liberar o conjunto completo:

1. Executar lint em todos os arquivos PHP.
2. Executar `php bin/smoke_static.php`.
3. Executar `php bin/run_tests.php`.
4. Executar `php bin/run_db_tests.php`.
5. Executar a nova suite de integracao com MySQL/MariaDB.
6. Executar `php bin/smoke_schema.php` na base de homologacao.
7. Simular dois requests concorrentes de inicio e de submit.
8. Simular dois workers concorrentes e interrupcao de um deles.
9. Confirmar preservacao de tentativas e respostas apos tentativa de exclusao.
10. Simular falha em cada etapa do submit e confirmar que nao ha falso sucesso.
11. Validar instalacao limpa aplicando somente `001_create_tables.sql`.
12. Simular retry manual enquanto um worker possui o job.
