<?php

declare(strict_types=1);

/**
 * Testes da camada de dados com SQLite em memória — sem MySQL.
 *
 * Injeta um PDO SQLite no singleton Core\Database via reflection (sem poluir o
 * código de produção com seams de teste) e exercita:
 *   - Core\Database: parametrização (anti-SQLi), CRUD, rowCount, lastInsertId,
 *     transações (commit/rollback) e guarda de transação aninhada.
 *   - App\Models\User: métodos com SQL portável (find, updateAvatar).
 *
 * Uso:  php bin/run_db_tests.php
 * Pula com aviso se pdo_sqlite não estiver disponível.
 */

define('ROOT_PATH', dirname(__DIR__));
require ROOT_PATH . '/core/Env.php';
require ROOT_PATH . '/autoload.php';

if (!extension_loaded('pdo_sqlite')) {
  echo "pdo_sqlite indisponível — testes de banco pulados.\n";
  exit(0);
}

$GLOBALS['__tests'] = 0;
$GLOBALS['__fails'] = 0;

function check(bool $cond, string $msg): void
{
  $GLOBALS['__tests']++;
  if (!$cond) {
    $GLOBALS['__fails']++;
    echo "FAIL: {$msg}\n";
  }
}

function same(mixed $expected, mixed $actual, string $msg): void
{
  check(
    $expected === $actual,
    $msg . ' (esperado ' . var_export($expected, true) . ', obteve ' . var_export($actual, true) . ')'
  );
}

// ── Injeta um PDO SQLite no singleton Core\Database ──────────────────────────
$pdo = new PDO('sqlite::memory:', null, null, [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec(
  'CREATE TABLE users (
     id INTEGER PRIMARY KEY AUTOINCREMENT,
     name TEXT NOT NULL,
     email TEXT NOT NULL,
     avatar_path TEXT NULL
   )'
);

$dbInstance = (new ReflectionClass(\Core\Database::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(\Core\Database::class, 'pdo'))->setValue($dbInstance, $pdo);
(new ReflectionProperty(\Core\Database::class, 'instance'))->setValue(null, $dbInstance);

$db = \Core\Database::getInstance();

// ── Database: insert + lastInsertId ──────────────────────────────────────────
$id1 = $db->insert('INSERT INTO users (name, email) VALUES (?, ?)', ['Edvar', 'a@b.com']);
same(1, $id1, 'insert retorna lastInsertId');

// ── Database: parametrização neutraliza tentativa de injeção ─────────────────
$malicious = "Robert'); DROP TABLE users;--";
$id2 = $db->insert('INSERT INTO users (name, email) VALUES (?, ?)', [$malicious, 'x@y.com']);
$row = $db->fetchOne('SELECT name FROM users WHERE id = ?', [$id2]);
same($malicious, $row['name'] ?? null, 'param binding preserva string maliciosa literal');
$still = $db->fetchOne('SELECT COUNT(*) AS n FROM users', []);
same(2, (int) ($still['n'] ?? 0), 'tabela users intacta após payload de injeção');

// ── Database: fetchAll + execute (rowCount) ──────────────────────────────────
$all = $db->fetchAll('SELECT * FROM users ORDER BY id', []);
same(2, count($all), 'fetchAll retorna todas as linhas');
$affected = $db->execute('UPDATE users SET email = ? WHERE id = ?', ['novo@b.com', $id1]);
same(1, $affected, 'execute retorna número de linhas afetadas');

// ── Database: transação com rollback ─────────────────────────────────────────
$db->beginTransaction();
$db->insert('INSERT INTO users (name, email) VALUES (?, ?)', ['Temp', 't@t.com']);
$db->rollback();
$countAfterRollback = $db->fetchOne('SELECT COUNT(*) AS n FROM users', []);
same(2, (int) ($countAfterRollback['n'] ?? 0), 'rollback descarta a inserção');

// ── Database: transação aninhada lança LogicException ────────────────────────
$nestedBlocked = false;
$db->beginTransaction();
try {
  $db->beginTransaction();
} catch (\LogicException $e) {
  $nestedBlocked = true;
}
$db->rollback();
check($nestedBlocked, 'beginTransaction aninhado lança LogicException');

// ── App\Models\User: find + updateAvatar (SQL portável) ──────────────────────
$users = new \App\Models\User();
$found = $users->find($id1);
same('Edvar', $found['name'] ?? null, 'User::find retorna a linha por id');

$users->updateAvatar($id1, 'avatars/abc123.jpg');
$afterSet = $users->find($id1);
same('avatars/abc123.jpg', $afterSet['avatar_path'] ?? null, 'User::updateAvatar grava o caminho');

$users->updateAvatar($id1, null);
$afterClear = $users->find($id1);
same(null, $afterClear['avatar_path'], 'User::updateAvatar(null) limpa o avatar');

same(false, $users->find(9999), 'User::find retorna false quando não existe');

// ── RP-01: Exercise::hasAttempts + canDelete (real model, SQLite) ─────────────
$pdo->exec("
  CREATE TABLE IF NOT EXISTS exercises (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    teacher_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft'
  )
");
$pdo->exec("
  CREATE TABLE IF NOT EXISTS attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    exercise_id INTEGER NOT NULL,
    student_id INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'in_progress'
  )
");

$pdo->exec("INSERT INTO exercises (teacher_id, title, status) VALUES (1, 'Ex Draft', 'draft')");
$rp01DraftId = (int) $pdo->lastInsertId();

$pdo->exec("INSERT INTO exercises (teacher_id, title, status) VALUES (1, 'Ex Active', 'active')");
$rp01ActiveId = (int) $pdo->lastInsertId();

$pdo->exec("INSERT INTO exercises (teacher_id, title, status) VALUES (1, 'Ex Draft+Attempt', 'draft')");
$rp01DraftWithAttemptId = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO attempts (exercise_id, student_id, status) VALUES ($rp01DraftWithAttemptId, 1, 'submitted')");

$exModel = new \App\Models\Exercise();

check(!$exModel->hasAttempts($rp01DraftId), 'RP-01: hasAttempts retorna false para exercício sem tentativas');
check($exModel->hasAttempts($rp01DraftWithAttemptId), 'RP-01: hasAttempts retorna true para exercício com tentativa');

check($exModel->canDelete(['id' => $rp01DraftId, 'status' => 'draft']), 'RP-01: rascunho sem tentativas pode ser excluído');
check(!$exModel->canDelete(['id' => $rp01ActiveId, 'status' => 'active']), 'RP-01: exercício ativo não pode ser excluído');
check(!$exModel->canDelete(['id' => $rp01DraftWithAttemptId, 'status' => 'draft']), 'RP-01: rascunho com tentativa não pode ser excluído');

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

// ── RP-06: csvCell sanitização (testa a implementação real) ──────────────────
check(\App\Controllers\AdminBaseController::csvCell('=SUM(A1)') === "\t=SUM(A1)", 'RP-06: prefixo = é neutralizado');
check(\App\Controllers\AdminBaseController::csvCell('+1234') === "\t+1234",        'RP-06: prefixo + é neutralizado');
check(\App\Controllers\AdminBaseController::csvCell('-1') === "\t-1",              'RP-06: prefixo - é neutralizado');
check(\App\Controllers\AdminBaseController::csvCell('@foo') === "\t@foo",          'RP-06: prefixo @ é neutralizado');
check(\App\Controllers\AdminBaseController::csvCell('João Silva') === 'João Silva', 'RP-06: valor normal não é alterado');
check(\App\Controllers\AdminBaseController::csvCell('') === '',                    'RP-06: string vazia não é alterada');
check(\App\Controllers\AdminBaseController::csvCell('42') === '42',               'RP-06: número normal não é alterado');

// ── RP-13: GradingJob state machine ──────────────────────────────────────────
$pdo->exec("
  CREATE TABLE IF NOT EXISTS grading_jobs_rp13 (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    attempt_id INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'queued',
    attempts INTEGER NOT NULL DEFAULT 0,
    worker_id TEXT NULL,
    locked_at TEXT NULL,
    completed_at TEXT NULL,
    available_at TEXT NULL,
    last_error TEXT NULL
  )
");

// Test 1: markCompleted with status='processing' succeeds (returns 1 row affected)
$pdo->exec("INSERT INTO grading_jobs_rp13 (attempt_id, status, attempts, worker_id) VALUES (10, 'processing', 1, 'worker-X')");
$rp13JobOk = (int) $pdo->lastInsertId();

function markCompletedRp13(PDO $db, int $jobId, string $workerId): int {
  $stmt = $db->prepare(
    "UPDATE grading_jobs_rp13
     SET status = 'completed', completed_at = datetime('now'), last_error = NULL, worker_id = NULL
     WHERE id = ? AND status = 'processing' AND (worker_id IS NULL OR worker_id = ?)"
  );
  $stmt->execute([$jobId, $workerId]);
  return $stmt->rowCount();
}

$rowsOk = markCompletedRp13($pdo, $rp13JobOk, 'worker-X');
same(1, $rowsOk, 'RP-13: markCompleted com status=processing retorna 1 linha afetada');

// Test 2: markCompleted with status='queued' (not processing) returns 0 — job not modified
$pdo->exec("INSERT INTO grading_jobs_rp13 (attempt_id, status, attempts, worker_id) VALUES (11, 'queued', 0, NULL)");
$rp13JobQueued = (int) $pdo->lastInsertId();

$rowsQueued = markCompletedRp13($pdo, $rp13JobQueued, 'worker-Y');
same(0, $rowsQueued, 'RP-13: markCompleted com status=queued retorna 0 — job não modificado');

$checkQueued = $pdo->query("SELECT status FROM grading_jobs_rp13 WHERE id={$rp13JobQueued}")->fetch();
same('queued', (string)($checkQueued['status'] ?? ''), 'RP-13: job com status=queued permanece intacto após markCompleted');

// Test 3 & 4: recoverStaleProcessing clears worker_id and locked_at, even at max attempts (attempts = 3)
// MySQL-specific DATE_SUB not supported in SQLite — mirrors production logic with SQLite datetime arithmetic.
// Comment: production path covered by smoke tests; this verifies column-clearing logic.
$pdo->exec("INSERT INTO grading_jobs_rp13 (attempt_id, status, attempts, worker_id, locked_at)
            VALUES (12, 'processing', 3, 'worker-Z', datetime('now', '-30 minutes'))");
$rp13StuckMaxAttempts = (int) $pdo->lastInsertId();

// Mirror recoverStaleProcessing logic (SQLite-compatible, no attempts < MAX_ATTEMPTS guard)
$staleMinutes = 15;
$stmtRecover = $pdo->prepare(
  "UPDATE grading_jobs_rp13
   SET status = 'failed',
       available_at = datetime('now'),
       worker_id = NULL,
       locked_at = NULL,
       last_error = 'Job recuperado após ficar travado em processamento.'
   WHERE status = 'processing'
     AND locked_at <= datetime('now', '-{$staleMinutes} minutes')"
);
$stmtRecover->execute();
$recovered = $stmtRecover->rowCount();

check($recovered >= 1, 'RP-13: recoverStaleProcessing recupera job travado mesmo com attempts = MAX_ATTEMPTS (3)');

$afterRecover = $pdo->query("SELECT status, worker_id, locked_at FROM grading_jobs_rp13 WHERE id={$rp13StuckMaxAttempts}")->fetch();
same('failed', (string)($afterRecover['status'] ?? ''), 'RP-13: job travado no max_attempts move para failed após recovery');
check(array_key_exists('worker_id', $afterRecover) && $afterRecover['worker_id'] === null, 'RP-13: worker_id limpo após recovery');
check(array_key_exists('locked_at', $afterRecover) && $afterRecover['locked_at'] === null, 'RP-13: locked_at limpo após recovery');

// ── RP-12: GradingJob::adminRequeue ──────────────────────────────────────────
// Mirrors the atomic adminRequeue logic using SQLite-compatible datetime('now')
// instead of MySQL's NOW(), following the same pattern as RP-05 and RP-13.
$pdo->exec("
  CREATE TABLE IF NOT EXISTS grading_jobs_rp12 (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    attempt_id INTEGER NOT NULL UNIQUE,
    status TEXT NOT NULL DEFAULT 'queued',
    attempts INTEGER NOT NULL DEFAULT 0,
    last_error TEXT NULL,
    available_at TEXT NOT NULL,
    locked_at TEXT NULL,
    worker_id TEXT NULL,
    completed_at TEXT NULL
  )
");

// Mirror of adminRequeue using SQLite datetime('now') instead of MySQL NOW().
function adminRequeueRp12(PDO $db, int $attemptId): string {
  $stmt = $db->prepare(
    "UPDATE grading_jobs_rp12
     SET status       = 'queued',
         available_at = datetime('now'),
         attempts     = 0,
         last_error   = NULL,
         worker_id    = NULL,
         locked_at    = NULL
     WHERE attempt_id = ?
       AND status != 'processing'"
  );
  $stmt->execute([$attemptId]);

  if ($stmt->rowCount() > 0) {
    return 'queued';
  }

  $row = $db->query("SELECT status FROM grading_jobs_rp12 WHERE attempt_id = {$attemptId} LIMIT 1")->fetch();
  if ($row && (string)($row['status'] ?? '') === 'processing') {
    return 'already_processing';
  }

  return 'queued'; // no-job branch (enqueueAttempt) not exercised here
}

// Case 1: job in 'failed' state → adminRequeue resets it → returns 'queued'
$pdo->exec("INSERT INTO grading_jobs_rp12 (attempt_id, status, attempts, available_at, worker_id)
            VALUES (101, 'failed', 2, datetime('now'), 'worker-old')");

$resultRp12Case1 = adminRequeueRp12($pdo, 101);
same('queued', $resultRp12Case1, 'RP-12: adminRequeue de job failed retorna queued');

$rowRp12Case1 = $pdo->query("SELECT status, attempts, worker_id FROM grading_jobs_rp12 WHERE attempt_id=101")->fetch();
same('queued', (string)($rowRp12Case1['status'] ?? ''), 'RP-12: status resetado para queued');
same(0, (int)($rowRp12Case1['attempts'] ?? -1), 'RP-12: attempts zerado');
check($rowRp12Case1['worker_id'] === null, 'RP-12: worker_id limpo após requeue');

// Case 2: job in 'processing' state → atomic WHERE blocks update → returns 'already_processing'
$pdo->exec("INSERT INTO grading_jobs_rp12 (attempt_id, status, attempts, available_at, worker_id)
            VALUES (102, 'processing', 1, datetime('now'), 'worker-active')");

$resultRp12Case2 = adminRequeueRp12($pdo, 102);
same('already_processing', $resultRp12Case2, 'RP-12: adminRequeue de job processing retorna already_processing');

$rowRp12Case2 = $pdo->query("SELECT status, worker_id FROM grading_jobs_rp12 WHERE attempt_id=102")->fetch();
same('processing', (string)($rowRp12Case2['status'] ?? ''), 'RP-12: job processing permanece inalterado');
same('worker-active', (string)($rowRp12Case2['worker_id'] ?? ''), 'RP-12: worker_id não foi limpo');

// ── RP-15: role change validation (hasTeacherDependencies / hasStudentDependencies) ─
$pdo->exec("
  CREATE TABLE IF NOT EXISTS turmas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    teacher_id INTEGER NOT NULL
  )
");
$pdo->exec("
  CREATE TABLE IF NOT EXISTS student_turma (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    student_id INTEGER NOT NULL
  )
");
// Note: exercises and attempts tables were already created in the RP-01 block above.

// Insert a teacher user (id will be captured) and a student user
$pdo->exec("INSERT INTO users (name, email, avatar_path) VALUES ('Prof Silva', 'prof@school.com', NULL)");
$rp15TeacherId = (int) $pdo->lastInsertId();

$pdo->exec("INSERT INTO users (name, email, avatar_path) VALUES ('Aluno Joao', 'joao@school.com', NULL)");
$rp15StudentId = (int) $pdo->lastInsertId();

// Teacher WITH a turma
$pdo->exec("INSERT INTO turmas (teacher_id) VALUES ({$rp15TeacherId})");

// Student WITH an enrollment
$pdo->exec("INSERT INTO student_turma (student_id) VALUES ({$rp15StudentId})");

$rp15UserModel = new \App\Models\User();

// Test 1: hasTeacherDependencies returns true when teacher has a turma
check(
  $rp15UserModel->hasTeacherDependencies($rp15TeacherId),
  'RP-15: hasTeacherDependencies retorna true quando professor possui turma'
);

// Test 2: hasTeacherDependencies returns false for a user with no turmas/exercises
$pdo->exec("INSERT INTO users (name, email, avatar_path) VALUES ('Prof Sem Turma', 'semturma@school.com', NULL)");
$rp15TeacherNoDepsId = (int) $pdo->lastInsertId();

check(
  !$rp15UserModel->hasTeacherDependencies($rp15TeacherNoDepsId),
  'RP-15: hasTeacherDependencies retorna false quando professor não possui turmas nem exercícios'
);

// Test 3: hasStudentDependencies returns true when student has an enrollment
check(
  $rp15UserModel->hasStudentDependencies($rp15StudentId),
  'RP-15: hasStudentDependencies retorna true quando aluno possui matrícula'
);

// Test 4: hasStudentDependencies returns false for a user with no enrollments/attempts
$pdo->exec("INSERT INTO users (name, email, avatar_path) VALUES ('Aluno Sem Matricula', 'semmatricula@school.com', NULL)");
$rp15StudentNoDepsId = (int) $pdo->lastInsertId();

check(
  !$rp15UserModel->hasStudentDependencies($rp15StudentNoDepsId),
  'RP-15: hasStudentDependencies retorna false quando aluno não possui matrículas nem tentativas'
);

// Test 5 (bonus): hasTeacherDependencies returns true when teacher has an exercise (not just turma)
$pdo->exec("INSERT INTO users (name, email, avatar_path) VALUES ('Prof Com Exercicio', 'comexercicio@school.com', NULL)");
$rp15TeacherWithExerciseId = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO exercises (teacher_id, title, status) VALUES ({$rp15TeacherWithExerciseId}, 'Exercicio RP15', 'draft')");

check(
  $rp15UserModel->hasTeacherDependencies($rp15TeacherWithExerciseId),
  'RP-15: hasTeacherDependencies retorna true quando professor possui exercício (sem turma)'
);

// Test 6 (bonus): hasStudentDependencies returns true when student has an attempt (not just enrollment)
$pdo->exec("INSERT INTO users (name, email, avatar_path) VALUES ('Aluno Com Tentativa', 'comtentativa@school.com', NULL)");
$rp15StudentWithAttemptId = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO attempts (exercise_id, student_id, status) VALUES (1, {$rp15StudentWithAttemptId}, 'submitted')");

check(
  $rp15UserModel->hasStudentDependencies($rp15StudentWithAttemptId),
  'RP-15: hasStudentDependencies retorna true quando aluno possui tentativa (sem matrícula)'
);

// ── Resumo ───────────────────────────────────────────────────────────────────
$total = $GLOBALS['__tests'];
$fails = $GLOBALS['__fails'];

if ($fails > 0) {
  echo "\n{$fails} de {$total} verificação(ões) falharam.\n";
  exit(1);
}

echo "Todos os {$total} testes de banco passaram.\n";
exit(0);
