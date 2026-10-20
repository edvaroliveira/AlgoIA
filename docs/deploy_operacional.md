# Deploy Operacional

Este documento resume os passos operacionais para subir ou atualizar o AlgoIA.

## Servidor (HostGator, deploy em subpasta)

O repositório inteiro fica dentro do `public_html` (ex.: `public_html/algoia`), e o
app responde em `https://<domínio>/algoia/public/`. O `.htaccess` da raiz do projeto
libera só `public/`, redireciona `/algoia/` para `/algoia/public/` e responde 403
para todo o resto. Sem ele, qualquer visitante leria `.env`, docs e
`database/`, e **executaria** pela web os `.php` de `bin/` e `database/seeds/`
(AU-11). Não remova esse arquivo.

O deploy é feito por um cron no servidor que atualiza a cópia a partir da `main`
do GitHub. Use `fetch` + `reset`, nunca `git pull`: depois de uma reescrita de
histórico (como a de 2026-10-17), o `pull` falha e o servidor fica parado na
versão antiga sem aviso.

```bash
cd ~/public_html/algoia && git fetch origin main && git reset --hard origin/main
```

O `reset --hard` preserva o que não é versionado (`.env` e
`public/assets/uploads/`). Para conferir de fora se está tudo bloqueado, só
arquivos que não são `.php` (um GET em `.php` executaria o script):
`/algoia/.env.example` e `/algoia/README.md` devem responder 403.

## Banco de Dados

### Instalacao limpa

1. Criar o banco vazio com charset `utf8mb4`.
2. Executar `database/migrations/001_create_tables.sql`.

O `001_create_tables.sql` e a **linha de base** do schema (2026-10-19): contem
o schema completo, inclusive indices e chaves estrangeiras. Equivale as antigas
migrations `001`–`022`, removidas da pasta e preservadas na tag
`schema-baseline-2026-10-19` (`git checkout schema-baseline-2026-10-19 -- database/migrations`
traz de volta, se algum banco anterior a essa data aparecer).

### Migrations incrementais aplicadas

| Migration | O que faz |
|---|---|
| `023_bank_exercises.sql` | Cria as tabelas do Banco de exercicios (`bank_exercises`, `bank_questions`, `bank_themes`, `bank_exercise_themes`). Idempotente (`CREATE TABLE IF NOT EXISTS`). |
| `024_score_reviews.sql` | Cria `score_reviews` (Revisao de nota pelo Docente e Pedido de revisao do Aluno). Idempotente. |
| `025_notifications.sql` | Cria `notifications` (avisos dentro do app). Idempotente. |
| `026_practice_answers.sql` | Cria `practice_answers` (modo prática). Idempotente. |
| `027_questions_bank_origin.sql` | `questions.bank_question_id` (origem no banco, para o progresso por Tema) e liga importações antigas por texto idêntico. Idempotente. |
| `028_email_notifications.sql` | `users.email_notifications` e fila de e-mail em `notifications` (`emailed_at`, `email_attempts`). Idempotente. |
| `029_turma_posts.sql` | Cria `turma_posts` (mural da Turma). Idempotente. |
| `030_student_achievements.sql` | Cria `student_achievements` (conquistas do Aluno). Idempotente. |
| `031_ranking.sql` | `turmas.ranking_mode` e `users.ranking_anonymous` (ranking opcional da Turma). Idempotente. |

### Dados de teste (seed 003)

`php database/seeds/003_seed_banco_algoritmos.php --teacher-id=N` carrega a populacao minima de teste: 6 topicos de algoritmos x 10 Exercicios x 5 Questoes (300 Questoes), com tres niveis de rigidez de correcao (flexivel, padrao e rigida) e Pontuacao de 1 a 3. Cria 60 itens ativos no Banco de exercicios (o topico e o Tema) e, com `--teacher-id`, 60 Exercicios Concluidos do Docente. E idempotente: titulos ja existentes sao pulados. Os dados ficam em `database/seeds/data/banco_algoritmos/`, e `bin/run_tests.php` valida unicidade, formato e a coerencia da rigidez com a regra da Correcao por IA.

### Mudancas de schema (a partir da linha de base)

Toda mudanca de schema precisa de **duas** coisas no mesmo PR:

1. Atualizar o `001_create_tables.sql` (instalacao limpa).
2. Criar uma migration incremental **idempotente**, numerada a partir da `023`
   (`023_descricao.sql`, `024_...`), para o banco que ja existe. Use o padrao de
   verificar em `information_schema` antes de `ALTER` (`PREPARE`/`EXECUTE`).

O `bin/smoke_schema.php` deve passar tanto num banco criado so pelo `001`
quanto num banco atualizado pelas incrementais. O runner de integracao aplica o
`001` e, em seguida, todas as incrementais da pasta, em ordem.

### Atualizacao de base existente

Aplique, em ordem, as migrations incrementais com numero maior que o da ultima
ja aplicada nesse banco. Nao reexecute o `001` esperando que ele altere tabelas
existentes: ele usa `CREATE TABLE IF NOT EXISTS` e nao muda tabela que ja existe.
O banco de teste do HostGator foi conferido em 2026-10-19 e esta identico a
linha de base.

Antes de atualizar uma base de producao:

1. Fazer backup do banco.
2. Confirmar qual foi a ultima migration incremental aplicada (a linha de base equivale a `022`).
3. Aplicar somente as migrations pendentes, em ordem.
4. Se a base recebeu ajustes manuais, rodar `php bin/smoke_schema.php` antes, para ver divergencias.
5. Executar `php bin/smoke_static.php` para validar invariantes basicos do codigo antes do smoke test funcional.
6. Executar `php bin/run_tests.php` para rodar os testes unitarios (sem banco): sanitizacao de entrada, allowlist de templates, rotas e cache-busting.
7. Executar `php bin/smoke_schema.php` apos aplicar migrations para confirmar que o schema esperado esta disponivel.

## Variaveis de Ambiente

Configurar no `.env`:

- `APP_NAME`
- `APP_ENV`
- `APP_URL`
- `APP_DEBUG`
- `DB_HOST`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `OPENAI_API_KEY`
- `OPENAI_MODEL`
- `TRUSTED_PROXIES`
- `MAIL_DRIVER`, `MAIL_FROM`, `MAIL_FROM_NAME` e, com SMTP, `MAIL_SMTP_HOST`, `MAIL_SMTP_PORT`, `MAIL_SMTP_ENCRYPTION`, `MAIL_SMTP_USER`, `MAIL_SMTP_PASSWORD` (avisos por e-mail; ver abaixo)

`OPENAI_MODEL` tem fallback em `config/openai.php`, mas deve ser definido no ambiente para facilitar troca sem edicao de codigo.

`TRUSTED_PROXIES` e a lista (separada por virgula) de IPs ou blocos CIDR dos proxies/CDN na frente da aplicacao. Vazio e o padrao seguro: os cabecalhos `X-Forwarded-For` e `CF-Connecting-IP` sao ignorados e `audit_logs.ip_address` usa apenas `REMOTE_ADDR`. So preencha com os IPs do seu proxy — qualquer cliente pode forjar esses cabecalhos.

## Avisos por E-mail

Os Avisos do app tambem saem por e-mail, para quem nao desligou em Minha conta.

O e-mail do dominio `edvar.pro.br` fica no **Titan** (MX `mx1.titan.email`), e o SPF autoriza so o Titan (`include:spf.titan.email`). Por isso o envio deve ser por **SMTP autenticado** na caixa `no-reply@edvar.pro.br`; o `mail()` do servidor web nao esta no SPF e o Gmail recusa. Configuracao no `.env` do servidor:

```
MAIL_DRIVER=smtp
MAIL_FROM=no-reply@edvar.pro.br
MAIL_FROM_NAME="AlgoIA"
MAIL_SMTP_HOST=smtp.titan.email
MAIL_SMTP_PORT=465
MAIL_SMTP_ENCRYPTION=ssl
MAIL_SMTP_USER=no-reply@edvar.pro.br
MAIL_SMTP_PASSWORD=<senha da caixa no Titan>
```

`MAIL_SMTP_ENCRYPTION=none` so e aceito para `127.0.0.1`/`localhost` (testes). Para conferir se o servidor alcanca o Titan: `php -r 'var_dump((bool) @fsockopen("ssl://smtp.titan.email", 465, $e, $s, 10));'`. Falhas de SMTP vao para o `error_log` com a resposta do servidor (sem a senha).

Agende no cron, a cada 5 minutos:

```bash
php bin/send_notification_emails.php 200
```

Regras do worker:

- Antes de enviar, gera o lembrete de prazo (Publicacao que encerra em ate 24 h, sem envio) para todos os Alunos ativos, mesmo que nao abram o app.
- Envia uma unica vez cada Aviso nao lido criado nas ultimas 24 h. Aviso ja lido no app nao vira e-mail; avisos antigos nao sao enviados quando o cron e ligado pela primeira vez.
- Ate 3 tentativas por Aviso; dois workers simultaneos nao enviam o mesmo Aviso.
- Codigos de saida: `0` ok, `1` algum e-mail falhou (ex.: senha SMTP errada), `2` configuracao invalida (`MAIL_FROM` ausente, `MAIL_DRIVER` desconhecido, SMTP sem host) — nada e enviado.
- `MAIL_DRIVER=log` grava cada e-mail como linha JSON em `MAIL_LOG_PATH` (usado nos testes de integracao); o driver `smtp` e testado contra `bin/fixtures/fake_smtp_server.php`.

## Cadastro Publico de Docentes

O cadastro publico de docentes nasce desabilitado.

Para habilitar ou desabilitar:

1. Entrar como administrador.
2. Acessar a area de solicitacoes docentes.
3. Usar a acao de alternar cadastro publico.

A configuracao e salva em `system_settings.teacher_registration_enabled` e toda alteracao gera auditoria.

## Correcao por IA

Quando a avaliacao automatica falhar:

- A tentativa fica com status `submitted`.
- O aluno ve a tentativa como "Em correcao".
- Admin e docente visualizam a pendencia nos paineis de correcoes pendentes.
- O reprocessamento pode ser acionado pelo painel.
- A falha inicial e as falhas de reprocessamento sao registradas em auditoria.

O submit do aluno apenas enfileira a correcao em `grading_jobs`. Configure um cron ou tarefa agendada para executar:

```bash
php bin/process_grading_jobs.php 10
```

O argumento numerico define o maximo de jobs processados por execucao. Jobs com falha ficam recuperaveis para nova tentativa automatica ou reprocessamento manual.

O worker sai com codigo `1` quando algum job do lote falhou (`0` quando o lote correu limpo) e com codigo `2` quando a propria configuracao do worker esta invalida (`OPENAI_API_KEY` vazia ou extensao `curl`/`json` ausente) — nesse caso nenhum job chega a ser tentado. Use esses codigos para alarme no cron — ate a revisao de 2026-09-21 o script saia sempre com `0` e a degradacao da fila passava despercebida.
Jobs que ficarem travados como `processing` ou `queued` por mais de `GRADING_JOB_STALE_MINUTES` (padrao 15 minutos) sao recuperados pelo proprio worker e voltam ao ciclo de tentativa. Quando uma tentativa e corrigida manualmente, o job correspondente e marcado como concluido para evitar alerta falso.

Para validar a fila sem chamar a OpenAI, use:

```bash
php bin/process_grading_jobs.php --dry-run 10
```

Esse mesmo comando serve de base para alerta operacional: o campo `stale` do
JSON de saida (`Queue summary: {...}`) conta jobs presos alem do limite
configurado, e `failed` conta jobs que esgotaram `GRADING_JOB_MAX_ATTEMPTS`
(padrao 3) sem sucesso. Um cron de monitoramento separado pode rodar
`--status` periodicamente e alertar quando `stale > 0` ou `failed` crescer
sem reprocessamento.

### Variaveis de configuracao da fila e da OpenAI

| Variavel | Padrao | Efeito |
|---|---|---|
| `OPENAI_TIMEOUT_SECONDS` | 30 | Timeout por chamada HTTP a OpenAI. |
| `OPENAI_MAX_RETRIES` | 3 | Tentativas por chamada antes de marcar o job como falho. |
| `GRADING_JOB_MAX_ATTEMPTS` | 3 | Tentativas de correcao por job antes de esgotar (`status = failed` permanente ate reprocessamento manual). |
| `GRADING_JOB_STALE_MINUTES` | 15 | Minutos parado em `processing`/`queued` antes de ser recuperado automaticamente. |

Aumentar `GRADING_JOB_STALE_MINUTES` sem aumentar o timeout/retry da OpenAI de
forma proporcional pode mascarar workers travados; os dois devem ser ajustados
juntos.

## Prompt e Auditoria Pedagogica

O prompt de IA usa rubrica por faixa de pontuacao e retorna `deduction_reasons`.

Os motivos de desconto sao salvos em `answers.deduction_reasons_json` e podem ser consultados por professor/admin no detalhe da tentativa corrigida.

Tentativas suspeitas de prompt injection sao registradas em `injection_logs`.

### Politica de privacidade e retencao dos `injection_logs`

- **Redacao por truncamento:** o registro guarda apenas um trecho redigido da resposta do aluno (ate 500 caracteres, com marcador de truncamento), nunca o conteudo integral. Vide `OpenAIService::buildInjectionLogSummary`.
- **Uso restrito:** o conteudo serve para revisao manual de incidentes; as telas de professor/admin usam apenas a contagem de ocorrencias (`injection_flag_count`), nao o texto.
- **Retencao:** registros sao apagados apos 180 dias (`App\Models\InjectionLog::RETENTION_DAYS`). A limpeza roda automaticamente ao final de cada execucao do worker `bin/process_grading_jobs.php`, portanto nao requer cron adicional alem do ja configurado para a fila.

## Testes

| Script | O que roda | Precisa de banco? |
|---|---|---|
| `bin/smoke_static.php` | Invariantes de codigo (grep de padroes que nao podem regredir) | Nao |
| `bin/run_tests.php` | Unitarios (sanitizacao, rotas, cache-busting) | Nao |
| `bin/run_db_tests.php` | Camada de dados com SQLite em memoria (SQL portavel) | Nao (SQLite em memoria) |
| `bin/run_integration_tests.php` | Services reais (`AttemptSubmissionService`, `GradingJob`, `User`) contra MySQL real, com duas conexoes para os pontos que dependem de lock (`FOR UPDATE`); smoke HTTP via `php -S` | Sim, MySQL |
| `bin/smoke_schema.php` | Colunas, indices e FKs esperadas no schema aplicado | Sim, MySQL |

### `bin/run_integration_tests.php`

Cobre os pontos de concorrencia do backlog de auditoria profunda (AP-01/AP-02,
AP-03, AP-05, AP-06) rodando os services de producao contra um MySQL real —
nao uma reproducao do SQL em outro motor. Para os pontos cuja correcao foi um
lock, abre duas conexoes reais e prova que a segunda trava/perde a corrida
enquanto a primeira segura a transacao.

**Seguranca:** o script recusa rodar a menos que `DB_DATABASE` contenha
`test`/`ci` (case-insensitive) ou `INTEGRATION_TESTS_CONFIRM=1` esteja
definido — ele **trunca todas as tabelas** do banco configurado no inicio e
no fim. Nunca aponte para um banco de producao.

Rodar localmente (requer MySQL/MariaDB acessivel e as variaveis `DB_*` do
`.env` apontando para um banco de teste dedicado):

```bash
php bin/run_integration_tests.php
```

Sem `pdo_mysql`, sem conexao com o MySQL, ou com um `DB_DATABASE` que nao
pareça de teste, o script pula com aviso e sai com codigo `0` — nao é um
requisito duro para desenvolvimento local sem banco.

No CI, o job `integration` (`.github/workflows/ci.yml`) sobe um servico MySQL
8.0 dedicado (`algoia_ci`) e roda o script automaticamente, separado do job
`test` original ("Lint + testes (sem banco)"), que continua sem MySQL.

### Resetar um banco de teste

Os antigos scripts `000_reset_test_*_hostgator.sql` (com `DROP DATABASE`) foram
removidos: ficavam ao lado das migrations e podiam ser aplicados por engano em
produção. Para zerar um banco **de teste**, apague todas as tabelas dele (no
phpMyAdmin: selecionar todas → Eliminar) e aplique só o schema consolidado:

```bash
mysql -u USER -p BANCO_DE_TESTE < database/migrations/001_create_tables.sql
```

Depois, crie os usuários iniciais com os seeds (`SEED_*` no `.env`). O
`bin/smoke_static.php` falha se algum arquivo de `database/migrations/` contiver
`DROP DATABASE`.
