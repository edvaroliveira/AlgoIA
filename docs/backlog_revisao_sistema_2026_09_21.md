# Backlog — Revisão de Sistema 2026-09-21

Revisão completa do AlgoIA (core, app, views, bin, infra) a partir do estado da
branch `docs/backlog-a3-avaliacao-composer` (base `4cab40f`).

**Escopo da revisão:** bootstrap e CSP, mini-framework `core/`, autorização por
middleware, todos os controllers, models e services, views, worker de correção,
migrations, CI e `.htaccess`.

**Resultado:** 14 achados. 13 corrigidos nesta entrega, 1 recusado com
justificativa registrada (RS-13).

---

## Sumário

| ID | Severidade | Tema | Status |
|----|-----------|------|--------|
| RS-01 | 🔴 Alto | CSP bloqueia confirmação de ações destrutivas | ✅ Corrigido |
| RS-02 | 🟠 Médio | IP forjável na trilha de auditoria | ✅ Corrigido |
| RS-03 | 🟠 Médio | Open redirect via barra invertida no `return_to` | ✅ Corrigido |
| RS-04 | 🟠 Médio | Troca de senha não invalida sessões | ✅ Corrigido |
| RS-05 | 🟠 Médio | Injeção de dependência interrompida nos services | ✅ Corrigido |
| RS-06 | 🟠 Médio | Tela de troca de senha mente sobre "senha temporária" | ✅ Corrigido |
| RS-07 | 🟡 Baixo | `OPENAI_API_KEY` vazia queima 3 retries | ✅ Corrigido |
| RS-08 | 🟡 Baixo | Router responde 404 a HEAD; erro sem `Content-Type` | ✅ Corrigido |
| RS-09 | 🟡 Baixo | Presets de filtro crescem sem teto na sessão | ✅ Corrigido |
| RS-10 | 🟡 Baixo | Worker sempre sai com código 0 | ✅ Corrigido |
| RS-11 | 🟡 Baixo | `.htaccess` com sintaxe Apache 2.2 derruba 2.4 | ✅ Corrigido |
| RS-12 | 🟡 Baixo | `countStale` com janela divergente da constante | ✅ Corrigido |
| RS-13 | 🟡 Baixo | `Request::str` mutila entrada com `strip_tags` | ⛔ Não corrigido (decisão) |
| RS-14 | 🟡 Baixo | Entrada em turma sem throttle permite varrer chaves | ✅ Corrigido |

**O que NÃO foi encontrado** (verificado, está correto): CSRF está presente em
100% das rotas POST; todo SQL é parametrizado e os fragmentos interpolados
(`{$where}`, `{$limit}`) vêm de allowlist ou de `int` saneado; nenhuma saída de
view escapa do `View::e`; a autorização por perfil está centralizada no
middleware do Router com falha fechada; o upload de avatar reencoda via GD e
descarta metadados.

---

## 🔴 RS-01 — CSP bloqueia a confirmação de toda ação destrutiva

**Severidade:** Alta · **Tipo:** Regressão de segurança/funcional

### Problema

`public/index.php` envia `script-src 'self' 'nonce-…' https://cdn.jsdelivr.net`.
Nonce **não** habilita atributo de evento inline — isso exigiria `'unsafe-inline'`
ou `'unsafe-hashes'`, ausentes na política. Os 19 handlers
`onclick="return confirm(...)"` / `onsubmit="return confirm(...)"` espalhados por
11 views eram descartados pelo navegador em silêncio: **a ação destrutiva
executava direto, sem nenhum diálogo**.

Atingia, entre outros: excluir exercício e todos os seus dados, excluir questão,
inativar turma, inativar usuários em lote, encerrar publicações em lote, gerar
link de redefinição de senha e — do lado do aluno — submeter tentativa, que é
irreversível.

Regressão introduzida em `a371f47` ("ajuste mvc e auditoria"), quando a CSP
entrou depois dos handlers. O `app.js` chegou a ser adaptado para a CSP no caso
de `style` (usa `setProperty`), mas os atributos de evento passaram batido.

### Correção

- Os 19 atributos viraram `data-confirm="mensagem"`.
- Dois listeners delegados em `public/assets/js/app.js`, registrados no topo do
  IIFE (antes de qualquer bloco que possa lançar): `click` para
  `[data-confirm]` que não seja `<form>`, `submit` para `form[data-confirm]`.
  Ambos em fase de captura, com `preventDefault` + `stopPropagation` no cancelamento.
- Verificado que nenhum botão com `data-confirm` está dentro de um form que
  também tenha `data-confirm` — não há diálogo duplicado.

### Arquivos

`public/assets/js/app.js`, `views/student/exercises/show.php`,
`views/admin/{turmas,exercises}/{index,show}.php`, `views/admin/users/index.php`,
`views/teacher/dashboard.php`, `views/teacher/students/index.php`,
`views/teacher/turmas/show.php`, `views/teacher/exercises/show.php`,
`views/teacher/questions/create.php`

### Regressão travada

- `bin/run_tests.php`: varre `views/` e falha se qualquer `.php` voltar a usar
  `onclick|onsubmit|onchange|oninput|onload|onerror`.
- `bin/smoke_static.php`: exige `data-confirm` e `form[data-confirm]` em `app.js`
  — sem o listener, os `data-confirm` das views seriam pura decoração.

---

## 🟠 RS-02 — IP da trilha de auditoria é forjável pelo cliente

**Severidade:** Média · **Tipo:** Integridade de auditoria

### Problema

`AuditService::clientIp()` lia `HTTP_CF_CONNECTING_IP` e `HTTP_X_FORWARDED_FOR`
sem nenhuma verificação de origem. Esses cabeçalhos são controlados pelo cliente:
qualquer requisição podia escolher o IP gravado em `audit_logs.ip_address`,
inclusive para atribuir uma ação a terceiros. Inconsistente com
`AuthController::loginClientIp()`, que já usava só `REMOTE_ADDR` (correto).

### Correção

Cabeçalho de proxy só é aceito quando a conexão vem de um proxy declarado em
`TRUSTED_PROXIES` (lista de IPs ou blocos CIDR, separada por vírgula). O valor é
validado com `FILTER_VALIDATE_IP` antes do uso. Sem a variável configurada — o
padrão — vale apenas `REMOTE_ADDR`. Comparação CIDR feita com `inet_pton`,
funciona para IPv4 e IPv6.

### Arquivos

`app/Services/AuditService.php`, `.env.example`, `docs/deploy_operacional.md`

### Ação de deploy

Se o AlgoIA roda atrás de Cloudflare ou outro proxy, preencher `TRUSTED_PROXIES`
com os IPs do proxy. Enquanto estiver vazio, a auditoria registra o IP do proxy
em vez do IP do usuário final — **um IP a menos vale mais que um IP forjado**.

---

## 🟠 RS-03 — Open redirect via barra invertida no `return_to`

**Severidade:** Média · **Tipo:** Segurança

### Problema

`AttemptController::safeReturnPath()` e
`AdminBaseController::buildReturnPathFromRequest()` aceitavam qualquer
`return_to` que começasse com `/` e não contivesse `://`. O valor `/\evil.com`
passava nas duas checagens. O `app_url()` normaliza `//host`, mas não a barra
invertida — e o parser de URL do navegador converte `\` em `/`, transformando
`/\evil.com` em `//evil.com`, ou seja, URL protocol-relative para domínio externo.

Exploração exige usuário autenticado clicando um link preparado, o que limita o
alcance, mas o destino é um redirect a partir de domínio confiável.

### Correção

Helper único `\Core\app_safe_path(string $candidate, string $fallback)` em
`core/Env.php`, junto dos demais helpers de URL. Regra: `#^/(?![/\\])#` — aceita
caminho interno, recusa `//host`, `/\host`, URL absoluta, caminho relativo e
string vazia. Os dois call sites passaram a usar o helper.

### Arquivos

`core/Env.php`, `app/Controllers/AttemptController.php`,
`app/Controllers/AdminBaseController.php`

### Regressão travada

7 asserções em `bin/run_tests.php` cobrindo cada forma recusada e a preservação
de caminho interno com query string.

---

## 🟠 RS-04 — Troca de senha não invalidava sessões abertas

**Severidade:** Média · **Tipo:** Segurança

### Problema

`User::updatePassword()` trocava o hash, mas: (a) a sessão atual não era
regenerada, e (b) sessões do mesmo usuário em outros dispositivos continuavam
válidas, porque `Auth::refreshSessionUser()` só checava `status`. Consequência
prática: trocar a senha não expulsava quem tinha roubado a sessão — exatamente o
cenário em que a troca de senha é a reação esperada.

### Correção

Versionamento de sessão pela senha:

1. Migration `019_users_password_changed_at.sql` adiciona
   `users.password_changed_at` (idempotente, via `information_schema`). Preenche
   as linhas existentes com `created_at` para que o primeiro deploy não derrube
   todas as sessões de uma vez. Coluna também incorporada ao schema consolidado
   `001_create_tables.sql`.
2. `User::updatePassword()` grava `password_changed_at = NOW()`.
3. `Auth::sessionPayload()` (novo, centraliza a montagem do payload — antes
   duplicada em `login()` e `refreshSessionUser()`) inclui o campo.
4. `Auth::refreshSessionUser()` compara sessão × banco e encerra a sessão na
   divergência.
5. `Auth::refreshAfterPasswordChange()` substitui `clearMustChangePassword()`:
   regenera o id da sessão (a antiga não serve mais) e recarrega o payload do
   banco. Sem isso, a sessão que **acabou de trocar a senha** seria derrubada
   pelo próprio mecanismo.

### Limitação conhecida

O refresh é throttled em 60 s (`Auth::refreshSessionUser`), então a invalidação
das outras sessões leva **até 60 s**. Era o preço já aceito pelo projeto para não
consultar o banco a cada request; o trade-off segue válido e está documentado
aqui em vez de escondido no código.

### Arquivos

`database/migrations/019_users_password_changed_at.sql`,
`database/migrations/001_create_tables.sql`, `app/Models/User.php`,
`core/Auth.php`, `app/Controllers/AuthController.php`,
`docs/deploy_operacional.md`

### Ação de deploy

⚠️ **A migration 019 é pré-requisito, não opcional.** Sem a coluna, toda troca de
senha falha com "Unknown column". Aplicar a migration **antes** de publicar os
arquivos.

---

## 🟠 RS-05 — Injeção de dependência interrompida nos services

**Severidade:** Média · **Tipo:** Correção latente / testabilidade

### Problema

O refactor de DI (`9135d55`) deu a `Model` e aos services um construtor
`?Database`, mas os services continuavam instanciando models sem argumento, o que
cai em `Database::getInstance()`. Em `AttemptSubmissionService::submit()` isso
significa que, com uma conexão injetada diferente do singleton,
`(new GradingJob())->enqueueAttempt()` rodaria **fora** da transação aberta pelo
serviço: a tentativa viraria `submitted` sem job na fila, e o aluno ficaria
permanentemente "em correção".

Hoje é latente — em produção só existe o singleton — mas anula o objetivo do
refactor e impede teste com conexão isolada.

### Correção

- `AttemptSubmissionService`: `Question`, `Answer` e `GradingJob` recebem `$db`.
- `AttemptGradingService`: passou a aceitar `?Database` e propaga para `Attempt`,
  `Answer`, `Exercise`, `Question` e `OpenAIService`.
- `GradingJobProcessor`: passou a aceitar `?Database` e propaga para
  `GradingJob`, `Attempt`, `InjectionLog` e `AttemptGradingService`.

Cadeia de DI agora completa de `bin/process_grading_jobs.php` até os models.

### Arquivos

`app/Services/AttemptSubmissionService.php`,
`app/Services/AttemptGradingService.php`, `app/Services/GradingJobProcessor.php`

---

## 🟠 RS-06 — Tela de troca de senha mentia sobre "senha temporária"

**Severidade:** Média · **Tipo:** UX / clareza operacional

### Problema

`User::createPasswordResetToken()` marca `must_change_password = 1` mas **não
troca a senha**. O usuário que não abrir o link (que expira em 60 min) fica
preso em `/password/change`, numa tela que pede "Senha temporária" e responde
"Senha temporária incorreta." quando ele digita a única senha que tem — a atual.
O texto de apoio ("Sua senha foi redefinida pela administração") reforçava a
informação errada: a senha não foi redefinida, só foi exigida a troca.

### Correção

Textos alinhados ao comportamento real: rótulo "Senha atual", erro "Senha atual
incorreta.", "A nova senha deve ser diferente da senha atual." e copy "A
administração solicitou a troca da sua senha. Informe a senha que você usa hoje e
escolha uma nova para continuar."

Mantido o `must_change_password = 1` na emissão do token: ele é o que força a
troca quando o admin suspeita de comprometimento, e removê-lo enfraqueceria o
fluxo. O problema era a mensagem, não o mecanismo.

### Arquivos

`views/auth/change_password.php`, `app/Controllers/AuthController.php`

---

## 🟡 RS-07 — `OPENAI_API_KEY` vazia queimava 3 retries

`OpenAIService` não validava a chave. Com `OPENAI_API_KEY` em branco, cada
resposta disparava 3 tentativas HTTP (com `sleep(2**n)` entre elas) até morrer
com "Erro ao processar avaliação. Tente novamente." — mensagem que não aponta a
causa. `evaluateAnswer()` agora falha imediatamente com
"OPENAI_API_KEY não configurada: correção automática indisponível." e
`GradingJobProcessor::errorCategory()` ganhou a categoria `missing_credentials`,
separando falta de configuração de indisponibilidade do provedor no log.

**Arquivos:** `app/Services/OpenAIService.php`, `app/Services/GradingJobProcessor.php`

---

## 🟡 RS-08 — Router respondia 404 a HEAD; página de erro sem `Content-Type`

`dispatch()` comparava `REQUEST_METHOD` cru, então toda rota GET respondia 404 a
requisição HEAD (health check, crawler, monitor de uptime). HEAD agora é mapeado
para GET — o PHP já descarta o corpo sozinho. `abort()` passou a enviar
`Content-Type: text/html; charset=UTF-8` e `<!doctype html><meta charset="utf-8">`,
para que a página de erro não fique à mercê do sniffing do navegador; o
`htmlspecialchars` ganhou flags explícitas.

**Arquivos:** `core/Router.php`

---

## 🟡 RS-09 — Presets de filtro cresciam sem teto na sessão

`AdminDashboardController::saveFilterPreset()` gravava presets em
`$_SESSION['admin_filter_presets']` sem limite de quantidade. Como o id é o slug
do nome (até 40 chars), bastava salvar com nomes diferentes para inflar a sessão
indefinidamente. Teto de 10 presets por escopo (`MAX_FILTER_PRESETS`), descartando
os mais antigos por `updated_at`.

**Arquivos:** `app/Controllers/AdminDashboardController.php`

---

## 🟡 RS-10 — Worker sempre saía com código 0

`bin/process_grading_jobs.php` retornava sucesso mesmo quando todos os jobs do
lote falhavam — o cron nunca sinalizava degradação da fila de correção.
`GradingJobProcessor` agora conta falhas (`failedCount()`) e o script sai com `1`
quando houve alguma. A saída também passou a informar o número de falhas.

**Arquivos:** `app/Services/GradingJobProcessor.php`,
`bin/process_grading_jobs.php`, `docs/deploy_operacional.md`

### Ação de deploy

Configurar alarme no cron em cima do exit code.

---

## 🟡 RS-11 — `.htaccess` com sintaxe Apache 2.2 podia derrubar o 2.4

O bloco que esconde arquivos iniciados por ponto usava `Order allow,deny` sem
guarda de versão. Em Apache 2.4 sem `mod_access_compat`, essa diretiva é
desconhecida e o servidor responde 500 na raiz — ou seja, a proteção poderia
derrubar o site inteiro. Passou a usar `<IfModule mod_authz_core.c>` com
`Require all denied` e fallback 2.2, mesmo padrão já adotado em
`public/assets/uploads/.htaccess`.

O redirect HTTPS segue comentado (para não quebrar ambiente local em http), mas
ganhou a condição `X-Forwarded-Proto`, necessária atrás de proxy/CDN onde
`%{HTTPS}` chega como `off` mesmo com TLS no cliente.

**Arquivos:** `public/.htaccess`

### Ação de deploy pendente

Descomentar o bloco de redirect HTTPS em produção — não foi feito aqui porque
ativá-lo às cegas quebraria qualquer ambiente servido em http.

---

## 🟡 RS-12 — `countStale` com janela divergente da constante

`GradingJob::countStale()` usava `INTERVAL 15 MINUTE` literal para jobs `queued` e
`self::STALE_PROCESSING_MINUTES` para `processing`. Os valores coincidem hoje, o
que torna a divergência invisível — e garante que mudar a constante quebraria a
contagem em silêncio. Ambos agora usam a constante.

**Arquivos:** `app/Models/GradingJob.php`

---

## ⛔ RS-13 — `Request::str` mutila entrada com `strip_tags` (não corrigido)

**Decisão: manter o comportamento atual.**

`Request::str()` aplica `strip_tags`, então um nome como `Ana <3` é gravado como
`Ana` sem aviso ao usuário. A correção "certa" seria escapar na saída (o que o
projeto já faz, via `View::e`) em vez de mutilar na entrada.

Não foi alterado porque o comportamento é uma invariante deliberada, coberta por
teste explícito em `bin/run_tests.php` ("Request::str remove tags e trim"), e
mudá-lo alteraria a sanitização de toda entrada de formulário do sistema. É uma
decisão de produto, não um bug a corrigir de passagem.

**Se for retomado:** trocar `strip_tags` por validação com rejeição explícita
(mensagem ao usuário), nunca por remoção silenciosa, e atualizar o teste junto.

---

## 🟡 RS-14 — Entrada em turma sem throttle permitia varrer o espaço de chaves

`TurmaController::join()` (`POST /student/turma/join`) não tinha limite de
tentativas. A chave de turma tem 6 caracteres, então um aluno autenticado podia
varrer o espaço de chaves e se inscrever em turmas que não são dele. O pedido
entra como `pending` e ainda depende de aprovação do docente, o que contém o
impacto — mas gera ruído na fila de aprovação e vaza quais chaves existem.

Passou a usar o throttle persistente por IP que já existia para cadastro e reset
(`LoginAttempt::isActionRateLimited` / `recordAction`), com o escopo
`turma_join`: 8 tentativas por janela de 10 minutos. Falha do throttle é
registrada em log e não bloqueia o fluxo, mesmo padrão dos demais escopos. O IP
vem de `REMOTE_ADDR` direto, sem cabeçalho de proxy — coerente com RS-02.

**Arquivos:** `app/Controllers/TurmaController.php`

---

## Verificação

Todas as suítes rodadas após as correções:

```
$ find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 -P4 php -l
(sem erros de sintaxe)

$ php bin/smoke_static.php
Smoke static OK.

$ php bin/run_tests.php
Todos os 63 testes passaram.

$ php bin/run_db_tests.php
Todos os 58 testes de banco passaram.
```

Testes unitários passaram de 55 para 63 (+8: 7 de `app_safe_path`, 1 da varredura
de handler inline nas views). `bin/smoke_static.php` ganhou 6 novos blocos de
invariante, cobrindo RS-01, RS-02, RS-04 e RS-10.

`bin/smoke_schema.php` **não** foi executado — exige MySQL ao vivo, indisponível
no ambiente da revisão. Deve rodar no deploy, depois da migration 019.

---

## Checklist de deploy desta entrega

1. Aplicar `database/migrations/019_users_password_changed_at.sql` **antes** de
   publicar os arquivos (RS-04).
2. Publicar o código.
3. Rodar `php bin/smoke_schema.php` para confirmar o schema.
4. Definir `TRUSTED_PROXIES` no `.env` se houver proxy/CDN na frente (RS-02).
5. Descomentar o redirect HTTPS em `public/.htaccess` (RS-11).
6. Configurar alarme do cron em cima do exit code do worker (RS-10).
7. Validar no navegador, com o DevTools aberto e sem erro de CSP no console, que
   as confirmações voltaram a aparecer: excluir exercício (docente), inativar
   turma (admin) e submeter tentativa (aluno) — RS-01.
