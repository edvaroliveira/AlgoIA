# AlgoIA (IAProg)

Aplicação web de apoio acadêmico para criação, publicação e **correção automática por IA** de exercícios avaliativos. Professores criam turmas e exercícios; alunos respondem; a correção é feita de forma assíncrona usando a API da OpenAI, com rastreabilidade administrativa e auditoria completa.

Construída em **PHP puro** (sem framework, sem Composer), com um mini-framework MVC próprio em `core/`.

---

## Funcionalidades

- **Três perfis** — Admin, Professor (Teacher) e Aluno (Student), cada um com rotas e autorização próprias.
- **Turmas** — entrada por chave, com aprovação de alunos pelo professor.
- **Exercícios** — autoria, conclusão e publicação por turma, com janela de submissão.
- **Correção por IA** — avaliação automática das respostas via OpenAI, com rubrica por faixa de pontuação e motivos de desconto (`deduction_reasons`).
- **Fila assíncrona** — o envio do aluno enfileira a correção em `grading_jobs`; um worker processa a fila com retry e recuperação de jobs travados.
- **Defesa contra prompt injection** — tentativas suspeitas são detectadas e registradas em `injection_logs` (sem armazenar o conteúdo completo).
- **Auditoria** — toda ação sensível gera registro em `audit_logs`.
- **Segurança** — CSP com nonce, headers de proteção, throttle de login (`login_attempts`), reset de senha por token, troca de senha obrigatória.
- **Painel administrativo** — gestão de usuários, turmas, exercícios, moderação, exportação (CSV/JSON), aprovação de cadastro docente.

---

## Stack

| Camada | Tecnologia |
|--------|-----------|
| Linguagem | PHP 8+ (`declare(strict_types=1)`) |
| Banco | MySQL / MariaDB (PDO, `utf8mb4`) |
| Front | Views PHP + CSS próprio (`public/assets/`) + CDN jsdelivr |
| IA | OpenAI API (modelo configurável, padrão `gpt-4o`) |
| Dependências | Nenhuma — autoloader próprio (`autoload.php`) |

---

## Estrutura

```
.
├── public/             # Document root (index.php = front controller)
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
│   ├── migrations/     # Schema consolidado (001) + incrementais idempotentes (002–015)
│   └── seeds/          # Seed de professor e admin
├── bin/                # Worker da fila + smoke tests
├── views/              # Templates por perfil (admin/teacher/student/auth)
└── docs/               # SPEC, PRD, auditorias, deploy, backlogs
```

---

## Instalação

### Pré-requisitos
- PHP 8+ com extensões PDO/MySQL
- MySQL ou MariaDB
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
   > Instalação limpa: aplique **apenas** o `001`. As migrations `002`–`015` existem só para atualizar bases antigas. Detalhes em [docs/deploy_operacional.md](docs/deploy_operacional.md).

3. **Popular usuários iniciais** (professor/admin):
   ```bash
   php database/seeds/001_seed_teacher.php
   php database/seeds/002_seed_admin.php
   ```

4. **Apontar o servidor web** para `public/` como document root (há `.htaccess` para Apache).

5. **Validar a instalação:**
   ```bash
   php bin/smoke_static.php    # invariantes de código
   php bin/smoke_schema.php    # schema esperado no banco
   ```

---

## Correção por IA (worker da fila)

O envio do aluno **apenas enfileira** a correção em `grading_jobs`. É necessário rodar o worker via cron/tarefa agendada:

```bash
php bin/process_grading_jobs.php 10        # processa até 10 jobs
php bin/process_grading_jobs.php --dry-run 10   # inspeciona a fila sem chamar a OpenAI
php bin/process_grading_jobs.php --status 10     # resumo operacional da fila
```

Comportamento:
- Jobs que falham ficam recuperáveis para nova tentativa automática (backoff) ou reprocessamento manual no painel.
- Jobs travados em `processing` por mais de 15 min são recuperados pelo próprio worker.
- Falhas iniciais e de reprocessamento são registradas em auditoria.

Enquanto não corrigida, a tentativa fica como `submitted` ("Em correção") e aparece nos painéis de correções pendentes de admin/professor.

---

## Cadastro de docentes

O cadastro público de docentes **nasce desabilitado**. Um administrador habilita/desabilita pela área de solicitações docentes; a flag fica em `system_settings.teacher_registration_enabled` e toda alteração gera auditoria.

---

## Modelo de dados (principais tabelas)

`users` · `turmas` · `student_turma` · `exercises` · `exercise_turmas` · `questions` · `attempts` · `answers` · `grading_jobs` · `injection_logs` · `audit_logs` · `login_attempts` · `system_settings`

---

## Documentação

- [docs/spec_sistema_2026_05_27.md](docs/spec_sistema_2026_05_27.md) — especificação técnica e requisitos funcionais
- [docs/prd_sistema_2026_05_27.md](docs/prd_sistema_2026_05_27.md) — PRD
- [docs/deploy_operacional.md](docs/deploy_operacional.md) — deploy, migrations e operação
- [docs/smoke_test_multinivel.md](docs/smoke_test_multinivel.md) — plano de smoke test
- `docs/auditoria_*` e `docs/backlog_*` — auditorias e backlog de melhorias

---

## Segurança

- Content-Security-Policy com nonce + headers (`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`) aplicados no front controller.
- Senhas com hash, política de senha forte, reset por token e troca obrigatória.
- Throttle de tentativas de login.
- Detecção e log de prompt injection nas respostas enviadas à IA.
- Auditoria de ações administrativas.

> O `.env` contém segredos (DB e OpenAI) e está no `.gitignore`. Nunca versione.
