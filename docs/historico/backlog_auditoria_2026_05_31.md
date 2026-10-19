# Backlog de Ajustes — Auditoria 2026-05-31

Este backlog formaliza os achados da auditoria de 2026-05-31 sobre o estado atual do código (branch `main`, commit `92379db`). Itens estão agrupados por epic, com prioridade, descrição técnica, critérios de aceite e referências de arquivo:linha.

## Premissas

- baseline: estado do repositório em 2026-05-31, working tree limpo
- P0: risco de produção, segurança, integridade de dados
- P1: performance, manutenibilidade média, resiliência
- P2: higiene, refactor, dívida técnica

## Sequenciamento recomendado

1. consistência de schema (E1)
2. correctness e segurança de sessão (E2)
3. performance de hot paths (E3)
4. resiliência do pipeline de correção (E4)
5. higiene de design e dívida técnica (E5)

---

## Epic E1 — Consistência de Schema e Migrations

### Objetivo

Eliminar divergência entre `001_create_tables.sql` e os scripts `000_reset_test_*`, garantindo que toda instalação produza FKs equivalentes.

### Prioridade

P0

### Histórias

#### E1-H1 Padronizar FK `exercises.turma_id` com ON DELETE SET NULL

Como mantenedor do sistema
Quero que a FK `fk_ex_turma` use `ON DELETE SET NULL` em toda migration
Para permitir exclusão de turmas sem erro 500.

Critérios de aceite:

- migration nova altera `fk_ex_turma` em `001_create_tables.sql:71` para `ON DELETE SET NULL`
- comportamento idêntico ao definido em `000_reset_test_database_full_schema_hostgator.sql:117-118`
- teste manual: excluir turma com exercício referenciado mantém o exercício com `turma_id = NULL`
- documentação operacional sinaliza que `exercises.turma_id` é coluna legada

#### E1-H2 Remover escrita em coluna legada `exercises.turma_id`

Como mantenedor do sistema
Quero parar de escrever `turma_id` em `exercises` durante `activate`
Para tratar `exercise_turmas` como fonte canônica única.

Critérios de aceite:

- `Exercise::activate` em [Exercise.php:546](app/Models/Exercise.php#L546) deixa de escrever `turma_id`
- `applyPublicationContext` em [Exercise.php:491](app/Models/Exercise.php#L491) preserva `turma_id` original do attempt
- leituras dependem apenas de `exercise_turmas`
- migration futura pode marcar coluna como deprecated sem afetar fluxo

---

## Epic E2 — Correctness e Segurança de Sessão

### Objetivo

Corrigir comparações incorretas, sanitização indevida de input e configuração de cookie que falha sob proxy reverso.

### Prioridade

P0

### Histórias

#### E2-H1 Substituir `hash_equals` em comparação de plaintext

Como auditor de código
Quero que comparação de senha temporária vs nova use operador `===`
Para usar a função apropriada ao tipo de dado.

Critérios de aceite:

- [AuthController.php:146](app/Controllers/AuthController.php#L146) troca `hash_equals($currentPassword, $password)` por `$currentPassword === $password`
- demais usos de `hash_equals` (CSRF em `Session.php:106`) permanecem inalterados
- comportamento de bloqueio de reuso de senha temporária mantém equivalência

#### E2-H2 Endurecer cookie de sessão atrás de proxy

Como operador
Quero que a flag `secure` do cookie respeite `X-Forwarded-Proto`
Para impedir tráfego de cookie sem TLS em ambiente com proxy reverso.

Critérios de aceite:

- [Session.php:20](core/Session.php#L20) detecta HTTPS via `$_SERVER['HTTPS']` ou `X-Forwarded-Proto = https`
- decisão é configurável via variável de ambiente para evitar spoofing em ambientes sem proxy confiável
- documentação de deploy registra cabeçalhos esperados do proxy

#### E2-H3 Corrigir sanitização de e-mail no `Request::email`

Como auditor de código
Quero que `Request::email` valide sem mutar input
Para evitar discrepância entre o que o usuário digitou e o que é persistido/validado.

Critérios de aceite:

- [Request.php:57](core/Request.php#L57) deixa de aplicar `FILTER_SANITIZE_EMAIL`
- retorna string crua trimada
- validação `FILTER_VALIDATE_EMAIL` permanece nos controllers
- caso de entrada inválida resulta em erro de formulário, não em string mutada silenciosamente

#### E2-H4 Endurecer CSP removendo `unsafe-inline` em script-src

Como mantenedor de segurança
Quero substituir `'unsafe-inline'` por nonce em scripts
Para reduzir superfície de XSS.

Critérios de aceite:

- [public/index.php:11](public/index.php#L11) gera nonce por request
- views inline recebem `nonce="..."` em `<script>`
- diretiva `script-src` referencia o nonce no lugar de `'unsafe-inline'`
- aplicação continua funcional em navegação completa: login, criação de exercício, submit, dashboard

#### E2-H5 Retornar Content-Type JSON em todos os caminhos de `saveAnswer`

Como cliente JavaScript do aluno
Quero que toda resposta JSON do endpoint declare `Content-Type: application/json`
Para evitar parsing inconsistente.

Critérios de aceite:

- branches em [AttemptController.php:92-95](app/Controllers/AttemptController.php#L92-L95) emitem header JSON antes do `exit`
- comportamento de erro mantém status HTTP correto
- consumidor JS não precisa de fallback de detecção

---

## Epic E3 — Performance de Hot Paths

### Objetivo

Remover consultas desnecessárias em rotas autenticadas e no dashboard de admin.

### Prioridade

P1

### Histórias

#### E3-H1 Substituir contagem por papel via SQL no dashboard admin

Como admin
Quero que o dashboard calcule total por papel via `GROUP BY`
Para evitar carregar a tabela `users` inteira a cada acesso.

Critérios de aceite:

- [AdminController.php:45](app/Controllers/AdminController.php#L45) não carrega `getAllForAdmin()` apenas para contar
- contagem por papel vem de `countForAdmin()` parametrizado por role ou de SELECT agregado
- totais batem com queries diretas do banco
- latência do dashboard cai mensuravelmente com volume realista

#### E3-H2 Reduzir consulta DB em `Auth::refreshSessionUser`

Como operador
Quero limitar a query de refresh de sessão a um intervalo configurável
Para reduzir carga de DB em páginas com assets servidos pelo backend.

Critérios de aceite:

- [Auth.php:102-130](core/Auth.php#L102-L130) executa `find` no máximo a cada N segundos por sessão
- intervalo é configurável (default 60s)
- comportamento de logout em conta desativada continua imediato no próximo refresh
- métrica de queries por request cai em fluxo médio do aluno

#### E3-H3 Filtrar `Attempt::getInProgress` estritamente por `turma_id`

Como mantenedor
Quero remover o fallback `turma_id IS NULL` no lookup de attempt em andamento
Para não cruzar contexto entre turmas.

Critérios de aceite:

- [Attempt.php:39](app/Models/Attempt.php#L39) usa `AND turma_id = ?` quando turma fornecida
- attempt sem `turma_id` (legado) só é retornado quando turma também é null
- regressão verificada: aluno em duas turmas com publicações distintas não recupera attempt da turma errada

---

## Epic E4 — Resiliência do Pipeline de Correção

### Objetivo

Permitir recuperação operacional de jobs travados e proteger nota já entregue.

### Prioridade

P1

### Histórias

#### E4-H1 Endpoint admin para retry de `grading_jobs` esgotados

Como admin
Quero forçar retry de job que excedeu `MAX_ATTEMPTS`
Para reprocessar tentativas sem ação manual em banco.

Critérios de aceite:

- novo endpoint `POST /admin/grading-jobs/{id}/retry` zera `attempts` e move status para `queued`
- ação registra evento de auditoria com motivo
- listagem de falhas no dashboard expõe botão para retry
- usuário não-admin recebe 403

#### E4-H2 Proteger `Attempt::markGraded` contra sobrescrita silenciosa

Como mantenedor
Quero que `markGraded` só atualize attempt em status `submitted`
Para evitar regravar nota já entregue sem trilha.

Critérios de aceite:

- [Attempt.php:29](app/Models/Attempt.php#L29) inclui `AND status = 'submitted'` no UPDATE
- tentativa de regradeAdmin/regradeTeacher passa por caminho explícito que reabre status quando necessário
- log registra divergência se UPDATE retornar 0 linhas

#### E4-H3 Política operacional para `injection_logs`

Como admin acadêmico
Quero conteúdo de tentativas suspeitas armazenado de forma controlada
Para revisar incidentes sem violar privacidade.

Critérios de aceite:

- [OpenAIService.php:170-176](app/Services/OpenAIService.php#L170-L176) grava trecho da resposta com truncamento explícito
- coluna recebe valor cifrado ou redigido por política documentada
- política de retenção definida em `docs/deploy_operacional.md`

---

## Epic E5 — Higiene de Design e Dívida Técnica

### Objetivo

Reduzir código morto, docblocks enganosos e rotas confusas; quebrar god controllers.

### Prioridade

P2

### Histórias

#### E5-H1 Remover rota `/teacher/students/{id}/delete`

Como mantenedor
Quero eliminar a rota `delete` que apenas desvincula
Para que URL reflita ação real.

Critérios de aceite:

- [routes/web.php:103](routes/web.php#L103) removida
- views/templates referenciam apenas `/detach`
- regressão verificada na lista de alunos do docente

#### E5-H2 Remover `DELETE FROM attempts` redundante em `Exercise::delete`

Como mantenedor
Quero remover query redundante que duplica o efeito de FK CASCADE
Para reduzir trabalho de DB e indicar código desatualizado.

Critérios de aceite:

- [Exercise.php:23](app/Models/Exercise.php#L23) remove DELETE explícito de attempts
- transação mantém integridade via cascata da FK
- teste manual: excluir exercise remove attempts associados sem orfanizar respostas

#### E5-H3 Limpar docblocks `@method` falsos em `Turma`

Como leitor de IDE
Quero docblocks coerentes com a implementação
Para evitar confusão sobre métodos mágicos vs reais.

Critérios de aceite:

- [Turma.php:7-10](app/Models/Turma.php#L7-L10) remove anotações `@method` para métodos implementados
- IDE deixa de marcar métodos como mágicos
- nenhum comportamento em runtime altera

#### E5-H4 Detectar transações aninhadas em `Database`

Como mantenedor
Quero que `beginTransaction` em transação ativa lance erro controlado
Para evitar bug silencioso de tx aninhada.

Critérios de aceite:

- [core/Database.php](core/Database.php) verifica `inTransaction()` antes de iniciar
- erro indica claramente que tx aninhada não é suportada
- chamadores que dependem disso passam a usar savepoints ou compor a tx no chamador externo

#### E5-H5 Quebrar `AdminController` em controllers menores

Como mantenedor
Quero dividir o controller administrativo de 2188 linhas em módulos por área
Para reduzir o custo de localizar e revisar ações administrativas.

Critérios de aceite:

- separação por área: usuários, turmas, exercícios, audit, presets, teacher requests
- rotas continuam funcionais sem mudança de path
- arquivos resultantes ficam abaixo de 500 linhas cada
- nenhuma regressão funcional verificada em smoke admin

---

## Resumo por prioridade

| Epic | História | Prioridade | Referência principal |
|------|----------|------------|----------------------|
| E1 | H1 FK turma_id SET NULL | P0 | `database/migrations/001_create_tables.sql:71` |
| E1 | H2 Remover escrita legada `turma_id` | P0 | `app/Models/Exercise.php:546` |
| E2 | H1 `hash_equals` → `===` | P0 | `app/Controllers/AuthController.php:146` |
| E2 | H2 Cookie secure sob proxy | P0 | `core/Session.php:20` |
| E2 | H3 `Request::email` sem sanitize | P0 | `core/Request.php:57` |
| E2 | H4 CSP sem `unsafe-inline` | P1 | `public/index.php:11` |
| E2 | H5 Content-Type JSON em saveAnswer | P1 | `app/Controllers/AttemptController.php:92` |
| E3 | H1 Dashboard contagem via SQL | P1 | `app/Controllers/AdminController.php:45` |
| E3 | H2 Cache curto em refreshSessionUser | P1 | `core/Auth.php:102` |
| E3 | H3 Filtro estrito em `getInProgress` | P1 | `app/Models/Attempt.php:39` |
| E4 | H1 Retry admin para jobs esgotados | P1 | `app/Models/GradingJob.php:251` |
| E4 | H2 markGraded só em submitted | P1 | `app/Models/Attempt.php:29` |
| E4 | H3 Política injection_logs | P2 | `app/Services/OpenAIService.php:170` |
| E5 | H1 Remover rota /delete falsa | P2 | `routes/web.php:103` |
| E5 | H2 Remover DELETE redundante | P2 | `app/Models/Exercise.php:23` |
| E5 | H3 Limpar `@method` em Turma | P2 | `app/Models/Turma.php:7` |
| E5 | H4 Detectar tx aninhada | P2 | `core/Database.php` |
| E5 | H5 Quebrar AdminController | P2 | `app/Controllers/AdminController.php` |
