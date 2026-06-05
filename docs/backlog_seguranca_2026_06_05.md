# Backlog de Segurança — Revisão 2026-06-05

Avaliação dos critérios de segurança do AlgoIA (PHP MVC, MySQL/PDO, OpenAI) feita em
2026-06-05 sobre o branch `main` mais os PRs em aberto. O foco foi: injeção, autenticação,
autorização/IDOR, sessão, CSRF, XSS, upload de arquivo, exposição de informação e segredos.

## Metodologia

Leitura dos pontos de entrada e camadas transversais: `core/` (Router, Database, Session,
Request, Auth, View, Env), controllers de autenticação e de recurso, models com SQL, e o
fluxo de upload de avatar. Verificação de parametrização de SQL, cobertura de autorização por
rota, política de sessão/CSRF e sinks perigosos.

## Modelo de severidade

- **P0** — exploração direta, perda de dados/conta, exposição de segredo.
- **P1** — fragilidade real explorável sob condição plausível, ou exposição de informação.
- **P2** — defesa em profundidade; reduz superfície sem corrigir falha ativa.
- **P3** — endurecimento opcional / higiene.

---

## Pontos fortes (já conformes — não regredir)

Documentado para evitar regressão nas correções abaixo.

- **Sem SQL injection:** todo acesso é por `PDO::prepare`/`execute` com placeholders e
  `ATTR_EMULATE_PREPARES => false` (`core/Database.php`). As únicas interpolações em SQL são
  `{$this->table}` (constante de classe) e `{$safeLimit/Offset/Delay/HoursAhead}`, todos
  derivados de `max(...)` sobre parâmetros tipados `int` — não há string de usuário em SQL.
- **CSRF:** token de 32 bytes por sessão, `hash_equals`, validado em todos os POST via
  `Request::validateCsrf()` (`core/Session.php`, `core/Request.php`).
- **Sessão endurecida:** cookie `httponly` + `samesite=Lax` + `secure` (detecta
  `X-Forwarded-Proto`), `session_regenerate_id(true)` no login, timeout de inatividade de
  30 min (`core/Session.php`, `core/Auth.php`).
- **Throttle de login:** `isLoginLocked` checado **antes** da verificação de senha; mensagem
  genérica "E-mail ou senha incorretos"; checagens de status só após senha válida (sem
  enumeração de usuário) (`app/Controllers/AuthController.php`, `app/Models/LoginAttempt.php`).
- **Autorização / IDOR:** rotas autenticadas chamam `Auth::require{Auth,Teacher,Admin,Student}`;
  acesso a recurso por id passa por `getOwnedExercise`/`getOwnedTurma` (checam
  `teacher_id === Auth::id()`) e `attempts->belongsToStudent/belongsToTeacher` via
  `Auth::ensure(...)` (`ExerciseController`, `TurmaController`, `AttemptController`).
- **Senha:** `password_hash`/`verifyPassword`, política ≥10 com maiúscula/minúscula/dígito;
  token de reset guardado como `sha256` com expiração (`AuthController`, `User`).
- **Upload de avatar:** valida MIME real (`finfo`), tamanho, dimensões; **reencoda via GD**
  (descarta EXIF/payload); nome aleatório; `.htaccess` nega execução no diretório
  (`AccountController`, `public/assets/uploads/.htaccess`).
- **Cabeçalhos:** CSP com nonce em `script-src` (sem `unsafe-inline` em script), mais
  `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`
  (`public/index.php`).
- **Segredos:** `.env` no `.gitignore`; erro de conexão de banco genérico ao usuário e
  detalhe só em `error_log` (`core/Database.php`).
- **Sem sinks perigosos:** nenhum `eval/exec/system/shell_exec/unserialize` sobre dado de
  usuário; `include/require` só com caminhos fixos de view.

---

## Epic S1 — Exposição de Informação por Erro

### Prioridade: P1 — ✅ IMPLEMENTADO (2026-06-05)

S1-H1 e S1-H2 entregues nesta mesma rodada: `public/index.php` passou a desligar
`display_errors` fora do debug, registrar `set_exception_handler` + `register_shutdown_function`
(500 genérica + `error_log`); `core/Router.php` genericiza mensagens 5xx.

### S1-H1 Tratamento global de erros condicionado a APP_DEBUG

Como operador
Quero que exceções e warnings nunca apareçam ao usuário em produção
Para não vazar stack trace, caminho de arquivo, SQL ou versão.

Contexto: `config/app.php:9` expõe `debug`, mas **nenhum código usa**. Não há
`set_exception_handler`, `set_error_handler` nem `ini_set('display_errors', ...)`. Se o
`php.ini` do servidor tiver `display_errors=On`, qualquer exceção não tratada
(ex.: falha de query, `RuntimeException` no upload) imprime detalhes ao visitante.

Critérios de aceite:

- `public/index.php` define, no bootstrap: `display_errors=0` e `log_errors=1` quando
  `config('app.debug')` é falso; `display_errors=1` quando verdadeiro.
- `set_exception_handler` registra exceção em `error_log` e responde página 500 genérica
  (sem detalhes) quando `debug=false`.
- `set_error_handler` converte warnings/notices relevantes em log; nada é ecoado ao usuário.
- Teste manual: forçar exceção (ex.: derrubar o banco) mostra "erro interno" genérico, não
  stack trace.

### S1-H2 Mensagens de 500 do Router sem nomes internos

Como mantenedor de segurança
Quero que `Router::abort(500, ...)` não revele classe/método inexistente
Para não expor estrutura interna em rota malformada.

Critérios de aceite:

- `core/Router.php` `abort(500, ...)` emite mensagem genérica ao usuário; o detalhe
  (`Controller não encontrado: ...`) vai só para `error_log`.

---

## Epic S2 — Endurecimento de Transporte e Cabeçalhos

### Prioridade: P2 — ✅ IMPLEMENTADO (2026-06-05)

`public/index.php` envia `Strict-Transport-Security` apenas sob HTTPS; `core/Session.php`
usa prefixo `__Host-` no cookie sob HTTPS e corrige a detecção de HTTPS (antes `isset`
aceitava `HTTPS=off`).

### S2-H1 Cabeçalho HSTS

Como operador
Quero `Strict-Transport-Security` nas respostas
Para impedir downgrade para HTTP (a app roda em HTTPS — `APP_URL` https).

Critérios de aceite:

- `public/index.php` envia `Strict-Transport-Security: max-age=31536000; includeSubDomains`
  apenas quando a requisição é HTTPS (evitar fixar HSTS em ambiente local http).
- Documentado em `docs/deploy_operacional.md` o requisito de TLS no proxy.

### S2-H2 Prefixo de cookie de sessão

Como operador
Quero o cookie de sessão com prefixo `__Host-`/`__Secure-` quando em HTTPS
Para reforçar escopo e flag secure no nível do nome do cookie.

Critérios de aceite:

- Em HTTPS, o nome da sessão usa prefixo seguro; sem regressão em ambiente http local.

---

## Epic S3 — Anti-abuso e Limites

### Prioridade: P2 — ✅ IMPLEMENTADO (2026-06-05)

`LoginAttempt` ganhou throttle genérico por IP (`isActionRateLimited`/`recordAction`,
namespaced por `@scope`); aplicado a `register` (aluno) e `resetPassword` (anti
brute-force de token). `registerTeacher` já tinha throttle próprio. `AccountController`
limita uploads de avatar por sessão (`UPLOAD_MAX`/janela).

### S3-H1 Rate limit em cadastro e reset de senha

Como operador
Quero limitar tentativas de cadastro público e de reset por IP/janela
Para conter spam de contas e enumeração/abuso, reaproveitando o mecanismo de
`LoginAttempt`.

Critérios de aceite:

- `AuthController::register`, `registerTeacher` e o fluxo de reset aplicam um limite por
  IP/janela (modelo análogo a `LoginAttempt`).
- Resposta de reset é sempre genérica (não confirma se o e-mail existe).

### S3-H2 Limite operacional de upload de avatar

Como operador
Quero limitar a frequência de upload de avatar por usuário
Para evitar abuso de CPU (reencode GD) e de disco.

Critérios de aceite:

- `AccountController::uploadAvatar` rejeita uploads acima de N por janela de tempo por
  usuário; excedente recebe mensagem amigável.
- Limite documentado.

---

## Epic S4 — Endurecimento de Renderização

### Prioridade: P3

### S4-H1 Allowlist de templates em `View::capture`

Como mantenedor
Quero que `require $file` em `core/View.php:62` só aceite caminhos sob `views/`
Para tornar impossível um LFI caso algum controller passe nome de template dinâmico.

Critérios de aceite:

- `capture` valida que o caminho resolvido (`realpath`) está dentro de `views/`.
- `extract($data, EXTR_SKIP)` mantido; documentar que chaves de `$data` nunca vêm cruas do
  usuário.

### S4-H2 Reduzir `unsafe-inline` em `style-src`

Como mantenedor de segurança
Quero remover `'unsafe-inline'` de `style-src` na CSP
Para fechar a superfície de injeção de estilo.

Critérios de aceite:

- Inventariar `style="..."` inline restantes nas views e migrá-los para classes.
- `style-src` passa a usar nonce ou remove `'unsafe-inline'` sem quebrar layout.

### S4-H3 Política de senha — teto e (opcional) verificação de vazamento

Como mantenedor
Quero limitar o tamanho máximo da senha (bcrypt trunca em 72 bytes) e, opcionalmente,
recusar senhas vazadas
Para evitar truncamento silencioso e senhas comprometidas.

Critérios de aceite:

- `isStrongPassword` rejeita senha acima de 72 bytes com mensagem clara.
- (Opcional) integração HIBP k-anonymity para bloquear senhas vazadas comuns.

---

## Resumo por prioridade

| Epic | História | Prioridade | Referência principal |
|------|----------|------------|----------------------|
| S1 | H1 Tratamento global de erros | P1 ✅ | `public/index.php`, `config/app.php:9` |
| S1 | H2 500 do Router sem nomes internos | P1 ✅ | `core/Router.php` (`abort`) |
| S2 | H1 Cabeçalho HSTS | P2 ✅ | `public/index.php` |
| S2 | H2 Prefixo de cookie | P2 ✅ | `core/Session.php` |
| S3 | H1 Rate limit cadastro/reset | P2 ✅ | `app/Controllers/AuthController.php`, `app/Models/LoginAttempt.php` |
| S3 | H2 Limite de upload de avatar | P2 ✅ | `app/Controllers/AccountController.php` |
| S4 | H1 Allowlist de templates | P3 | `core/View.php:62` |
| S4 | H2 Reduzir `unsafe-inline` em style-src | P3 | `public/index.php` (CSP) |
| S4 | H3 Teto de senha / HIBP | P3 | `app/Controllers/AuthController.php` |

## Sequenciamento recomendado

1. S1 (exposição de informação) — maior retorno, baixo esforço.
2. S2 (transporte/cabeçalhos).
3. S3 (anti-abuso).
4. S4 (endurecimento residual).
