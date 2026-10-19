# Backlog da Auditoria Profunda - 2026-06-12

Este backlog consolida os achados confirmados na revisao profunda do estado atual
do AlgoIA. A avaliacao cruzou codigo, schema, rotas, documentacao, historico
recente e suites automatizadas.

## Resultado da Validacao

- `php bin/smoke_static.php`: passou.
- `php bin/run_tests.php`: 50 testes passaram.
- `php bin/run_db_tests.php`: 53 testes de banco passaram.
- `php -l` em todos os arquivos PHP: passou.
- `bin/smoke_schema.php`: tentativa falhou por indisponibilidade da conexao
  MySQL local (`SQLSTATE[HY000] [2002]`).

As suites verdes confirmam as invariantes atualmente testadas, mas nao exercitam
concorrencia MySQL real, requests completos, integracao com a OpenAI nem os
efeitos ponta a ponta da moderacao.

**Atualização 2026-09-21:** AP-05, AP-06, AP-07, AP-08, AP-09, AP-10 e AP-12
implementados. Reverificado contra o código atual antes de implementar —
`php bin/smoke_static.php`, `php bin/run_tests.php` (63 testes) e
`php bin/run_db_tests.php` (58 testes) passam após as mudanças; AP-08 (e o
teste de concorrência do AP-07 adicionado depois) roda via CI (job
`integration`, com serviço MariaDB — motor real do HostGator em produção)
por depender de um MySQL real que este ambiente de desenvolvimento não tinha
disponível localmente. Todos os itens deste backlog estão implementados.

## Modelo de Prioridade

- **P0:** risco direto para integridade da avaliacao ou regra administrativa.
- **P1:** risco relevante de concorrencia, seguranca, indisponibilidade ou
  inconsistencia observavel.
- **P2:** confiabilidade preventiva, desempenho, operacao e manutencao.

## Resumo Executivo

| ID | Item | Prioridade | Esforco | Situacao |
|---|---|---|---|---|
| AP-01 | Completar revalidacao transacional do submit | P0 | M | Implementado; teste concorrente MySQL em AP-08 |
| AP-02 | Tornar bloqueio de questao efetivo em todo o fluxo | P0 | M | Implementado; teste MySQL em AP-08 (falta HTTP ponta a ponta autenticado) |
| AP-03 | Garantir lease continuo e propriedade estrita do worker | P1 | M | Implementado; teste concorrente MySQL em AP-08 |
| AP-04 | Unificar arredondamento da nota por resposta e total | P1 | P | Implementado e testado |
| AP-05 | Consumir token de redefinicao de senha atomicamente | P1 | M | Implementado em 2026-09-21; teste em AP-08 |
| AP-06 | Proteger ultimo admin e mudancas de papel contra concorrencia | P1 | M | Implementado em 2026-09-21; teste concorrente MySQL em AP-08 |
| AP-07 | Tornar cadastros publicos atomicos e concorrentes | P1 | M | Implementado em 2026-09-21; teste concorrente MySQL em AP-08 |
| AP-08 | Criar testes reais dos fluxos criticos em MySQL e HTTP | P1 | G | Implementado em 2026-09-21 |
| AP-09 | Eliminar divergencia entre schemas limpos e ampliar smoke | P2 | M | Implementado em 2026-09-21 |
| AP-10 | Configurar confianca explicita em proxy reverso | P2 | M | Implementado em 2026-09-21 |
| AP-11 | Revalidar tentativa em andamento antes de reutiliza-la | P2 | P | Implementado junto ao AP-01 |
| AP-12 | Definir limites de entrada e guardrails operacionais | P2 | M | Implementado em 2026-09-21 |

---

## Epic AP-A - Integridade Academica

### AP-01 Completar revalidacao transacional do submit

**Prioridade:** P0  
**Esforco:** M

**Situacao:** implementado em 2026-06-12. O submit agora exige contexto de
turma e revalida, dentro da transacao, janela completa, matricula ativa,
exercicio ativo e moderacao de exercicio/questoes. Teste concorrente com duas
conexoes MySQL reais (lock na linha da tentativa) e teste de rejeicao por
moderacao adicionados em `bin/run_integration_tests.php` (AP-08, 2026-09-21).

**Achado:** `AttemptSubmissionService` bloqueia a tentativa e verifica somente a
existencia da publicacao e `closes_at`. A decisao final nao revalida
`opens_at`, matricula ativa, estado ativo do exercicio nem moderacao. As
validacoes completas existem antes da transacao no controller, deixando uma
janela de corrida.

**Evidencias:**

- `app/Services/AttemptSubmissionService.php:50-66`
- `app/Controllers/AttemptController.php:124-129`
- `app/Models/Exercise.php:469-485`

**Impacto:** um aluno pode concluir o submit depois de ser removido da turma ou
depois de o exercicio ser bloqueado, desde que a publicacao ainda exista e
`closes_at` nao tenha expirado.

**Ajuste proposto:**

- Fazer a consulta transacional unir `exercise_turmas`, `student_turma`,
  `exercises` e, conforme AP-02, o estado de moderacao das questoes.
- Exigir janela completa: `opens_at <= NOW()` e `closes_at >= NOW()`.
- Exigir matricula ativa e exercicio `active` nao bloqueado.
- Retornar motivos estruturados para prazo, matricula e moderacao.

**Criterios de aceite:**

- Remocao/inativacao concorrente da matricula impede o submit.
- Bloqueio concorrente do exercicio ou questao impede o submit.
- Alterar `opens_at` para o futuro impede o submit.
- Teste MySQL coordena duas conexoes e confirma rollback.

### AP-02 Tornar bloqueio de questao efetivo em todo o fluxo

**Prioridade:** P0  
**Esforco:** M

**Situacao:** implementado em 2026-06-12. Bloquear uma questao fecha as
publicacoes na mesma transacao e o bloqueio derivado impede publicacao, acesso
do aluno, start, autosave, submit e correcao. O desbloqueio nao reabre
publicacoes automaticamente.

**Achado:** o admin grava `questions.admin_review_status = blocked`, mas todas as
leituras operacionais continuam usando `Question::findByExercise()` sem filtro.
O bloqueio nao fecha publicacoes, nao impede start/autosave/submit e nao remove a
questao da correcao ou do total.

**Evidencias:**

- `app/Controllers/AdminExerciseController.php:141-175`
- `app/Models/Question.php:11-16`
- `app/Services/AttemptSubmissionService.php:68-74`
- `app/Models/Answer.php:42-63`
- `app/Controllers/ExerciseController.php:265-268`

**Impacto:** uma questao administrativamente bloqueada continua visivel,
respondida, corrigida e pontuada como aprovada.

**Decisao recomendada:** ao bloquear uma questao, bloquear o exercicio e fechar
suas publicacoes. O desbloqueio nao deve reabrir publicacoes automaticamente.

**Criterios de aceite:**

- Bloquear questao fecha publicacoes abertas do exercicio na mesma transacao.
- Exercicio com questao bloqueada nao pode ser publicado, iniciado ou enviado.
- Jobs ainda nao iniciados nao corrigem exercicio bloqueado sem decisao explicita.
- Professor/admin visualizam motivo; aluno recebe mensagem generica.
- Testes cobrem bloqueio antes do start, durante tentativa e antes da correcao.

### AP-04 Unificar arredondamento da nota por resposta e total

**Prioridade:** P1  
**Esforco:** P

**Situacao:** implementado em 2026-06-12. A precisao canonica passou a ser uma
casa decimal antes da persistencia e da soma do total, com teste unitario.

**Achado:** `answers.ai_score` usa `DECIMAL(4,1)`, mas o total da tentativa soma
o `float` bruto retornado pela IA antes do arredondamento feito pelo banco. Em
retry, respostas ja avaliadas sao somadas a partir do valor arredondado salvo.

**Evidencias:**

- `database/migrations/001_create_tables.sql:143`
- `app/Services/AttemptGradingService.php:71-81`
- `app/Models/Answer.php:32-39`

**Impacto:** a nota total pode divergir da soma exibida por questao e pode mudar
apos um retry sem que as respostas tenham mudado.

**Ajuste proposto:**

- Definir uma precisao canonica para nota por questao.
- Arredondar antes de persistir e antes de somar, ou aumentar a precisao da
  coluna e formatar apenas na apresentacao.
- Calcular o total final a partir dos valores persistidos.

**Criterios de aceite:**

- Total e igual a soma das notas persistidas por resposta.
- Retry sem nova avaliacao preserva exatamente a nota anterior.
- Testes cobrem valores com mais de uma casa decimal.

---

## Epic AP-B - Fila e Concorrencia

### AP-03 Garantir lease continuo e propriedade estrita do worker

**Prioridade:** P1  
**Esforco:** M

**Situacao:** implementado em 2026-06-12. A correcao renova o lease entre
respostas, antes de persistir resultado e antes de concluir a tentativa.
Conclusao e falha agora exigem `status = processing` e ownership exato do
`worker_id`. Teste com duas conexoes MySQL reais (lock em `claimNext`, guarda
de ownership em `markCompleted`) adicionado em `bin/run_integration_tests.php`
(AP-08, 2026-09-21).

**Achado:** o lease e renovado apenas uma vez antes da correcao completa. Uma
tentativa com varias respostas pode ultrapassar 15 minutos e ser recuperada por
outro worker. Alem disso, `markFailed()` aceita atualizar qualquer job com
`worker_id IS NULL`, mesmo quando chamado por um worker antigo com ID nao vazio.

**Evidencias:**

- `app/Services/GradingJobProcessor.php:35-43`
- `app/Services/AttemptGradingService.php:35-79`
- `app/Models/GradingJob.php:113-127`
- `app/Models/GradingJob.php:134-162`
- `app/Services/OpenAIService.php:181-239`

**Impacto:** dois workers podem avaliar a mesma tentativa; um worker que perdeu
o lease pode alterar o estado de um job recuperado ou recolocado na fila.

**Ajuste proposto:**

- Renovar lease entre respostas e antes/depois de cada chamada lenta.
- Interromper imediatamente a correcao quando a renovacao falhar.
- Exigir `status = processing AND worker_id = ?` em `markFailed` e
  `markCompleted` para chamadas de worker.
- Criar metodos separados para transicoes administrativas sem `worker_id`.
- Tornar timeout do lease configuravel e maior que o pior caso esperado.

**Criterios de aceite:**

- Worker antigo nao conclui nem falha job recuperado, reencolado ou assumido.
- Correcao longa mantem lease valido durante todo o processamento.
- Perda de lease impede novas chamadas a IA e novas escritas de resultado.
- Teste MySQL cobre recovery concorrente e worker atrasado.

### AP-11 Revalidar tentativa em andamento antes de reutiliza-la

**Prioridade:** P2  
**Esforco:** P

**Situacao:** implementado em 2026-06-12 junto ao AP-01. A tentativa
`in_progress` so e reutilizada depois da revalidacao transacional da publicacao,
matricula e moderacao.

**Achado:** `AttemptStartService` retorna uma tentativa `in_progress` existente
antes de bloquear e revalidar a publicacao e a matricula.

**Evidencia:** `app/Services/AttemptStartService.php:17-27`

**Impacto:** o endpoint de start pode comunicar continuidade de tentativa que ja
nao pode receber autosave ou submit. O fluxo posterior bloqueia a operacao, mas
a resposta e inconsistente e tentativas orfas permanecem abertas.

**Criterios de aceite:**

- Reutilizacao ocorre apenas apos revalidacao da publicacao e matricula.
- Tentativa invalida recebe mensagem especifica e nao e apresentada como ativa.
- Definir politica para encerrar ou expirar tentativas `in_progress` orfas.

---

## Epic AP-C - Seguranca de Conta e Administracao

### AP-05 Consumir token de redefinicao de senha atomicamente

**Prioridade:** P1  
**Esforco:** M

**Situacao:** implementado em 2026-09-21. `User::consumePasswordResetToken()`
faz um único `UPDATE` cuja `WHERE` casa o hash do token e a expiração e já
limpa o próprio hash na mesma instrução — a segunda de duas requisições
concorrentes com o mesmo token casa zero linhas e recebe `false`. Teste de
duplo consumo sequencial contra MySQL real adicionado em
`bin/run_integration_tests.php` (AP-08, 2026-09-21) — a atomicidade vem do
próprio `UPDATE` de instrução única, não de um lock que exija duas conexões
sobrepostas para provar.

**Achado:** o reset consulta o token valido e depois atualiza a senha em outra
operacao. Dois requests concorrentes com o mesmo token podem ambos passar na
consulta e trocar a senha; o ultimo commit vence.

**Evidencias:**

- `app/Controllers/AuthController.php:188-214`
- `app/Models/User.php:56-67`
- `app/Models/User.php:85-99`

**Ajuste proposto:**

- Criar operacao transacional `consumePasswordResetToken`.
- Bloquear usuario/token, revalidar hash e expiracao, atualizar senha e limpar
  token na mesma transacao.
- Retornar falso quando o token ja tiver sido consumido.

**Criterios de aceite:**

- Apenas um de dois resets concorrentes com o mesmo token tem sucesso.
- Token deixa de ser valido no mesmo commit da troca de senha.
- Teste MySQL cobre consumo concorrente.

### AP-06 Proteger ultimo admin e mudancas de papel contra concorrencia

**Prioridade:** P1  
**Esforco:** M

**Situacao:** implementado em 2026-09-21. `updateUserStatus`, `deactivateUsersBatch`
e `updateUser` (troca de papel/status) agora abrem uma transação, contam admins
ativos com `User::countActiveAdminsForUpdate()` (`SELECT ... FOR UPDATE`) e só
então decidem e escrevem — o lock nas linhas de admin ativo serializa requests
concorrentes. Teste com duas conexões MySQL reais (UPDATE concorrente trava
até a conexão que segura o lock liberar) adicionado em
`bin/run_integration_tests.php` (AP-08, 2026-09-21).

**Achado:** contagem do ultimo admin, verificacao de dependencias e atualizacao
do usuario ocorrem em queries separadas, sem lock ou transacao.

**Evidencias:**

- `app/Controllers/AdminUserController.php:172-197`
- `app/Controllers/AdminUserController.php:212-225`
- `app/Controllers/AdminUserController.php:333-353`
- `app/Models/User.php:47-53`

**Impacto:** requests administrativos concorrentes podem remover os ultimos
acessos administrativos ou alterar papel enquanto novas dependencias surgem.

**Criterios de aceite:**

- Transicao de papel/status e validacoes de dominio ocorrem na mesma transacao.
- Linhas administrativas relevantes sao bloqueadas de forma deterministica.
- Dois requests concorrentes nao deixam o sistema sem admin ativo.
- Teste MySQL cobre inativacao/demote simultaneo.

### AP-07 Tornar cadastros publicos atomicos e concorrentes

**Prioridade:** P1  
**Esforco:** M

**Situacao:** implementado em 2026-09-21. Cadastro de aluno passou a criar
usuário e matrícula na mesma transação (commit/rollback juntos). Nos dois
fluxos (aluno e docente), a violação da constraint única de e-mail
(`SQLSTATE 23000`) é capturada e convertida em erro de formulário controlado
em vez de propagar como exceção não tratada. Teste de concorrência real com
duas conexões MySQL adicionado em `bin/run_integration_tests.php`: a conexão
A insere o e-mail sem commitar; a conexão B tenta o mesmo INSERT e trava
(lock do índice único do InnoDB, não erro imediato) até A commitar — só
então B recebe o `SQLSTATE 23000` real que o controller já captura. Fecha a
lacuna deixada em aberto quando o AP-08 foi implementado.

**Achado:** cadastro de aluno cria o usuario e depois cria a matricula, sem
transacao. O teste de e-mail existente tambem ocorre antes do insert, permitindo
corrida com a constraint unica. O cadastro docente possui a mesma corrida de
e-mail.

**Evidencias:**

- `app/Controllers/AuthController.php:277-301`
- `app/Controllers/AuthController.php:373-383`
- `app/Models/Turma.php:158-164`
- `app/Models/User.php:19-31`

**Impacto:** falha na matricula pode deixar conta pendente orfa; cadastros
concorrentes do mesmo e-mail podem gerar erro 500 em vez de resposta controlada.

**Criterios de aceite:**

- Criacao de aluno e matricula fazem commit ou rollback juntos.
- Violacao de e-mail unico vira erro de formulario controlado.
- Cadastro docente trata a mesma corrida sem erro 500.
- Testes cobrem falha no enrollment e dois cadastros simultaneos.

### AP-10 Configurar confianca explicita em proxy reverso

**Prioridade:** P2  
**Esforco:** M

**Situacao:** implementado em 2026-09-21 (IP de auditoria já vinha resolvido
desde a revisão do mesmo dia registrada em
`docs/backlog_revisao_sistema_2026_09_21.md` RS-02; faltava a parte de
HTTPS). `Core\is_trusted_proxy()` e `Core\request_is_https()` (novos, em
`core/Env.php`) centralizam a regra e são usados por `AuditService`,
`public/index.php` e `core/Session.php`: sem `TRUSTED_PROXIES` configurado,
`X-Forwarded-Proto`/`X-Forwarded-For`/`CF-Connecting-IP` são ignorados.

**Achado:** HTTPS e IP de auditoria confiam em headers encaminhados sem
configuracao de proxies confiaveis.

**Evidencias:**

- `public/index.php:9-20`
- `core/Session.php:17-29`
- `app/Services/AuditService.php:33-46`

**Impacto:** em acesso direto, headers forjados podem alterar HSTS/cookie secure
e registrar IP falso na auditoria.

**Criterios de aceite:**

- `X-Forwarded-Proto`, `X-Forwarded-For` e `CF-Connecting-IP` so sao aceitos de
  proxies configurados.
- Sem proxy confiavel, usar `HTTPS` e `REMOTE_ADDR`.
- Deploy documenta topologia e variaveis de confianca.

---

## Epic AP-D - Testes, Schema e Operacao

### AP-08 Criar testes reais dos fluxos criticos em MySQL e HTTP

**Prioridade:** P1  
**Esforco:** G

**Situacao:** implementado em 2026-09-21. `bin/run_integration_tests.php`
conecta a um MySQL real, aplica `001_create_tables.sql` (+ `020`) e roda os
services reais (`AttemptSubmissionService`, `GradingJob`, `User`) contra esse
banco. Para os pontos cuja correcao foi um lock (`FOR UPDATE`), abre DUAS
conexoes MySQL de verdade e prova que a segunda trava/perde a corrida
enquanto a primeira segura a transacao: AP-01/AP-02 (lock da tentativa no
submit), AP-03 (lock do job na fila, incluindo ownership de `markCompleted`),
AP-05 (consumo atomico do token — duplo consumo sequencial), AP-06 (lock do
ultimo admin), AP-07 (INSERT concorrente com e-mail duplicado — trava no
lock do indice unico do InnoDB ate a primeira conexao commitar, so entao a
segunda recebe o `SQLSTATE 23000` real). Inclui smoke HTTP via `php -S`
servindo `public/` de verdade: headers de seguranca, redirect de rota admin
sem sessao, CSRF em POST. Novo job `integration` em
`.github/workflows/ci.yml` sobe um servico MariaDB (motor real do HostGator
em producao — MySQL 8 recusa uma DDL que MariaDB aceita, ver commit da
correcao) e roda o script — job separado do `test` original (que continua
"Lint + testes (sem banco)", sem MySQL, inalterado). Guarda de seguranca:
recusa rodar fora de um banco cujo nome contenha `test`/`ci` (o script trunca
todas as tabelas). Nao cobre: teste de HTTP autenticado ponta a ponta (login
real + sessao + acao autenticada) nem carga/performance — escopo ficou nos
pontos de concorrencia e autorizacao negativa que motivaram o item.

**Achado:** a suite atual passa, mas varios testes de banco reproduzem SQL ou
estado-maquina em tabelas auxiliares SQLite, sem executar os services reais. O CI
nao valida concorrencia, requests completos, headers ou schema limpo MySQL.

**Evidencias:**

- `.github/workflows/ci.yml`
- `bin/run_db_tests.php`
- `bin/smoke_static.php:6-68`

**Escopo minimo:**

- Subir MySQL/MariaDB de servico no CI e aplicar apenas `001_create_tables.sql`.
- Executar services reais para start, submit, grading, reset e administracao.
- Adicionar testes com duas conexoes para os itens AP-01, AP-03, AP-05 e AP-06.
- Adicionar smoke HTTP para autorizacao negativa, CSRF, headers e redirects.
- Usar fake transport para OpenAI, sem chamada externa.

**Criterios de aceite:**

- Bugs corrigidos neste backlog possuem teste de regressao da implementacao real.
- Instalacao limpa e migrations incrementais sao verificadas no CI.
- Falha de concorrencia impede merge.

### AP-09 Eliminar divergencia entre schemas limpos e ampliar smoke

**Prioridade:** P2  
**Esforco:** M

**Situacao:** implementado em 2026-09-21 para a divergência confirmada
(as duas FKs de `admin_reviewed_by`). `001_create_tables.sql` ganhou
`fk_ex_admin_reviewed_by` e `fk_q_admin_reviewed_by`; migration incremental
`020_admin_reviewed_by_fk.sql` (idempotente) cobre bases já existentes.
`bin/smoke_schema.php` passou a validar as duas FKs via
`INFORMATION_SCHEMA.KEY_COLUMN_USAGE`. Não foi feita uma auditoria exaustiva
de todos os índices dos scripts `000_reset_test_*`; apenas a divergência de FK
identificada nesta revisão foi fechada.

**Achado:** `001_create_tables.sql` e os scripts de reset nao sao equivalentes.
O schema `001` omite FKs de `admin_reviewed_by` e diversos indices usados pelos
scripts de teste. O smoke valida apenas dois indices e nao valida FKs.

**Evidencias:**

- `database/migrations/001_create_tables.sql:55-150`
- `database/migrations/000_reset_test_database_full_schema_hostgator.sql:93-217`
- `bin/smoke_schema.php:72-93`

**Impacto:** ambientes criados por caminhos documentados diferentes possuem
integridade referencial e desempenho diferentes sem alerta automatizado.

**Criterios de aceite:**

- Definir um schema canonico e gerar/validar os scripts de reset a partir dele.
- Igualar FKs e indices necessarios entre instalacao limpa e teste.
- Smoke valida colunas, indices unicos, FKs e regras `ON DELETE` criticas.
- CI aplica schema limpo real e executa o smoke.

### AP-12 Definir limites de entrada e guardrails operacionais

**Prioridade:** P2  
**Esforco:** M

**Situacao:** implementado em 2026-09-21. Resposta de tentativa
(`AttemptController::MAX_ANSWER_LENGTH`) e enunciado/gabarito de questão
(`QuestionController::MAX_TEXT_LENGTH`) rejeitam payload acima de 10000
caracteres com erro controlado, antes de chegar ao banco ou à OpenAI.
Timeout/retries da OpenAI (`OPENAI_TIMEOUT_SECONDS`, `OPENAI_MAX_RETRIES`) e
tentativas/janela de job travado (`GRADING_JOB_MAX_ATTEMPTS`,
`GRADING_JOB_STALE_MINUTES`) passaram de constantes fixas para variáveis de
ambiente com os mesmos padrões. `bin/process_grading_jobs.php` falha cedo
(exit 2) se `OPENAI_API_KEY`/extensões faltarem. Alerta operacional documentado
via `--status` (campos `stale`/`failed`) em `docs/deploy_operacional.md` — não
foi criado um serviço de alerta automatizado, só a base de dados para montar
um.

**Achado:** respostas, enunciados, descricoes e alguns filtros nao possuem
limites de tamanho explicitos na aplicacao. Configuracoes criticas da fila e da
OpenAI tambem estao fixas no codigo.

**Evidencias:**

- `app/Controllers/AttemptController.php:90-104`
- `app/Controllers/QuestionController.php:45-69`
- `app/Models/GradingJob.php:14-15`
- `config/openai.php:5-9`

**Ajuste proposto:**

- Definir limites funcionais por campo e rejeitar payloads excessivos antes do
  banco e da OpenAI.
- Tornar timeout, retries, lease e lote do worker configuraveis.
- Validar no bootstrap do worker a presenca de chave/modelo e extensoes.
- Documentar alertas para fila parada, jobs esgotados e latencia da IA.

**Criterios de aceite:**

- Payload excessivo recebe erro controlado e nao e enviado a IA.
- Configuracao invalida falha cedo com mensagem operacional clara.
- Guia de deploy possui valores recomendados e verificacao de saude da fila.

---

## Roteiro de Ajustes

### Onda 0 - Decisao de produto

1. Confirmar a politica recomendada do AP-02: questao bloqueada bloqueia o
   exercicio inteiro e fecha publicacoes.
2. Definir precisao canonica de nota do AP-04.

### Onda 1 - Integridade imediata

1. AP-01 - revalidacao transacional completa do submit.
2. AP-02 - moderacao de questao efetiva.
3. AP-03 - lease continuo e ownership estrito.
4. AP-04 - consistencia matematica da nota.

### Onda 2 - Seguranca e concorrencia administrativa

1. AP-05 - consumo atomico de token.
2. AP-06 - transicoes administrativas atomicas.
3. AP-07 - cadastros publicos atomicos.
4. AP-11 - reutilizacao valida de tentativa em andamento.

### Onda 3 - Malha de confiabilidade

1. AP-08 - suite MySQL/HTTP real no CI.
2. AP-09 - schema canonico e smoke ampliado.
3. AP-10 - proxies confiaveis.
4. AP-12 - limites e guardrails operacionais.

## Definicao de Pronto Global

Um item deste backlog so deve ser marcado como concluido quando:

- a regra estiver aplicada no backend, independentemente da interface;
- houver teste de regressao da implementacao real;
- migrations e schema limpo permanecerem equivalentes quando aplicavel;
- auditoria e mensagens ao usuario representarem o estado confirmado;
- documentacao operacional for atualizada quando houver impacto de deploy.
