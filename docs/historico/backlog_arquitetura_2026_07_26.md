# Backlog de Arquitetura — 2026-07-26

Este backlog decorre da análise arquitetural do AlgoIA (PHP MVC próprio, sem
framework/Composer) feita em 2026-07-26. Conclusão: o padrão monolito modular
com camadas leve é adequado ao sistema (instituição única, escala
pequena/média); não há justificativa para migrar para framework completo ou
microserviços. Os itens abaixo endurecem a disciplina de camada existente,
visando inclusão segura de novas funcionalidades.

## Modelo de prioridade

- **P1** — reduz duplicação que já causou (ou pode causar) rota sem checagem
  de autorização.
- **P2** — melhora testabilidade/isolamento sem corrigir falha ativa.
- **P3** — endurecimento/higiene opcional.

---

## Epic A1 — Pipeline de middleware real no Router

### Prioridade: P1 — ✅ IMPLEMENTADO (2026-07-26)

Hoje `Auth::require{Auth,Teacher,Admin,Student}()` é chamado manualmente no
início de cada método de controller — **88 pontos de chamada** em 14
controllers (`AdminExerciseController` sozinho tem 15). `App\Middleware\` já
está registrado no autoloader (`autoload.php:11`) mas a pasta não existe —
nenhum pipeline real é usado. Duplicação massiva: basta esquecer a chamada em
um método novo para expor uma rota sem checagem de papel.

**Entregue:** `Core\Router` ganhou suporte a middleware por rota (`get()`/
`post()`/`dispatch()`, falha fechada para nome de middleware desconhecido —
`core/Router.php`), classes `App\Middleware\{Auth,Teacher,Admin,Student}Middleware`
preencheram o namespace já mapeado no autoloader, `routes/web.php` passou a
declarar o papel exigido em todas as 98 rotas (só as 10 públicas ficam sem
middleware), e os 88 pontos de chamada `Auth::require*()` duplicados nos
controllers foram removidos — a checagem de papel agora tem fonte única.
Teste novo em `bin/run_tests.php` cobre o pipeline de middleware do Router.
`php bin/run_tests.php`, `php bin/smoke_static.php` e `php -l` continuam
passando; auditoria manual confirmou que só as rotas públicas esperadas
ficaram sem middleware.

### A1-H1 Suporte a middleware por rota no Router

Como mantenedor
Quero declarar o papel exigido por rota em `routes/web.php`
Para que a checagem de autorização seja centralizada e não dependa de lembrar
de chamar `Auth::require*()` em cada método novo.

Critérios de aceite:
- `Core\Router::get()/post()` aceitam um terceiro parâmetro `array $middleware`.
- `Router::dispatch()` executa a cadeia de middleware antes de instanciar o
  controller; middleware resolve por nome (`'auth'`, `'teacher'`, `'admin'`,
  `'student'`) para uma classe em `App\Middleware\`.
- Teste unitário (`bin/run_tests.php`) cobre casamento de rota com middleware
  declarado.

### A1-H2 Classes de middleware de papel

Como mantenedor
Quero classes `App\Middleware\{Auth,Teacher,Admin,Student}Middleware`
Para reaproveitar `Core\Auth::require*()` já existente sob uma interface de
middleware, preenchendo o namespace já mapeado no autoloader.

Critérios de aceite:
- Cada classe expõe `handle(): void` estático e delega para o método
  `Auth::require*()` correspondente (sem duplicar lógica de sessão).

### A1-H3 Migrar `routes/web.php` para declarar middleware por rota

Critérios de aceite:
- Toda rota autenticada declara o(s) papel(is) exigido(s) no registro da rota
  (ex.: `$router->get('/admin/users', 'AdminUserController@users', ['admin']);`).
- Rotas públicas (`/login`, `/register*`, `/password/reset`, `/logout`)
  seguem sem middleware.

### A1-H4 Remover chamadas `Auth::require*()` duplicadas dos controllers

Critérios de aceite:
- Após A1-H3, os 88 pontos de chamada nos métodos de controller são
  removidos; a única fonte de verdade da checagem de papel passa a ser
  `routes/web.php`.
- Checagens de posse de recurso (`getOwnedExercise`, `belongsToTeacher` etc.)
  são preservadas — não fazem parte deste item.
- `php bin/run_tests.php`, `php bin/smoke_static.php` e `php -l` em todos os
  controllers continuam passando.

---

## Epic A2 — Injeção de dependência para `Database`

### Prioridade: P2 — ✅ IMPLEMENTADO (2026-07-26)

`Core\Database::getInstance()` é singleton estático (`core/Database.php:33`),
acoplado direto nas Models. Isola mal em teste unitário puro — hoje só é
possível testar contra banco real (`bin/run_db_tests.php`), o que esconde bug
de regra de negócio que não deveria depender de I/O.

**Entregue:** `App\Models\Model::__construct(?Database $db = null)`,
`AttemptStartService`, `AttemptSubmissionService` e `OpenAIService` passam a
aceitar `Database` injetada no construtor, usando `Database::getInstance()`
só como default (`OpenAIService` resolve sob demanda em `db()` — evita exigir
banco real em construções que nunca chegam a logar). Sem mudança de
comportamento em produção: todo call site existente instancia sem argumento e
continua usando o singleton por trás. Novo teste em `bin/run_tests.php`
(A2-H2) prova a regra de negócio "máximo de tentativas atingido" de
`AttemptStartService::start()` com uma `Database` stub (sem PDO/SQLite/MySQL).
`php bin/run_tests.php` (55 testes), `php bin/run_db_tests.php` (58 testes),
`php bin/smoke_static.php` e `php -l` continuam passando.

### A2-H1 Injetar `Database` no construtor de `Model` e `Service`s que usam banco

Critérios de aceite:
- `App\Models\Model::__construct(?Database $db = null)` aceita instância
  opcional; usa `Database::getInstance()` só como default (não obrigatório).
- Services que hoje chamam `Database::getInstance()` diretamente passam a
  receber a dependência.
- Sem quebra de comportamento em produção (bootstrap continua usando o
  singleton por trás).

### A2-H2 Teste unitário de regra de negócio sem banco real

Critérios de aceite:
- Ao menos uma regra de negócio hoje só coberta por `run_db_tests.php` ganha
  teste equivalente em `run_tests.php` usando um double/stub de `Database`.

---

## Epic A3 — Avaliar Composer para bibliotecas de segurança maduras

### Prioridade: P3 — ✅ AVALIADO (2026-07-26)

App é "zero dependência" por design (`autoload.php`, README). Isso evita risco
de supply-chain, mas também significa reimplementar mecanismos de segurança
(rate limit, cliente HTTP) sem patch automático de CVE upstream.

### A3-H1 Levantamento de candidatos

Critérios de aceite:
- Lista curta (2–3 libs) de candidatos maduros e de baixo risco de
  supply-chain para: cliente HTTP (substituir `curl_init` cru em
  `OpenAIService`), rate limiting.
- Decisão registrada (adotar Composer restrito a essas libs, ou manter
  zero-dependência) com justificativa — este item é avaliação, não execução
  automática.

**Levantamento — cliente HTTP** (substitui `curl_init` em
`OpenAIService.php:203`, único ponto de chamada HTTP externo do app, para a
API da OpenAI):

| Candidato | Maturidade | Risco supply-chain |
|---|---|---|
| `guzzlehttp/guzzle` | Muito madura, PSR-18, facilita mock em teste | Puxa `guzzlehttp/psr7` + `psr/http-*` como transitivas |
| `symfony/http-client` | Madura, mantida pela Symfony, HTTP/2 nativo | Puxa `symfony/*-contracts`; menor superfície que Guzzle |
| `php-http/curl-client` | Fina (wrapper de curl), poucas features | Menor superfície, mas ecossistema PSR menos difundido |

**Levantamento — rate limiting:** não há lacuna real hoje. O throttle
existente (`App\Models\LoginAttempt::isActionRateLimited`,
`AuthController::isActionThrottled`, `AccountController::isUploadThrottled`)
é regra de negócio específica (por IP/e-mail/sessão, com janela e persistência
em tabela própria) — uma lib genérica como `symfony/rate-limiter` adicionaria
uma camada de abstração (storage adapter, policy config) sem reduzir código
de forma proporcional, já que a lógica de domínio (escopo, chave, janela)
continuaria custom de qualquer forma.

**Decisão: manter zero-dependência — não adotar Composer agora.**

Justificativa: `OpenAIService::callOpenAI` é a única chamada HTTP externa do
app (um endpoint, uma responsabilidade), já cobre os pontos que uma lib madura
cobriria — timeout configurável, `CURLOPT_SSL_VERIFYPEER`, retry com backoff
exponencial em 429/5xx — em ~45 linhas revisáveis por completo em uma leitura.
Trocar por Guzzle/Symfony HttpClient trocaria uma dependência zero por uma
árvore de pacotes transitivos (PSR contracts, contracts do framework) para
resolver um problema que já não existe em produção. Reavaliar apenas se o app
passar a integrar múltiplos serviços HTTP externos — aí a duplicação de
retry/timeout/backoff por integração justificaria a dependência. Rate
limiting genérico fica descartado: o throttle atual é acoplado a regra de
negócio própria, não a uma preocupação transversal que uma lib resolveria
melhor.

---

## Sequenciamento recomendado

1. A1 (middleware) — maior retorno, fecha lacuna de autorização por
   duplicação/esquecimento.
2. A2 (DI de `Database`) — habilita teste unitário real de regra de negócio.
3. A3 (avaliação de Composer) — decisão, não implementação imediata.
