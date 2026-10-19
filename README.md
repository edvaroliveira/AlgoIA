# AlgoIA (IAProg)

[![CI](https://github.com/edvaroliveira/AlgoIA/actions/workflows/ci.yml/badge.svg)](https://github.com/edvaroliveira/AlgoIA/actions/workflows/ci.yml)

Aplicação web de apoio acadêmico para criação, publicação e **correção automática por IA** de exercícios discursivos. Docentes criam Turmas e Exercícios e publicam para as próprias Turmas; Alunos respondem dentro da janela de cada Publicação; a correção é feita de forma assíncrona usando a API da OpenAI, com moderação e auditoria administrativa.

A linguagem do domínio (Turma, Matrícula, Publicação, Tentativa…) está no [GLOSSARY.md](GLOSSARY.md).

Construída em **PHP puro** (sem framework, sem Composer), com um mini-framework MVC próprio em `core/`.

---

## Funcionalidades

- **Três papéis** — Admin, Docente e Aluno, cada um com rotas e autorização próprias, aplicadas no controller e na consulta ao banco.
- **Turmas e Matrículas** — entrada por Chave de acesso, com aprovação pelo Docente; *Recusar* remove pedido pendente, *Desvincular* remove Aluno já aprovado (de uma Turma ou de todas). Turma inativada pelo Admin bloqueia o acesso dos Alunos e as alterações do Docente.
- **Exercícios** — Rascunho → Concluído → Publicado. Cada **Publicação** (Exercício × Turma) tem janela e Limite de tentativas próprios.
- **Correção por IA** — avaliação automática das respostas via OpenAI, com rubrica por faixa de pontuação e motivos de desconto (`deduction_reasons`).
- **Fila assíncrona** — o envio do aluno enfileira a correção em `grading_jobs`; um worker processa a fila com retry e recuperação de jobs travados.
- **Defesa contra prompt injection** — a resposta do Aluno vai isolada em bloco delimitado (marcadores forjados são neutralizados), padrões suspeitos em português e inglês são registrados em `injection_logs` (só um resumo, nunca o texto completo).
- **Moderação** — o Admin marca Exercícios e Questões como *Sinalizado* (aviso) ou *Bloqueado* (impede publicar e encerra as Publicações).
- **Auditoria** — toda ação sensível gera registro em `audit_logs`.
- **Segurança** — CSRF em todo POST, CSP com nonce, headers de proteção, throttle de login (`login_attempts`), reset de senha por token, troca de senha obrigatória.
- **Painel administrativo** — gestão de usuários, turmas, exercícios e publicações (inclusive em lote), moderação, fila de correção, exportação (CSV/JSON), aprovação de cadastro docente.
- **Conta** — foto de perfil (JPEG/PNG/WebP até 2 MB, reencodada no servidor).

---

## Stack

| Camada | Tecnologia |
|--------|-----------|
| Linguagem | PHP 8.0+ (`declare(strict_types=1)`); CI testa 8.3 e 8.5 |
| Banco | MySQL 5.7+ / MariaDB (PDO, `utf8mb4`); produção e CI de integração em Percona Server 5.7 |
| Front | Views PHP + CSS próprio (`public/assets/`) + Bootstrap 5.3 via CDN jsdelivr |
| IA | OpenAI API (modelo configurável, padrão `gpt-4o`) |
| Dependências | Nenhuma — autoloader próprio (`autoload.php`); decisão em [ADR 0001](docs/adr/0001-php-puro-sem-dependencias.md) |

---

## Estrutura

```
.
├── .htaccess           # Em deploy no public_html: serve só public/, 403 para o resto
├── public/             # Única pasta pública (index.php = front controller)
│   ├── index.php       # Bootstrap: env, headers/CSP, sessão, router
│   └── assets/         # CSS, JS, imagens
├── core/               # Mini-framework: Router, Database, Auth, Session, View, Env, Request
├── app/
│   ├── Controllers/    # Controllers por perfil (Admin*, Teacher*, Student, Auth...)
│   ├── Models/         # Acesso a dados (User, Turma, Exercise, Attempt, GradingJob...)
│   └── Services/       # OpenAIService, AttemptGradingService, GradingJobProcessor, AuditService
├── routes/web.php      # Definição explícita de rotas
├── config/             # app.php, database.php, openai.php
├── database/
│   ├── migrations/     # Schema consolidado (001) + incrementais idempotentes (002–022)
│   └── seeds/          # Seeds de Docente e Admin (senha obrigatória via .env)
├── bin/                # Worker da fila, runners de teste e smoke tests
├── views/              # Templates por perfil (admin/teacher/student/auth)
├── GLOSSARY.md         # Linguagem do domínio
└── docs/               # SPEC, ADRs, deploy, auditorias e backlogs
```

---

## Instalação

### Pré-requisitos
- PHP 8.0+ com as extensões `pdo_mysql`, `curl`, `mbstring`, `gd` e `fileinfo`
- MySQL 5.7+ ou MariaDB
- Apache com `mod_rewrite` (há `.htaccess` na raiz e em `public/`)
- Chave de API da OpenAI

### Passos

1. **Clonar e configurar ambiente**
   ```bash
   git clone <repo> IAProg
   cd IAProg
   cp .env.example .env
   ```
   Edite o `.env`:
   ```ini
   APP_NAME="AlgoIA"
   APP_ENV=production
   APP_URL=https://seu-dominio/algoia
   APP_DEBUG=false

   DB_HOST=localhost
   DB_DATABASE=seu_banco
   DB_USERNAME=seu_usuario
   DB_PASSWORD=sua_senha

   OPENAI_API_KEY=sua_chave_openai
   OPENAI_MODEL=gpt-4o
   ```

2. **Criar o banco** (charset `utf8mb4`) e aplicar o schema consolidado:
   ```bash
   mysql -u USER -p SEU_BANCO < database/migrations/001_create_tables.sql
   ```
   > Instalação limpa: aplique **apenas** o `001`. As migrations `002`–`022` existem só para atualizar bases antigas. Detalhes em [docs/deploy_operacional.md](docs/deploy_operacional.md).

3. **Popular usuários iniciais** (professor/admin). Defina antes `SEED_TEACHER_*` e `SEED_ADMIN_*` no `.env`. Não há senha padrão: os seeds recusam senha vazia, o placeholder do `.env.example` ou senha fraca, e marcam troca obrigatória no primeiro acesso.
   ```bash
   php database/seeds/001_seed_teacher.php
   php database/seeds/002_seed_admin.php
   ```

4. **Servidor web.** O ideal é apontar o document root para `public/`. Em hospedagem compartilhada, com o repositório inteiro dentro do `public_html` (ex.: `public_html/algoia`), o `.htaccess` da raiz serve só `public/` e responde 403 para todo o resto. O app fica em `https://seu-dominio/algoia/public/`. **Não remova esse arquivo**: sem ele, `.env`, `docs/` e `database/` ficam legíveis, e os `.php` de `bin/` e `database/seeds/` são executados por qualquer visitante. Detalhes, inclusive o cron de deploy (`git fetch` + `git reset --hard`, nunca `git pull`), em [docs/deploy_operacional.md](docs/deploy_operacional.md).

5. **Validar a instalação:**
   ```bash
   php bin/smoke_static.php    # invariantes de código (segurança, APIs deprecated, .htaccess)
   php bin/run_tests.php       # testes unitários (sem banco)
   php bin/run_db_tests.php    # camada de dados em SQLite
   php bin/smoke_schema.php    # schema esperado no banco
   ```
   A CI roda, a cada push/PR, `php -l` e esses três primeiros no PHP 8.3 (check obrigatório) e no 8.5, e `bin/run_integration_tests.php` (models, locks e HTTP autenticado) em Percona 5.7.

   > `bin/run_integration_tests.php` **apaga todas as tabelas** do banco configurado. Só rode contra um banco de teste (nome com `test`/`ci`) ou com `INTEGRATION_TESTS_CONFIRM=1` consciente.

---

## Correção por IA (worker da fila)

O envio do Aluno **apenas enfileira** a correção em `grading_jobs` ([ADR 0002](docs/adr/0002-fila-de-correcao-em-tabela-com-cron.md)). É necessário rodar o worker via cron/tarefa agendada (sem ele, nenhuma Tentativa é corrigida):

```bash
php bin/process_grading_jobs.php 10        # processa até 10 jobs
php bin/process_grading_jobs.php --dry-run 10   # inspeciona a fila sem chamar a OpenAI
php bin/process_grading_jobs.php --status 10     # resumo operacional da fila
```

Comportamento:
- Jobs que falham ficam recuperáveis para nova tentativa automática (backoff) ou reprocessamento manual no painel.
- Jobs travados em `processing` por mais de 15 min são recuperados pelo próprio worker.
- Falhas iniciais e de reprocessamento são registradas em auditoria.

Enquanto não corrigida, a Tentativa fica *Enviada* (`submitted`, mostrada como "Em correção") e aparece nos painéis de correções pendentes de Admin e Docente, que podem pedir Reprocessamento.

---

## Cadastro de docentes

O cadastro público de Docentes **nasce desabilitado**. Um Admin habilita/desabilita pela área de solicitações docentes; a flag fica em `system_settings.teacher_registration_enabled` e toda alteração gera auditoria.

---

## Modelo de dados (principais tabelas)

`users` · `turmas` · `student_turma` (Matrícula) · `exercises` · `exercise_turmas` (Publicação) · `questions` · `attempts` (Tentativa) · `answers` · `grading_jobs` · `injection_logs` · `audit_logs` · `login_attempts` · `system_settings`

Janela e Limite de tentativas ficam na Publicação, não no Exercício ([ADR 0003](docs/adr/0003-janela-e-limite-por-publicacao.md)).

---

## Documentação

- [GLOSSARY.md](GLOSSARY.md) — linguagem do domínio (termos canônicos: Turma, Matrícula, Publicação, Tentativa…)
- [docs/adr/](docs/adr/) — decisões arquiteturais registradas
- [docs/spec_sistema_2026_10_17.md](docs/spec_sistema_2026_10_17.md) — especificação técnica e requisitos funcionais (atual)

- [docs/deploy_operacional.md](docs/deploy_operacional.md) — deploy, migrations e operação
- [docs/backlog_auditoria_2026_10_17.md](docs/backlog_auditoria_2026_10_17.md) — auditoria mais recente (AU-01 a AU-11)
- [docs/historico/](docs/historico/) — SPEC e PRD de 2026-05-27, auditorias, revisões e backlogs anteriores (todos concluídos), e o relato do smoke test multinível

---

## Segurança

- Content-Security-Policy com nonce + headers (`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`) aplicados no front controller; CSRF validado em todo POST.
- Senhas com bcrypt, política de senha forte (10–72 caracteres, maiúscula, minúscula e número), reset por token de uso único e troca obrigatória.
- Throttle de tentativas de login, de entrada em Turma e de upload.
- Resposta do Aluno isolada no prompt, com detecção e log de prompt injection.
- Uploads reencodados e pasta sem execução de script; só `public/` é servido.
- Auditoria de ações sensíveis, inclusive operações negadas e bloqueadas.

> **Este repositório é público.** O `.env` (segredos de banco e OpenAI) está no `.gitignore`, assim como dumps de banco (`database/*.sql`). Nunca versione credenciais, dumps ou senhas em documentação; os seeds não têm senha padrão por esse motivo.
