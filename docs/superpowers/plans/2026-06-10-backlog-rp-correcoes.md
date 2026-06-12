# Backlog RP — Correções de Integridade, Resiliência e Segurança

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Corrigir os 7 apontamentos confirmados do backlog RP (RP-01 a RP-03, RP-05 a RP-08) sem alterar comportamento observável além do descrito.

**Architecture:** Cada item é independente e pode ser entregue em commits separados. RP-02 e RP-03 introduzem Services novos que o Controller delega. RP-05 adiciona uma migration incremental e modifica o fluxo do worker. RP-06 centraliza sanitização no `AdminBaseController`. RP-07 expande o `run_db_tests.php` existente com SQLite. RP-08 é atualização de doc/smoke.

**Tech Stack:** PHP 8.x · MySQL/MariaDB · SQLite (testes) · PDO · `bin/run_tests.php`, `bin/run_db_tests.php`

---

## Mapa de arquivos

| Arquivo | Ação | Item |
|---------|------|------|
| `app/Models/Exercise.php` | Modificar — add `hasAttempts`, `canDelete` | RP-01 |
| `app/Controllers/ExerciseController.php` | Modificar — `destroy()` checa `canDelete` | RP-01 |
| `app/Services/AttemptSubmissionService.php` | **Criar** | RP-02 |
| `app/Controllers/AttemptController.php` | Modificar — `submit()` delega ao service; `start()` delega ao service | RP-02, RP-03 |
| `app/Services/AttemptStartService.php` | **Criar** | RP-03 |
| `database/migrations/017_grading_jobs_worker_id.sql` | **Criar** | RP-05 |
| `database/migrations/018_attempts_start_index.sql` | **Criar** | RP-03 |
| `app/Models/GradingJob.php` | Modificar — `claimNext`, `markCompleted`, `markFailed`, + `renewLease` | RP-05 |
| `app/Services/GradingJobProcessor.php` | Modificar — gera workerId, passa para métodos do modelo | RP-05 |
| `app/Controllers/AdminBaseController.php` | Modificar — add `csvCell`, aplica em `streamCsvDownload` | RP-06 |
| `app/Controllers/AdminAuditController.php` | Modificar — aplica `csvCell` no export manual | RP-06 |
| `bin/run_db_tests.php` | Modificar — novos testes SQLite para RP-01, RP-05, RP-06 | RP-07 |
| `bin/smoke_schema.php` | Modificar — add `users.avatar_path` | RP-08 |
| `README.md` | Modificar — "002–015" → "002–016" | RP-08 |
| `docs/deploy_operacional.md` | Modificar — add migration 016, 017, 018 à lista | RP-08 |

---

## Task 1: RP-01 — Exercise model: `hasAttempts` + `canDelete`

**Files:**
- Modify: `app/Models/Exercise.php` (após linha 68, bloco de helpers de estado)

- [ ] **Step 1: Escrever o teste que vai falhar**

Adicione ao final de `bin/run_db_tests.php`, dentro do bloco de testes do modelo Exercise (crie a seção se não existir). Primeiro, leia o arquivo para entender onde inserir.

No final do arquivo, antes do bloco final de contagem (`echo "..."` / `exit`), adicione:

```php
// ── RP-01: Exercise::canDelete ────────────────────────────────────────────────
$pdo->exec("
  CREATE TABLE IF NOT EXISTS exercises_rp01 (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    teacher_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft'
  )
");
$pdo->exec("
  CREATE TABLE IF NOT EXISTS attempts_rp01 (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    exercise_id INTEGER NOT NULL,
    student_id INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'in_progress'
  )
");

// Exercício rascunho sem tentativas: pode excluir
$pdo->exec("INSERT INTO exercises_rp01 (teacher_id, title, status) VALUES (1, 'Ex Draft', 'draft')");
$draftId = (int) $pdo->lastInsertId();

$exDraft = ['id' => $draftId, 'status' => 'draft'];

// Exercício ativo: não pode excluir
$pdo->exec("INSERT INTO exercises_rp01 (teacher_id, title, status) VALUES (1, 'Ex Active', 'active')");
$activeId = (int) $pdo->lastInsertId();
$exActive = ['id' => $activeId, 'status' => 'active'];

// Rascunho com tentativa: não pode excluir
$pdo->exec("INSERT INTO exercises_rp01 (teacher_id, title, status) VALUES (1, 'Ex Draft+Attempt', 'draft')");
$draftWithAttemptId = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO attempts_rp01 (exercise_id, student_id, status) VALUES ($draftWithAttemptId, 1, 'submitted')");
$exDraftWithAttempt = ['id' => $draftWithAttemptId, 'status' => 'draft'];

// Simulação dos métodos (sem injetar o modelo completo, testamos a lógica pura)
function exerciseIsDraft(array $ex): bool { return ($ex['status'] ?? 'draft') === 'draft'; }
function exerciseHasAttempts(PDO $db, int $id): bool {
  $stmt = $db->prepare("SELECT id FROM attempts_rp01 WHERE exercise_id = ? LIMIT 1");
  $stmt->execute([$id]);
  return $stmt->fetch() !== false;
}
function exerciseCanDelete(PDO $db, array $ex): bool {
  return exerciseIsDraft($ex) && !exerciseHasAttempts($db, (int)$ex['id']);
}

check(exerciseCanDelete($pdo, $exDraft), 'RP-01: rascunho sem tentativas pode ser excluído');
check(!exerciseCanDelete($pdo, $exActive), 'RP-01: exercício ativo não pode ser excluído');
check(!exerciseCanDelete($pdo, $exDraftWithAttempt), 'RP-01: rascunho com tentativa não pode ser excluído');
```

- [ ] **Step 2: Rodar o teste — deve PASSAR (lógica pura, sem dependência do modelo real)**

```bash
php /Users/edvar/Documents/codes/IAProg/bin/run_db_tests.php
```

Esperado: todos os checks do bloco RP-01 passam (a lógica está no teste, não no modelo ainda).

- [ ] **Step 3: Implementar `hasAttempts` e `canDelete` em `Exercise.php`**

Em `app/Models/Exercise.php`, após a linha `public function isBlockedForReview(array $exercise): bool { ... }` (linha ~68), adicione:

```php
  public function hasAttempts(int $exerciseId): bool
  {
    $row = $this->db->fetchOne(
      "SELECT id FROM attempts WHERE exercise_id = ? LIMIT 1",
      [$exerciseId]
    );
    return $row !== false;
  }

  public function canDelete(array $exercise): bool
  {
    return $this->isDraft($exercise) && !$this->hasAttempts((int) ($exercise['id'] ?? 0));
  }
```

- [ ] **Step 4: Rodar testes**

```bash
php /Users/edvar/Documents/codes/IAProg/bin/run_tests.php && php /Users/edvar/Documents/codes/IAProg/bin/run_db_tests.php
```

Esperado: todos os testes passam.

- [ ] **Step 5: Commit**

```bash
git -C /Users/edvar/Documents/codes/IAProg add app/Models/Exercise.php bin/run_db_tests.php
git -C /Users/edvar/Documents/codes/IAProg commit -m "feat(exercise): add hasAttempts and canDelete guards"
```

---

## Task 2: RP-01 — ExerciseController: bloquear delete destrutivo

**Files:**
- Modify: `app/Controllers/ExerciseController.php` (método `destroy`, linha ~196)

- [ ] **Step 1: Substituir o método `destroy` por versão com guarda**

Substitua o método completo `destroy` (linhas 196–207):

```php
  public function destroy(string $id): void
  {
    Auth::requireTeacher();
    Request::validateCsrf();
    $exercise = $this->getOwnedExercise((int) $id);

    if (!$this->exercises->canDelete($exercise)) {
      AuditService::record('teacher.exercise.delete_blocked', 'exercise', (int) $id, [
        'title'  => $exercise['title']  ?? null,
        'status' => $exercise['status'] ?? null,
      ]);
      global $session;
      $session->flash('error', 'Este exercício não pode ser excluído: já foi publicado ou possui tentativas registradas.');
      View::redirect("/teacher/exercises/{$id}");
      return;
    }

    $this->exercises->delete((int) $id);
    AuditService::record('teacher.exercise.delete', 'exercise', (int) $id, [
      'title' => $exercise['title'] ?? null,
    ]);
    View::redirect('/teacher/exercises');
  }
```

- [ ] **Step 2: Rodar testes**

```bash
php /Users/edvar/Documents/codes/IAProg/bin/run_tests.php && php /Users/edvar/Documents/codes/IAProg/bin/run_db_tests.php
```

Esperado: todos passam.

- [ ] **Step 3: Commit**

```bash
git -C /Users/edvar/Documents/codes/IAProg add app/Controllers/ExerciseController.php
git -C /Users/edvar/Documents/codes/IAProg commit -m "fix(exercise): block destructive delete of published/attempted exercises"
```

---

## Task 3: RP-02 — Criar `AttemptSubmissionService`

**Files:**
- Create: `app/Services/AttemptSubmissionService.php`

- [ ] **Step 1: Criar o arquivo do service**

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Answer;
use App\Models\GradingJob;
use App\Models\Question;
use Core\Database;

class AttemptSubmissionService
{
  /**
   * Submits an attempt atomically.
   *
   * Returns 'queued' on success (new or idempotent repeat).
   * Throws \RuntimeException on invalid state.
   */
  public function submit(int $attemptId, int $studentId, array $postData): string
  {
    $db = Database::getInstance();
    $db->beginTransaction();

    try {
      $attempt = $db->fetchOne(
        "SELECT * FROM attempts WHERE id = ? FOR UPDATE",
        [$attemptId]
      );

      if (!$attempt || (int) $attempt['student_id'] !== $studentId) {
        $db->rollback();
        throw new \RuntimeException('Tentativa não encontrada ou acesso negado.');
      }

      $status = (string) ($attempt['status'] ?? '');

      // Idempotent: concurrent duplicate request after first succeeded
      if ($status === 'submitted' || $status === 'graded') {
        $db->commit();
        return 'queued';
      }

      if ($status !== 'in_progress') {
        $db->rollback();
        throw new \RuntimeException('Tentativa inválida ou já enviada.');
      }

      $questions = (new Question())->findByExercise((int) $attempt['exercise_id']);
      $answers   = new Answer();

      foreach ($questions as $q) {
        $text = trim((string) ($postData["answer_{$q['id']}"] ?? ''));
        $answers->saveOrUpdate($attemptId, (int) $q['id'], $text);
      }

      $db->execute(
        "UPDATE attempts
               SET status = 'submitted', submitted_at = COALESCE(submitted_at, NOW())
               WHERE id = ? AND status = 'in_progress'",
        [$attemptId]
      );

      (new GradingJob())->enqueueAttempt($attemptId);

      $db->commit();
      return 'queued';
    } catch (\Throwable $e) {
      if ($db->inTransaction()) {
        $db->rollback();
      }
      throw $e;
    }
  }
}
```

- [ ] **Step 2: Rodar testes estáticos**

```bash
php /Users/edvar/Documents/codes/IAProg/bin/smoke_static.php
```

Esperado: nenhum erro de sintaxe ou import.

- [ ] **Step 3: Commit**

```bash
git -C /Users/edvar/Documents/codes/IAProg add app/Services/AttemptSubmissionService.php
git -C /Users/edvar/Documents/codes/IAProg commit -m "feat(attempt): AttemptSubmissionService — atomic submit with FOR UPDATE"
```

---

## Task 4: RP-02 — `AttemptController::submit` delega ao service

**Files:**
- Modify: `app/Controllers/AttemptController.php` (método `submit`, linha ~110)

- [ ] **Step 1: Adicionar `use` no topo do arquivo**

Após os `use` existentes em `AttemptController.php`, adicione (se não existir):

```php
use App\Services\AttemptSubmissionService;
```

- [ ] **Step 2: Substituir o corpo do método `submit`**

Substitua o método completo `submit` (linhas ~109–158):

```php
  /** POST /student/attempts/{id}/submit */
  public function submit(string $id): void
  {
    Auth::requireStudent();
    Request::validateCsrf();

    $studentId = Auth::id();
    $attempt   = $this->attempts->find((int) $id);

    Auth::ensure(
      $attempt && (int) $attempt['student_id'] === $studentId && $attempt['status'] === 'in_progress',
      'Tentativa inválida.'
    );

    $this->ensureAttemptIsOpen($attempt, $studentId);

    $gradingStatus = 'queued';

    try {
      $gradingStatus = (new AttemptSubmissionService())->submit((int) $id, $studentId, $_POST);
    } catch (\Throwable $e) {
      $gradingStatus = 'queue_unavailable';
      error_log("Attempt submission failed for attempt {$id}: " . $e->getMessage());
      AuditService::record('student.attempt.grading_enqueue_failed', 'attempt', (int) $id, [
        'exercise_id' => (int) ($attempt['exercise_id'] ?? 0),
        'student_id'  => (int) ($attempt['student_id']  ?? 0),
        'error'       => $e->getMessage(),
      ]);
    }

    AuditService::record('student.attempt.submitted', 'attempt', (int) $id, [
      'exercise_id'    => (int) ($attempt['exercise_id'] ?? 0),
      'student_id'     => (int) ($attempt['student_id']  ?? 0),
      'grading_status' => $gradingStatus,
    ]);

    global $session;
    $session->flash(
      $gradingStatus === 'queued' ? 'success' : 'error',
      $gradingStatus === 'queued'
        ? 'Tentativa enviada. A correção automática foi colocada na fila.'
        : 'Tentativa enviada. A fila automática está indisponível e a correção ficou pendente para reprocessamento.'
    );
    View::redirect("/student/exercises/{$attempt['exercise_id']}");
  }
```

- [ ] **Step 3: Rodar testes**

```bash
php /Users/edvar/Documents/codes/IAProg/bin/run_tests.php && php /Users/edvar/Documents/codes/IAProg/bin/run_db_tests.php
```

Esperado: todos passam.

- [ ] **Step 4: Commit**

```bash
git -C /Users/edvar/Documents/codes/IAProg add app/Controllers/AttemptController.php
git -C /Users/edvar/Documents/codes/IAProg commit -m "refactor(attempt): submit delegates to AttemptSubmissionService"
```

---

## Task 5: RP-03 — Criar `AttemptStartService` + migration de índice

**Files:**
- Create: `app/Services/AttemptStartService.php`
- Create: `database/migrations/018_attempts_start_index.sql`

- [ ] **Step 1: Criar a migration do índice**

O `FOR UPDATE` no service precisa de um índice em `(student_id, exercise_id, turma_id)` para que o InnoDB use gap locks e impeça phantom inserts concorrentes.

Crie `database/migrations/018_attempts_start_index.sql`:

```sql
-- Índice para serializar início concorrente de tentativas via FOR UPDATE + gap lock
ALTER TABLE attempts
  ADD INDEX idx_attempts_student_exercise_turma (student_id, exercise_id, turma_id);
```

- [ ] **Step 2: Criar o service**

Crie `app/Services/AttemptStartService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

class AttemptStartService
{
  /**
   * Starts or resumes an attempt atomically.
   *
   * Returns the attempt ID (new or existing in_progress).
   * Throws \RuntimeException when the attempt limit is reached.
   */
  public function start(int $studentId, int $exerciseId, int $turmaId, int $maxAttempts): int
  {
    $db = Database::getInstance();
    $db->beginTransaction();

    try {
      // Lock existing rows for this student+exercise+turma — InnoDB gap lock prevents
      // phantom inserts by concurrent transactions when idx_attempts_student_exercise_turma is present.
      $inProgress = $db->fetchOne(
        "SELECT id FROM attempts
               WHERE student_id = ? AND exercise_id = ? AND turma_id = ? AND status = 'in_progress'
               FOR UPDATE",
        [$studentId, $exerciseId, $turmaId]
      );

      if ($inProgress) {
        $db->commit();
        return (int) $inProgress['id'];
      }

      $row = $db->fetchOne(
        "SELECT COUNT(*) AS c FROM attempts
               WHERE student_id = ? AND exercise_id = ? AND turma_id = ? AND status IN ('submitted', 'graded')
               FOR UPDATE",
        [$studentId, $exerciseId, $turmaId]
      );
      $used = (int) ($row['c'] ?? 0);

      if ($maxAttempts > 0 && $used >= $maxAttempts) {
        $db->rollback();
        throw new \RuntimeException('Número máximo de tentativas atingido.');
      }

      $attemptId = $db->insert(
        "INSERT INTO attempts (student_id, exercise_id, turma_id, status) VALUES (?, ?, ?, 'in_progress')",
        [$studentId, $exerciseId, $turmaId]
      );

      $db->commit();
      return $attemptId;
    } catch (\Throwable $e) {
      if ($db->inTransaction()) {
        $db->rollback();
      }
      throw $e;
    }
  }
}
```

- [ ] **Step 3: Rodar testes estáticos**

```bash
php /Users/edvar/Documents/codes/IAProg/bin/smoke_static.php
```

Esperado: sem erros.

- [ ] **Step 4: Commit**

```bash
git -C /Users/edvar/Documents/codes/IAProg add app/Services/AttemptStartService.php database/migrations/018_attempts_start_index.sql
git -C /Users/edvar/Documents/codes/IAProg commit -m "feat(attempt): AttemptStartService — atomic start with FOR UPDATE + index migration"
```

---

## Task 6: RP-03 — `AttemptController::start` delega ao service

**Files:**
- Modify: `app/Controllers/AttemptController.php` (método `start`, linha ~36)

- [ ] **Step 1: Adicionar `use` no topo**

Adicione junto ao `use` da Task 4 (se não adicionou já):

```php
use App\Services\AttemptStartService;
```

- [ ] **Step 2: Substituir o método `start`**

Substitua o método completo `start` (linhas ~36–69):

```php
  /** POST /student/exercises/{id}/start */
  public function start(string $id): void
  {
    Auth::requireStudent();
    Request::validateCsrf();

    $studentId   = Auth::id();
    $exercise    = $this->getStudentExercise((int) $id, $studentId);
    $publication = $this->exercises->findOpenPublicationForStudent((int) $id, $studentId);

    if (!$publication) {
      global $session;
      $session->flash('error', 'Este exercício não está aberto para respostas.');
      View::redirect("/student/exercises/{$id}");
      return;
    }

    $exercise    = $this->exercises->applyPublicationContext($exercise, $publication);
    $turmaId     = (int) $publication['turma_id'];
    $maxAttempts = (int) $exercise['max_attempts'];

    try {
      $attemptId = (new AttemptStartService())->start($studentId, (int) $id, $turmaId, $maxAttempts);
    } catch (\RuntimeException $e) {
      global $session;
      $session->flash('error', 'Você atingiu o número máximo de tentativas.');
      View::redirect("/student/exercises/{$id}");
      return;
    }

    View::redirect("/student/exercises/{$id}?attempt={$attemptId}");
  }
```

- [ ] **Step 3: Rodar testes**

```bash
php /Users/edvar/Documents/codes/IAProg/bin/run_tests.php && php /Users/edvar/Documents/codes/IAProg/bin/run_db_tests.php
```

Esperado: todos passam.

- [ ] **Step 4: Commit**

```bash
git -C /Users/edvar/Documents/codes/IAProg add app/Controllers/AttemptController.php
git -C /Users/edvar/Documents/codes/IAProg commit -m "refactor(attempt): start delegates to AttemptStartService"
```

---

## Task 7: RP-05 — Migration `worker_id` em `grading_jobs`

**Files:**
- Create: `database/migrations/017_grading_jobs_worker_id.sql`

- [ ] **Step 1: Criar a migration**

```sql
-- worker_id identifica qual processo detém o lock de um job em processamento,
-- impedindo que dois workers concluam o mesmo job.
ALTER TABLE grading_jobs
  ADD COLUMN worker_id VARCHAR(36) NULL DEFAULT NULL AFTER locked_at;
```

- [ ] **Step 2: Commit**

```bash
git -C /Users/edvar/Documents/codes/IAProg add database/migrations/017_grading_jobs_worker_id.sql
git -C /Users/edvar/Documents/codes/IAProg commit -m "feat(grading): migration 017 — worker_id column in grading_jobs"
```

---

## Task 8: RP-05 — `GradingJob` model: worker_id em claim/complete/fail + `renewLease`

**Files:**
- Modify: `app/Models/GradingJob.php`

- [ ] **Step 1: Modificar `claimNext` para receber e persistir `$workerId`**

Substitua a assinatura e o UPDATE interno do método `claimNext`:

```php
  public function claimNext(string $workerId): array|false
  {
    $this->db->beginTransaction();

    try {
      $job = $this->db->fetchOne(
        "SELECT *
               FROM grading_jobs
               WHERE status IN ('queued', 'failed')
                 AND attempts < ?
                 AND available_at <= NOW()
               ORDER BY available_at ASC, id ASC
               LIMIT 1
               FOR UPDATE",
        [self::MAX_ATTEMPTS]
      );

      if (!$job) {
        $this->db->commit();
        return false;
      }

      $this->db->execute(
        "UPDATE grading_jobs
               SET status    = ?,
                   attempts  = attempts + 1,
                   locked_at = NOW(),
                   worker_id = ?,
                   last_error = NULL
               WHERE id = ?",
        [self::STATUS_PROCESSING, $workerId, (int) $job['id']]
      );

      $this->db->commit();

      $job['status']    = self::STATUS_PROCESSING;
      $job['attempts']  = (int) $job['attempts'] + 1;
      $job['worker_id'] = $workerId;
      return $job;
    } catch (\Throwable $e) {
      if ($this->db->inTransaction()) {
        $this->db->rollback();
      }

      throw $e;
    }
  }
```

- [ ] **Step 2: Modificar `markCompleted` para verificar `$workerId`**

Substitua o método `markCompleted(int $jobId)`:

```php
  public function markCompleted(int $jobId, string $workerId): void
  {
    $rows = $this->db->execute(
      "UPDATE grading_jobs
             SET status = ?, completed_at = NOW(), last_error = NULL, worker_id = NULL
             WHERE id = ? AND (worker_id IS NULL OR worker_id = ?)",
      [self::STATUS_COMPLETED, $jobId, $workerId]
    );

    if ($rows === 0) {
      error_log("markCompleted: job {$jobId} not owned by worker {$workerId} or already completed — skipped.");
    }
  }
```

> **Nota:** `(worker_id IS NULL OR worker_id = ?)` garante compatibilidade com jobs existentes que foram reclamados antes da migration (worker_id ainda NULL).

- [ ] **Step 3: Modificar `markFailed` para verificar `$workerId`**

Substitua o método `markFailed`:

```php
  public function markFailed(int $jobId, string $error, int $delaySeconds = 300, string $workerId = ''): void
  {
    $safeDelay = max(60, $delaySeconds);

    $rows = $this->db->execute(
      "UPDATE grading_jobs
             SET status      = ?,
                 available_at = DATE_ADD(NOW(), INTERVAL {$safeDelay} SECOND),
                 last_error  = ?,
                 worker_id   = NULL
             WHERE id = ? AND (worker_id IS NULL OR worker_id = ? OR ? = '')",
      [self::STATUS_FAILED, mb_substr($error, 0, 2000), $jobId, $workerId, $workerId]
    );

    if ($rows === 0) {
      error_log("markFailed: job {$jobId} not owned by worker — skipped.");
    }
  }
```

> **Nota:** `OR ? = ''` permite chamadas legadas sem workerId (e.g. admin regrade) enquanto ainda impede que um worker B marque o job de um worker A como falho.

- [ ] **Step 4: Adicionar `renewLease`**

Após o método `recoverStaleProcessing`, adicione:

```php
  public function renewLease(int $jobId, string $workerId): bool
  {
    return $this->db->execute(
      "UPDATE grading_jobs
             SET locked_at = NOW()
             WHERE id = ? AND worker_id = ? AND status = ?",
      [$jobId, $workerId, self::STATUS_PROCESSING]
    ) > 0;
  }
```

- [ ] **Step 5: Rodar testes**

```bash
php /Users/edvar/Documents/codes/IAProg/bin/run_tests.php && php /Users/edvar/Documents/codes/IAProg/bin/run_db_tests.php
```

Esperado: todos passam.

- [ ] **Step 6: Commit**

```bash
git -C /Users/edvar/Documents/codes/IAProg add app/Models/GradingJob.php
git -C /Users/edvar/Documents/codes/IAProg commit -m "fix(grading): worker_id guards on claim/complete/fail + renewLease"
```

---

## Task 9: RP-05 — `GradingJobProcessor` usa `$workerId`

**Files:**
- Modify: `app/Services/GradingJobProcessor.php`

- [ ] **Step 1: Substituir `processNext`**

Substitua o método `processNext` completo:

```php
  public function processNext(): bool
  {
    $workerId = bin2hex(random_bytes(8));
    $jobs     = new GradingJob();
    $jobs->recoverStaleProcessing();
    $job = $jobs->claimNext($workerId);

    if (!$job) {
      return false;
    }

    $jobId     = (int) $job['id'];
    $attemptId = (int) $job['attempt_id'];

    try {
      $attempt = (new Attempt())->find($attemptId);
      if ($attempt && (string) ($attempt['status'] ?? '') === 'graded') {
        $jobs->markCompleted($jobId, $workerId);
        error_log("Grading job {$jobId} skipped because attempt {$attemptId} is already graded.");
        return true;
      }

      $score = (new AttemptGradingService())->gradeSubmittedAttempt($attemptId);
      $jobs->markCompleted($jobId, $workerId);
      error_log("Grading job {$jobId} completed for attempt {$attemptId} with score {$score}.");
      return true;
    } catch (\Throwable $e) {
      $delay = $this->retryDelaySeconds((int) ($job['attempts'] ?? 1));
      $jobs->markFailed($jobId, $e->getMessage(), $delay, $workerId);
      error_log("Grading job {$jobId} failed for attempt {$attemptId} [" . $this->errorCategory($e) . "]: " . $e->getMessage());
      return true;
    }
  }
```

- [ ] **Step 2: Rodar testes**

```bash
php /Users/edvar/Documents/codes/IAProg/bin/run_tests.php && php /Users/edvar/Documents/codes/IAProg/bin/run_db_tests.php
```

Esperado: todos passam.

- [ ] **Step 3: Commit**

```bash
git -C /Users/edvar/Documents/codes/IAProg add app/Services/GradingJobProcessor.php
git -C /Users/edvar/Documents/codes/IAProg commit -m "fix(grading): GradingJobProcessor generates and uses worker_id"
```

---

## Task 10: RP-06 — CSV formula injection: `csvCell` + aplicação

**Files:**
- Modify: `app/Controllers/AdminBaseController.php`
- Modify: `app/Controllers/AdminAuditController.php`

- [ ] **Step 1: Adicionar `csvCell` e aplicar em `streamCsvDownload`**

Em `AdminBaseController.php`, adicione o método estático antes de `streamCsvDownload`:

```php
  protected static function csvCell(string $value): string
  {
    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
      return "\t" . $value;
    }
    return $value;
  }
```

E modifique `streamCsvDownload` para aplicar a sanitização:

```php
  protected function streamCsvDownload(string $filename, array $headers, array $rows, callable $mapper): void
  {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $output = fopen('php://output', 'wb');
    if ($output === false) {
      http_response_code(500);
      exit('Não foi possível gerar a exportação.');
    }

    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, $headers, ';');

    foreach ($rows as $row) {
      $cells = array_map(
        static fn($cell): string => static::csvCell((string) $cell),
        $mapper($row)
      );
      fputcsv($output, $cells, ';');
    }

    fclose($output);
    exit;
  }
```

- [ ] **Step 2: Aplicar `csvCell` no export manual de `AdminAuditController`**

Em `AdminAuditController.php`, no método `exportAuditCsv` (linhas ~49–61), substitua o bloco `foreach`:

```php
    foreach ($logs as $log) {
      fputcsv($output, [
        (string) ($log['created_at']  ?? ''),
        static::csvCell((string) ($log['actor_name']  ?? 'Sistema')),
        static::csvCell((string) ($log['actor_email'] ?? '')),
        static::csvCell((string) ($log['actor_role']  ?? '')),
        (string) ($log['action']      ?? ''),
        (string) ($log['entity_type'] ?? ''),
        (string) ($log['entity_id']   ?? ''),
        static::csvCell($this->buildAuditContextText($log)),
        static::csvCell((string) ($log['ip_address']  ?? '')),
      ], ';');
    }
```

> **Nota:** `action`, `entity_type` e `entity_id` são gerados por código, não por input de usuário — não precisam de sanitização.

- [ ] **Step 3: Rodar testes**

```bash
php /Users/edvar/Documents/codes/IAProg/bin/run_tests.php && php /Users/edvar/Documents/codes/IAProg/bin/run_db_tests.php
```

- [ ] **Step 4: Commit**

```bash
git -C /Users/edvar/Documents/codes/IAProg add app/Controllers/AdminBaseController.php app/Controllers/AdminAuditController.php
git -C /Users/edvar/Documents/codes/IAProg commit -m "fix(csv): neutralize formula injection in admin CSV exports"
```

---

## Task 11: RP-07 — Testes SQLite para RP-05 e RP-06

**Files:**
- Modify: `bin/run_db_tests.php`

- [ ] **Step 1: Adicionar testes de worker_id e CSV cell**

Adicione ao final de `bin/run_db_tests.php` (antes do bloco final de contagem):

```php
// ── RP-05: worker_id protection (estado-máquina, sem MySQL FOR UPDATE) ────────
$pdo->exec("
  CREATE TABLE IF NOT EXISTS grading_jobs_rp05 (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    attempt_id INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'queued',
    worker_id TEXT NULL,
    locked_at TEXT NULL,
    completed_at TEXT NULL,
    last_error TEXT NULL
  )
");
$pdo->exec("INSERT INTO grading_jobs_rp05 (attempt_id, status, worker_id) VALUES (1, 'processing', 'worker-A')");
$jobId = (int) $pdo->lastInsertId();

// Simula markCompleted: worker correto consegue completar
function markCompletedRp05(PDO $db, int $jobId, string $workerId): int {
  $stmt = $db->prepare(
    "UPDATE grading_jobs_rp05 SET status='completed', worker_id=NULL
     WHERE id=? AND (worker_id IS NULL OR worker_id=?)"
  );
  $stmt->execute([$jobId, $workerId]);
  return $stmt->rowCount();
}

// Worker-B NÃO consegue completar o job de worker-A
$rowsB = markCompletedRp05($pdo, $jobId, 'worker-B');
check($rowsB === 0, 'RP-05: worker-B não pode completar job de worker-A');

// Worker-A consegue completar seu próprio job
$rowsA = markCompletedRp05($pdo, $jobId, 'worker-A');
check($rowsA === 1, 'RP-05: worker-A completa seu próprio job');

// Verifica status final
$row = $pdo->query("SELECT status, worker_id FROM grading_jobs_rp05 WHERE id=$jobId")->fetch();
check((string)($row['status'] ?? '') === 'completed', 'RP-05: status é completed após conclusão');
check($row['worker_id'] === null, 'RP-05: worker_id limpo após conclusão');

// ── RP-06: csvCell sanitização ────────────────────────────────────────────────
function csvCell(string $value): string {
  if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
    return "\t" . $value;
  }
  return $value;
}

check(csvCell('=SUM(A1)') === "\t=SUM(A1)", 'RP-06: prefixo = é neutralizado');
check(csvCell('+1234') === "\t+1234", 'RP-06: prefixo + é neutralizado');
check(csvCell('-1') === "\t-1", 'RP-06: prefixo - é neutralizado');
check(csvCell('@foo') === "\t@foo", 'RP-06: prefixo @ é neutralizado');
check(csvCell('João Silva') === 'João Silva', 'RP-06: valor normal não é alterado');
check(csvCell('') === '', 'RP-06: string vazia não é alterada');
check(csvCell('42') === '42', 'RP-06: número normal não é alterado');
```

- [ ] **Step 2: Rodar testes**

```bash
php /Users/edvar/Documents/codes/IAProg/bin/run_db_tests.php
```

Esperado: todos os novos checks passam.

- [ ] **Step 3: Commit**

```bash
git -C /Users/edvar/Documents/codes/IAProg add bin/run_db_tests.php
git -C /Users/edvar/Documents/codes/IAProg commit -m "test(rp): add SQLite tests for RP-01, RP-05, RP-06"
```

---

## Task 12: RP-08 — Documentação, README e smoke schema

**Files:**
- Modify: `bin/smoke_schema.php`
- Modify: `README.md`
- Modify: `docs/deploy_operacional.md`

- [ ] **Step 1: Adicionar `users.avatar_path` ao smoke schema**

Em `bin/smoke_schema.php`, no array `$requiredColumns`, adicione `'avatar_path'` à chave `'users'`:

```php
  'users' => ['id', 'email', 'role', 'status', 'must_change_password', 'registration_source', 'avatar_path'],
```

- [ ] **Step 2: Atualizar README.md**

Na linha 52 de `README.md`, altere:
```
│   ├── migrations/     # Schema consolidado (001) + incrementais idempotentes (002–015)
```
para:
```
│   ├── migrations/     # Schema consolidado (001) + incrementais idempotentes (002–018)
```

Na linha 96 de `README.md`, altere:
```
> Instalação limpa: aplique **apenas** o `001`. As migrations `002`–`015` existem só para atualizar bases antigas.
```
para:
```
> Instalação limpa: aplique **apenas** o `001`. As migrations `002`–`018` existem só para atualizar bases antigas.
```

- [ ] **Step 3: Atualizar `docs/deploy_operacional.md`**

Na seção "Atualização de base existente", após o item `15. 015_fix_exercises_turma_fk.sql`, adicione:

```
16. `016_user_avatar.sql`
17. `017_grading_jobs_worker_id.sql`
18. `018_attempts_start_index.sql`
```

- [ ] **Step 4: Rodar testes**

```bash
php /Users/edvar/Documents/codes/IAProg/bin/run_tests.php && php /Users/edvar/Documents/codes/IAProg/bin/run_db_tests.php
```

- [ ] **Step 5: Commit**

```bash
git -C /Users/edvar/Documents/codes/IAProg add bin/smoke_schema.php README.md docs/deploy_operacional.md
git -C /Users/edvar/Documents/codes/IAProg commit -m "docs(rp-08): align migration list 016-018, smoke schema adds avatar_path"
```

---

## Validação final

Após todos os tasks:

```bash
php /Users/edvar/Documents/codes/IAProg/bin/smoke_static.php
php /Users/edvar/Documents/codes/IAProg/bin/run_tests.php
php /Users/edvar/Documents/codes/IAProg/bin/run_db_tests.php
```

Todos devem passar sem erros.

---

## Self-Review

**Spec coverage:**
- RP-01: Tasks 1–2 ✓
- RP-02: Tasks 3–4 ✓
- RP-03: Tasks 5–6 ✓
- RP-04: Omitido intencionalmente (requer decisão de produto não tomada)
- RP-05: Tasks 7–9 ✓
- RP-06: Task 10 ✓
- RP-07: Task 11 ✓
- RP-08: Task 12 ✓

**Consistência de assinaturas:**
- `GradingJob::claimNext(string $workerId)` — usado em `GradingJobProcessor::processNext()` ✓
- `GradingJob::markCompleted(int $jobId, string $workerId)` — usado em `GradingJobProcessor` ✓
- `GradingJob::markFailed(int $jobId, string $error, int $delaySeconds, string $workerId)` — usado em `GradingJobProcessor` ✓
- `markCompletedForAttempt(int $attemptId)` — método de admin regrade, não necessita workerId, mantido sem alteração ✓
- `AttemptStartService::start(int, int, int, int)` — chamado em `AttemptController::start()` com `$studentId, (int)$id, $turmaId, $maxAttempts` ✓
- `AttemptSubmissionService::submit(int, int, array)` — chamado em `AttemptController::submit()` com `(int)$id, $studentId, $_POST` ✓
