# Backlog de Prioridades da Revisao Profunda - 2026-06-10

Este backlog transforma os principais achados da revisao profunda do AlgoIA em
itens executaveis de engenharia. O foco e preservar dados academicos, garantir
consistencia das tentativas, endurecer a fila de correcao e ampliar a cobertura
automatizada dos fluxos criticos.

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

## Resumo Executivo

| ID | Item | Prioridade | Dependencias |
|---|---|---|---|
| RP-01 | Bloquear exclusao destrutiva de exercicios publicados | P0 | Nenhuma |
| RP-02 | Tornar o submit da tentativa atomico | P0 | RP-01 recomendado |
| RP-03 | Proteger inicio e limite de tentativas contra concorrencia | P0 | RP-02 |
| RP-04 | Aplicar bloqueio de moderacao por questao | P1 | Definicao de regra de produto |
| RP-05 | Implementar lease seguro e heartbeat no worker | P1 | Migration incremental |
| RP-06 | Neutralizar formulas nas exportacoes CSV | P1 | Nenhuma |
| RP-07 | Criar testes de integracao dos fluxos criticos | P1 | RP-01 a RP-06 |
| RP-08 | Alinhar migration 016, documentacao e smoke de schema | P2 | Nenhuma |

---

## Epic RP-01 - Preservacao do Historico Academico

### RP-01 Bloquear exclusao destrutiva de exercicios publicados

**Prioridade:** P0

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

### RP-08 Alinhar migration 016, documentacao e smoke de schema

**Prioridade:** P2

**Problema:** existe `database/migrations/016_user_avatar.sql`, mas README e guia
operacional param na migration 015. O smoke de schema tambem nao verifica
`users.avatar_path`.

**Objetivo:** garantir que deploys existentes recebam a coluna necessaria para
avatar e que divergencias sejam detectadas antes da liberacao.

**Escopo:**

- Incluir a migration 016 na ordem oficial de atualizacao.
- Atualizar README e documentacao operacional.
- Adicionar `users.avatar_path` ao smoke de schema.
- Revisar mencoes a intervalos `002-014` e `002-015`.
- Definir checklist para confirmar migration aplicada em producao.

**Criterios de aceite:**

- Guia de deploy lista a migration 016 na ordem correta.
- README nao apresenta intervalo desatualizado.
- `smoke_schema.php` falha quando `users.avatar_path` estiver ausente.
- Instalacao limpa via `001_create_tables.sql` continua valida.

**Arquivos candidatos:**

- `docs/deploy_operacional.md`
- `README.md`
- `bin/smoke_schema.php`

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

### Entrega 1 - Preservacao e consistencia

1. RP-01 Bloquear exclusao destrutiva.
2. RP-02 Tornar submit atomico.
3. RP-03 Proteger inicio e limite de tentativas.

### Entrega 2 - Moderacao e operacao

4. RP-04 Aplicar bloqueio de moderacao por questao.
5. RP-05 Implementar lease seguro e heartbeat.
6. RP-06 Neutralizar formulas em CSV.

### Entrega 3 - Qualidade e deploy

7. RP-07 Consolidar testes de integracao e CI.
8. RP-08 Alinhar migration 016, documentacao e smoke.

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
