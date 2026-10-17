# PHP puro, sem framework e sem Composer

O AlgoIA roda em PHP puro com um mini-framework próprio em `core/` e um autoloader próprio, sem dependências de terceiros. O sistema atende uma única instituição, de porte pequeno a médio, e roda em hospedagem compartilhada. Tem uma única integração HTTP externa (OpenAI), que já cobre timeout, verificação TLS e retry com backoff em cerca de 45 linhas revisáveis. Adotar Guzzle, Symfony HttpClient ou um rate limiter genérico trocaria zero dependências por uma árvore de pacotes transitivos sem eliminar a lógica de domínio, que continuaria própria.

## Consequences

- Correções de segurança que uma biblioteca receberia automaticamente precisam ser feitas à mão. Por isso, CSRF, sessão, CSP e throttle de login são código do projeto e são cobertos por `bin/smoke_static.php` e `bin/run_tests.php`.
- Reavaliar só se o app passar a integrar **vários** serviços HTTP externos. Nesse caso, a duplicação de retry e timeout por integração justificaria `symfony/http-client` ou `guzzlehttp/guzzle`. Detalhes da avaliação em `docs/backlog_arquitetura_2026_07_26.md` (A3).
