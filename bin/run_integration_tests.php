<?php

declare(strict_types=1);

/**
 * Testes de integração reais contra MySQL — AP-08 / RP-07.
 *
 * Diferente de bin/run_db_tests.php (SQLite em memória, SQL replicado à mão,
 * uma única conexão), este script:
 *   - conecta a um MySQL de verdade e aplica o schema consolidado
 *     (001_create_tables.sql);
 *   - roda os SERVIÇOS/MODELS REAIS (AttemptSubmissionService, GradingJob,
 *     User) contra esse banco, não uma reprodução do SQL em outro motor;
 *   - para os pontos cuja correção foi um lock (`FOR UPDATE`), abre DUAS
 *     conexões MySQL de verdade e prova que a segunda trava/perde a corrida
 *     enquanto a primeira segura a transação — não só que o resultado final
 *     bate.
 *
 * Cobre: AP-01/AP-02 (lock da tentativa no submit), AP-03 (lock do job na
 * fila), AP-05 (consumo atômico do token de reset), AP-06 (lock do último
 * admin), AP-07 (INSERT concorrente com e-mail duplicado). HTTP smoke
 * (headers, redirect de rota protegida, CSRF) via `php -S` servindo public/
 * de verdade, e autorização negativa autenticada (RP-23-03): login pelo fluxo
 * real como Docentes, Alunos e Admin, cobrindo IDOR, papel errado, Turma
 * inativa e a Auditoria de Recusar/Desvincular.
 *
 * SEGURANÇA: só roda contra um banco cujo nome contenha "test"/"ci", ou com
 * INTEGRATION_TESTS_CONFIRM=1 explícito — o script TRUNCA todas as tabelas
 * no início. Nunca aponte para um banco de produção.
 *
 * Uso: php bin/run_integration_tests.php
 * Pula com aviso (exit 0) se pdo_mysql não estiver disponível, o MySQL não
 * responder, ou o banco não parecer um banco de teste — não é um requisito
 * duro para ambientes sem MySQL (ex.: dev local sem docker).
 */

define('ROOT_PATH', dirname(__DIR__));
require ROOT_PATH . '/core/Env.php';
(new Core\Env(ROOT_PATH . '/.env'))->load();
require ROOT_PATH . '/autoload.php';

if (!extension_loaded('pdo_mysql')) {
  echo "pdo_mysql indisponível — testes de integração pulados.\n";
  exit(0);
}

$cfg = require ROOT_PATH . '/config/database.php';

$dbName    = (string) $cfg['database'];
$confirmed = (string) \Core\env('INTEGRATION_TESTS_CONFIRM', '') === '1';

if (!$confirmed && !preg_match('/test|ci/i', $dbName)) {
  fwrite(
    STDERR,
    "Recusado: DB_DATABASE ('{$dbName}') não parece um banco de teste.\n" .
      "Defina INTEGRATION_TESTS_CONFIRM=1 para confirmar explicitamente — " .
      "este script TRUNCA todas as tabelas do banco configurado.\n"
  );
  exit(0);
}

function newRawConnection(array $cfg): PDO
{
  $dsn = "mysql:host={$cfg['host']};dbname={$cfg['database']};charset=utf8mb4";
  return new PDO($dsn, $cfg['username'], $cfg['password'], [
    PDO::ATTR_ERRMODE               => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE    => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES      => false,
    // Os testes de lock abaixo usam ->query()->fetch() diretamente (fora
    // dos helpers de Core\Database); sem query buffered, um cursor aberto
    // e não totalmente consumido quebra a próxima chamada na mesma conexão.
    // Pdo\Mysql::ATTR_* existe desde o PHP 8.4; as constantes MYSQL_ATTR_* da classe PDO são deprecated no 8.5.
    (defined('Pdo\Mysql::ATTR_USE_BUFFERED_QUERY')
      ? constant('Pdo\Mysql::ATTR_USE_BUFFERED_QUERY')
      : constant('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) => true,
  ]);
}

/** Envolve um PDO num Core\Database real via reflection (mesma técnica de bin/run_db_tests.php). */
function wrapAsDatabase(PDO $pdo): \Core\Database
{
  $ref = new ReflectionClass(\Core\Database::class);
  $db  = $ref->newInstanceWithoutConstructor();
  (new ReflectionProperty(\Core\Database::class, 'pdo'))->setValue($db, $pdo);
  return $db;
}

try {
  $pdoA = newRawConnection($cfg);
} catch (\Throwable $e) {
  echo 'MySQL indisponível (' . $e->getMessage() . ") — testes de integração pulados.\n";
  exit(0);
}

$pdoB = newRawConnection($cfg);

$dbA = wrapAsDatabase($pdoA);
$dbB = wrapAsDatabase($pdoB);

// `new Model()` sem argumento (código de produção) cai no singleton — aponta
// para a mesma conexão A, para que os helpers de seed abaixo (que usam
// `new User()`/`new Question()` sem DI explícita) participem da mesma sessão.
(new ReflectionProperty(\Core\Database::class, 'instance'))->setValue(null, $dbA);

// ── Schema ────────────────────────────────────────────────────────────────
/**
 * Aplica um .sql via cliente `mysql` (CLI), não via loop de PDO::exec().
 *
 * As migrations incrementais usam SQL dinâmico
 * (PREPARE/EXECUTE/DEALLOCATE) para ser idempotente. Rodar isso através de
 * PDO — dividindo o arquivo em instruções e chamando exec() uma a uma —
 * quebra com "Cannot execute queries while other unbuffered queries are
 * active" logo após o EXECUTE (limitação do driver PDO_MYSQL/mysqlnd com SQL
 * dinâmico, não um bug no SQL em si). O cliente `mysql` lida com isso nativamente
 * — e é como os próprios arquivos já orientam serem aplicados (ver cabeçalho
 * de 001_create_tables.sql: "via phpMyAdmin ou CLI").
 */
function applySchema(array $cfg, string $path): void
{
  if (@shell_exec('command -v mysql') === null) {
    throw new \RuntimeException('Cliente `mysql` não encontrado no PATH — necessário para aplicar o schema.');
  }

  $passwordSet = $cfg['password'] !== '';
  if ($passwordSet) {
    putenv('MYSQL_PWD=' . $cfg['password']);
  }

  $cmd = sprintf(
    'mysql --host=%s --user=%s --database=%s < %s 2>&1',
    escapeshellarg($cfg['host']),
    escapeshellarg($cfg['username']),
    escapeshellarg($cfg['database']),
    escapeshellarg($path)
  );

  exec($cmd, $outputLines, $exitCode);

  if ($passwordSet) {
    putenv('MYSQL_PWD');
  }

  if ($exitCode !== 0) {
    throw new \RuntimeException("Falha ao aplicar {$path} via CLI:\n" . implode("\n", $outputLines));
  }
}

try {
  applySchema($cfg, ROOT_PATH . '/database/migrations/001_create_tables.sql');
  // Migrations incrementais posteriores à linha de base (023+), em ordem.
  foreach (glob(ROOT_PATH . '/database/migrations/*.sql') ?: [] as $migration) {
    if (basename($migration) !== '001_create_tables.sql') {
      applySchema($cfg, $migration);
    }
  }
} catch (\Throwable $e) {
  echo 'Não foi possível aplicar o schema (' . $e->getMessage() . ") — testes de integração pulados.\n";
  exit(0);
}

$allTables = [
  'ai_usage_logs',
  'student_achievements',
  'turma_posts',
  'practice_answers',
  'notifications',
  'score_reviews',
  'bank_exercise_themes',
  'bank_themes',
  'bank_questions',
  'bank_exercises',
  'injection_logs',
  'answers',
  'grading_jobs',
  'attempts',
  'questions',
  'exercise_turmas',
  'exercises',
  'student_turma',
  'turmas',
  'login_attempts',
  'system_settings',
  'audit_logs',
  'users',
];

$pdoA->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($allTables as $t) {
  $pdoA->exec("TRUNCATE TABLE {$t}");
}
$pdoA->exec('SET FOREIGN_KEY_CHECKS = 1');

// ── Helpers de verificação ────────────────────────────────────────────────
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

// ── Seed base: docente, turma, aluno matriculado, exercício publicado ──────
function seedScenario(PDO $pdo): array
{
  $now = new DateTimeImmutable('now');

  $hash = password_hash('x', PASSWORD_BCRYPT);
  $suffix = bin2hex(random_bytes(4)); // e-mails únicos entre seeds repetidos no mesmo run

  $pdo->exec("INSERT INTO users (name, email, password_hash, role, status) VALUES ('Docente IT', 'docente.{$suffix}@test.local', '{$hash}', 'teacher', 'active')");
  $teacherId = (int) $pdo->lastInsertId();

  $pdo->exec("INSERT INTO users (name, email, password_hash, role, status) VALUES ('Aluno IT', 'aluno.{$suffix}@test.local', '{$hash}', 'student', 'active')");
  $studentId = (int) $pdo->lastInsertId();

  $accessKey = strtoupper(substr($suffix, 0, 6));
  $pdo->exec("INSERT INTO turmas (teacher_id, name, access_key) VALUES ({$teacherId}, 'Turma IT', '{$accessKey}')");
  $turmaId = (int) $pdo->lastInsertId();

  $pdo->exec("INSERT INTO student_turma (student_id, turma_id, status) VALUES ({$studentId}, {$turmaId}, 'active')");

  $pdo->exec("INSERT INTO exercises (teacher_id, title, status) VALUES ({$teacherId}, 'Exercício IT', 'active')");
  $exerciseId = (int) $pdo->lastInsertId();

  $opensAt  = $now->modify('-1 hour')->format('Y-m-d H:i:s');
  $closesAt = $now->modify('+1 hour')->format('Y-m-d H:i:s');
  $pdo->exec("INSERT INTO exercise_turmas (exercise_id, turma_id, opens_at, closes_at, max_attempts) VALUES ({$exerciseId}, {$turmaId}, '{$opensAt}', '{$closesAt}', 0)");

  $pdo->exec("INSERT INTO questions (exercise_id, text, expected_answer_hint, max_score, order_index) VALUES ({$exerciseId}, 'Explique recursão.', 'Caso base + chamada menor.', 10.0, 1)");
  $questionId = (int) $pdo->lastInsertId();

  $pdo->exec("INSERT INTO attempts (exercise_id, student_id, turma_id, status) VALUES ({$exerciseId}, {$studentId}, {$turmaId}, 'in_progress')");
  $attemptId = (int) $pdo->lastInsertId();

  return compact('teacherId', 'studentId', 'turmaId', 'exerciseId', 'questionId', 'attemptId');
}

// ═══════════════════════════════════════════════════════════════════════════
// AP-05 — consumo atômico do token de redefinição de senha
// ═══════════════════════════════════════════════════════════════════════════

$users = new App\Models\User(); // conexão A (singleton)
$resetUserId = $users->create('Aluno Reset', 'reset.it@test.local', 'SenhaAntiga1', 'student', 'active');
$token = bin2hex(random_bytes(16));
$users->createPasswordResetToken($resetUserId, $token, 60);

$first = $users->consumePasswordResetToken($resetUserId, $token, 'SenhaNovaUm1');
check($first === true, 'AP-05: primeiro consumo do token de reset tem sucesso (MySQL real)');

$second = $users->consumePasswordResetToken($resetUserId, $token, 'SenhaNovaDois2');
check($second === false, 'AP-05: segundo consumo do MESMO token falha — já foi limpo pelo primeiro');

$resetRow = $users->find($resetUserId);
check(
  password_verify('SenhaNovaUm1', (string) $resetRow['password_hash']),
  'AP-05: senha final é a do primeiro consumo, não do segundo'
);

// ═══════════════════════════════════════════════════════════════════════════
// AP-06 — lock do último admin com DUAS conexões reais
// ═══════════════════════════════════════════════════════════════════════════

$adminId = $users->create('Admin Único IT', 'admin.it@test.local', 'SenhaAdmin1', 'admin', 'active');

$usersA = new App\Models\User($dbA);
$usersB = new App\Models\User($dbB);

$dbA->beginTransaction();
$lockedCount = $usersA->countActiveAdminsForUpdate(); // SELECT ... FOR UPDATE — segura a linha
check($lockedCount === 1, 'AP-06: contagem de admin ativo sob lock é 1');

$pdoB->exec('SET SESSION innodb_lock_wait_timeout = 1');
$blockedByLock = false;
try {
  $pdoB->exec("UPDATE users SET status = 'inactive' WHERE id = {$adminId} AND role = 'admin'");
} catch (\PDOException $e) {
  $blockedByLock = ((int) ($e->errorInfo[1] ?? 0)) === 1205; // lock wait timeout
}
check($blockedByLock, 'AP-06: UPDATE concorrente na linha do admin trava até a conexão A liberar (FOR UPDATE real)');

$dbA->commit();

$pdoB->exec("UPDATE users SET status = 'active' WHERE id = {$adminId} AND role = 'admin'");
check(true, 'AP-06: após o commit da conexão A, a conexão B consegue escrever na mesma linha');

// ═══════════════════════════════════════════════════════════════════════════
// AP-07 — cadastro público concorrente com o mesmo e-mail, DUAS conexões reais
//
// InnoDB coloca lock exclusivo na entrada do índice único assim que o INSERT
// não commitado é executado — uma segunda transação inserindo o mesmo valor
// único BLOQUEIA (não erra na hora) até a primeira resolver. Só depois do
// commit da primeira é que a segunda recebe o erro de chave duplicada real
// (SQLSTATE 23000) que AuthController::register()/registerTeacher() já
// capturam e convertem em erro de formulário.
// ═══════════════════════════════════════════════════════════════════════════

$raceEmail = 'race.' . bin2hex(random_bytes(4)) . '@test.local';

$dbA->beginTransaction();
$usersA->create('Corrida A', $raceEmail, 'SenhaA1234', 'student', 'active'); // INSERT não commitado

$pdoB->exec('SET SESSION innodb_lock_wait_timeout = 1');
$raceBlockedByLock = false;
try {
  $usersB->create('Corrida B', $raceEmail, 'SenhaB1234', 'student', 'active');
} catch (\PDOException $e) {
  $raceBlockedByLock = ((int) ($e->errorInfo[1] ?? 0)) === 1205; // lock wait timeout
}
check($raceBlockedByLock, 'AP-07: INSERT concorrente com o mesmo e-mail trava até a conexão A commitar (lock real do índice único)');

$dbA->commit();

$raceDuplicateRejected = false;
try {
  $usersB->create('Corrida B', $raceEmail, 'SenhaB1234', 'student', 'active');
} catch (\PDOException $e) {
  $raceDuplicateRejected = $e->getCode() === '23000';
}
check($raceDuplicateRejected, 'AP-07: após o commit da conexão A, a conexão B recebe SQLSTATE 23000 (o mesmo erro que o controller captura)');

$raceRows = $pdoA->query("SELECT COUNT(*) AS total FROM users WHERE email = '{$raceEmail}'")->fetch();
check((int) $raceRows['total'] === 1, 'AP-07: apenas um usuário foi de fato criado com o e-mail disputado');

// ═══════════════════════════════════════════════════════════════════════════
// AP-03 — lock do job de correção (claimNext) com DUAS conexões reais
// ═══════════════════════════════════════════════════════════════════════════

$scenario = seedScenario($pdoA);
$jobsA = new App\Models\GradingJob($dbA);
$jobsB = new App\Models\GradingJob($dbB);

$jobsA->enqueueAttempt($scenario['attemptId']);
$jobRow = $pdoA->query("SELECT id FROM grading_jobs WHERE attempt_id = {$scenario['attemptId']}")->fetch();
$jobId  = (int) $jobRow['id'];

$dbA->beginTransaction();
$pdoA->query("SELECT * FROM grading_jobs WHERE id = {$jobId} FOR UPDATE")->fetch(); // segura a linha

$pdoB->exec('SET SESSION innodb_lock_wait_timeout = 1');
$claimBlocked = false;
try {
  $jobsB->claimNext('worker-b');
} catch (\PDOException $e) {
  $claimBlocked = ((int) ($e->errorInfo[1] ?? 0)) === 1205;
}
check($claimBlocked, 'AP-03: claimNext em outra conexão trava enquanto a linha do job está sob lock');

$dbA->commit();

$claimed = $jobsB->claimNext('worker-b');
check($claimed !== false && (string) $claimed['status'] === 'processing', 'AP-03: após liberar o lock, claimNext (conexão B) reivindica o job');

$jobsA->markCompleted($jobId, 'worker-errado'); // não lança; só não afeta linha nenhuma (ownership estrito)
$afterWrongOwner = $pdoA->query("SELECT status, worker_id FROM grading_jobs WHERE id = {$jobId}")->fetch();
check(
  (string) $afterWrongOwner['status'] === 'processing' && (string) $afterWrongOwner['worker_id'] === 'worker-b',
  'AP-03: markCompleted com worker_id errado não altera o job (ownership estrito)'
);

$jobsA->markCompleted($jobId, 'worker-b');
$afterRightOwner = $pdoA->query("SELECT status FROM grading_jobs WHERE id = {$jobId}")->fetch();
check((string) $afterRightOwner['status'] === 'completed', 'AP-03: markCompleted com worker_id correto conclui o job');

// ═══════════════════════════════════════════════════════════════════════════
// AP-01/AP-02 — lock da tentativa no submit, com DUAS conexões reais
// ═══════════════════════════════════════════════════════════════════════════

$scenario2 = seedScenario($pdoA);
$attemptId2 = $scenario2['attemptId'];

$dbA->beginTransaction();
$pdoA->query("SELECT * FROM attempts WHERE id = {$attemptId2} FOR UPDATE")->fetch();

$pdoB->exec('SET SESSION innodb_lock_wait_timeout = 1');
$submitBlocked = false;
try {
  (new App\Services\AttemptSubmissionService($dbB))->submit($attemptId2, $scenario2['studentId'], []);
} catch (\PDOException $e) {
  $submitBlocked = ((int) ($e->errorInfo[1] ?? 0)) === 1205;
}
check($submitBlocked, 'AP-01/AP-02: submit em outra conexão trava enquanto a tentativa está sob lock (FOR UPDATE real)');

$dbA->commit();

$result = (new App\Services\AttemptSubmissionService($dbB))->submit($attemptId2, $scenario2['studentId'], []);
check($result === 'submitted', 'AP-01/AP-02: após liberar o lock, submit (conexão B) é aceito e roda contra MySQL real');

$attemptAfter = $pdoA->query("SELECT status FROM attempts WHERE id = {$attemptId2}")->fetch();
check((string) $attemptAfter['status'] === 'submitted', 'AP-01/AP-02: tentativa fica submitted no MySQL real após o submit');

$jobAfterSubmit = $pdoA->query("SELECT status FROM grading_jobs WHERE attempt_id = {$attemptId2}")->fetch();
check($jobAfterSubmit !== false && (string) $jobAfterSubmit['status'] === 'queued', 'AP-01/AP-02: job de correção foi enfileirado na mesma transação do submit');

// Moderação: questão bloqueada no meio do caminho impede o submit (AP-02).
$scenario3 = seedScenario($pdoA);
$pdoA->exec("UPDATE questions SET admin_review_status = 'blocked' WHERE exercise_id = {$scenario3['exerciseId']}");

$blockedByModeration = false;
try {
  (new App\Services\AttemptSubmissionService($dbA))->submit($scenario3['attemptId'], $scenario3['studentId'], []);
} catch (\RuntimeException $e) {
  $blockedByModeration = true;
}
check($blockedByModeration, 'AP-02: submit é rejeitado quando a questão do exercício está bloqueada (revalidado contra MySQL real)');

$attempt3After = $pdoA->query("SELECT status FROM attempts WHERE id = {$scenario3['attemptId']}")->fetch();
check((string) $attempt3After['status'] === 'in_progress', 'AP-02: tentativa permanece in_progress após rejeição por moderação (rollback real)');

// ═══════════════════════════════════════════════════════════════════════════
// RP-23 — turma inativa bloqueia acesso do aluno e operações docentes
// ═══════════════════════════════════════════════════════════════════════════

$inactiveScenario = seedScenario($pdoA);
$pdoA->exec("UPDATE turmas SET active = 0 WHERE id = {$inactiveScenario['turmaId']}");

$inactiveExercises = new App\Models\Exercise($dbA);
check(
  $inactiveExercises->findAllForStudent($inactiveScenario['studentId']) === [],
  'RP-23-01: turma inativa não aparece na lista de exercícios do aluno'
);
check(
  $inactiveExercises->findForStudent($inactiveScenario['exerciseId'], $inactiveScenario['studentId']) === false,
  'RP-23-01: detalhe do exercício de turma inativa fica indisponível'
);
check(
  !$inactiveExercises->studentHasAccess($inactiveScenario['exerciseId'], $inactiveScenario['studentId']),
  'RP-23-01: studentHasAccess rejeita turma inativa'
);
check(
  $inactiveExercises->findOpenPublicationForStudent($inactiveScenario['exerciseId'], $inactiveScenario['studentId']) === false,
  'RP-23-01: publicação aberta de turma inativa não é retornada'
);

$inactiveStartRejected = false;
try {
  (new App\Services\AttemptStartService($dbA))->start(
    $inactiveScenario['studentId'],
    $inactiveScenario['exerciseId'],
    $inactiveScenario['turmaId']
  );
} catch (\RuntimeException $e) {
  $inactiveStartRejected = true;
}
check($inactiveStartRejected, 'RP-23-01: início de tentativa é rejeitado para turma inativa');

$inactiveSubmitRejected = false;
try {
  (new App\Services\AttemptSubmissionService($dbA))->submit(
    $inactiveScenario['attemptId'],
    $inactiveScenario['studentId'],
    []
  );
} catch (\RuntimeException $e) {
  $inactiveSubmitRejected = true;
}
check($inactiveSubmitRejected, 'RP-23-01: envio de tentativa é rejeitado para turma inativa');

$inactiveAttempt = $pdoA->query("SELECT status FROM attempts WHERE id = {$inactiveScenario['attemptId']}")->fetch();
check(
  (string) ($inactiveAttempt['status'] ?? '') === 'in_progress',
  'RP-23-01: rejeição por turma inativa preserva a tentativa em andamento'
);

$inactiveTurmas = new App\Models\Turma($dbA);
check(
  !$inactiveTurmas->belongsToTeacher($inactiveScenario['turmaId'], $inactiveScenario['teacherId']),
  'RP-23-02: turma inativa não é válida para mutação docente'
);

$inactiveExerciseId = $inactiveExercises->createDraft($inactiveScenario['teacherId'], 'Exercício inativo', 'Teste');
$pdoA->exec("UPDATE exercises SET status = 'ready' WHERE id = {$inactiveExerciseId}");
$pdoA->exec("INSERT INTO questions (exercise_id, text, expected_answer_hint, max_score, order_index)
             VALUES ({$inactiveExerciseId}, 'Questão inativa', 'Resposta esperada', 10.0, 1)");

$inactivePublicationRejected = false;
try {
  $inactiveExercises->activate($inactiveExerciseId, [
    $inactiveScenario['turmaId'] => [
      'opens_at' => date('Y-m-d H:i:s', time() - 3600),
      'closes_at' => date('Y-m-d H:i:s', time() + 3600),
      'max_attempts' => 1,
    ],
  ]);
} catch (\RuntimeException $e) {
  $inactivePublicationRejected = true;
}
check($inactivePublicationRejected, 'RP-23-02: publicação é rejeitada quando a turma está inativa');

$inactiveExercise = $pdoA->query("SELECT status FROM exercises WHERE id = {$inactiveExerciseId}")->fetch();
check(
  (string) ($inactiveExercise['status'] ?? '') === 'ready',
  'RP-23-02: exercício permanece pronto após publicação rejeitada'
);

// ═══════════════════════════════════════════════════════════════════════════
// RD-01 — Recusar vale só para Matrícula pendente
// ═══════════════════════════════════════════════════════════════════════════

$rejectScenario = seedScenario($pdoA); // Matrícula ativa
$rejectTurmas = new App\Models\Turma($dbA);

check(
  $rejectTurmas->rejectStudent($rejectScenario['studentId'], $rejectScenario['turmaId']) === false,
  'RD-01: recusar Matrícula ativa informa que nada foi recusado'
);
check(
  count($rejectTurmas->getActiveStudents($rejectScenario['turmaId'])) === 1,
  'RD-01: Matrícula ativa continua ativa após tentativa de recusa'
);

$pendingStudentId = $users->create('Aluno Pendente', 'pendente.' . bin2hex(random_bytes(4)) . '@test.local', 'SenhaForte1', 'student', 'pending');
$pdoA->exec("INSERT INTO student_turma (student_id, turma_id, status) VALUES ({$pendingStudentId}, {$rejectScenario['turmaId']}, 'pending')");
check(
  $rejectTurmas->rejectStudent($pendingStudentId, $rejectScenario['turmaId']) === true,
  'RD-01: recusar Matrícula pendente informa sucesso'
);
check(
  $rejectTurmas->getPendingStudents($rejectScenario['turmaId']) === [],
  'RD-01: Matrícula pendente recusada deixa de existir'
);
check(
  $rejectTurmas->rejectStudent($pendingStudentId, $rejectScenario['turmaId']) === false,
  'RD-01: recusar Matrícula inexistente informa que nada foi recusado'
);

$detachPendingId = $users->create('Aluno Pendente 2', 'pendente2.' . bin2hex(random_bytes(4)) . '@test.local', 'SenhaForte1', 'student', 'pending');
$pdoA->exec("INSERT INTO student_turma (student_id, turma_id, status) VALUES ({$detachPendingId}, {$rejectScenario['turmaId']}, 'pending')");
check(
  $rejectTurmas->detachStudent($detachPendingId, $rejectScenario['turmaId']) === false,
  'RD-01: desvincular da turma não remove Matrícula pendente'
);
check(
  count($rejectTurmas->getPendingStudents($rejectScenario['turmaId'])) === 1,
  'RD-01: Matrícula pendente continua pendente após tentativa de desvínculo'
);
check(
  $rejectTurmas->detachStudent($rejectScenario['studentId'], $rejectScenario['turmaId']) === true,
  'RD-01: desvincular da turma remove Matrícula ativa'
);
check(
  $rejectTurmas->getActiveStudents($rejectScenario['turmaId']) === [],
  'RD-01: Aluno desvinculado deixa de ser ativo na turma'
);

// ═══════════════════════════════════════════════════════════════════════════
// RD-02/RD-03 — Tentativa sempre no contexto de uma Publicação
// ═══════════════════════════════════════════════════════════════════════════

$pubScenario = seedScenario($pdoA);
$pubExercises = new App\Models\Exercise($dbA);
$pubExerciseRow = $pdoA->query("SELECT * FROM exercises WHERE id = {$pubScenario['exerciseId']}")->fetch();
$noPublication = $pubExercises->applyPublicationContext($pubExerciseRow, false);
check(
  $noPublication['turma_id'] === null
    && $noPublication['opens_at'] === null
    && $noPublication['closes_at'] === null
    && $noPublication['max_attempts'] === null,
  'RD-02: Exercício sem Publicação não herda turma, janela nem limite'
);
check(
  (int) $noPublication['has_open_publication'] === 0 && (int) $noPublication['has_future_publication'] === 0,
  'RD-02: Exercício sem Publicação não tem Publicação aberta nem futura'
);

// Mesmo Exercício publicado em duas Turmas do mesmo Aluno; a Tentativa do seed é da Turma A.
$pdoA->exec("UPDATE attempts SET status = 'graded', total_score = 8.0, submitted_at = NOW() WHERE id = {$pubScenario['attemptId']}");
$pdoA->exec("INSERT INTO turmas (teacher_id, name, access_key) VALUES ({$pubScenario['teacherId']}, 'Turma IT B', '" . strtoupper(bin2hex(random_bytes(3))) . "')");
$turmaBId = (int) $pdoA->lastInsertId();
$pdoA->exec("INSERT INTO student_turma (student_id, turma_id, status) VALUES ({$pubScenario['studentId']}, {$turmaBId}, 'active')");
$pdoA->exec("INSERT INTO exercise_turmas (exercise_id, turma_id, opens_at, closes_at, max_attempts)
             SELECT exercise_id, {$turmaBId}, opens_at, closes_at, max_attempts FROM exercise_turmas
             WHERE exercise_id = {$pubScenario['exerciseId']} AND turma_id = {$pubScenario['turmaId']}");

$pubAttempts = new App\Models\Attempt($dbA);
[$sId, $eId, $tA] = [$pubScenario['studentId'], $pubScenario['exerciseId'], $pubScenario['turmaId']];
check(
  $pubAttempts->countUsedAttempts($sId, $eId, $tA) === 1 && $pubAttempts->countUsedAttempts($sId, $eId, $turmaBId) === 0,
  'RD-03: Tentativas usadas são contadas por Publicação'
);
check(
  $pubAttempts->getBestScore($sId, $eId, $tA) === 8.0 && $pubAttempts->getBestScore($sId, $eId, $turmaBId) === null,
  'RD-03: melhor Pontuação é calculada por Publicação'
);
$pdoA->exec("INSERT INTO attempts (exercise_id, student_id, turma_id, status) VALUES ({$eId}, {$sId}, {$tA}, 'in_progress')");
check(
  $pubAttempts->getInProgress($sId, $eId, $turmaBId) === false && $pubAttempts->getInProgress($sId, $eId, $tA) !== false,
  'RD-03: Tentativa em andamento só é retomada na mesma Publicação'
);

$nullTurmaRejected = false;
try {
  $pubAttempts->countUsedAttempts($sId, $eId, null);
} catch (\TypeError $e) {
  $nullTurmaRejected = true;
}
check($nullTurmaRejected, 'RD-03: consulta de Tentativa sem Turma é rejeitada');

// ═══════════════════════════════════════════════════════════════════════════
// Smoke HTTP — servidor embutido do PHP servindo public/ de verdade
// ═══════════════════════════════════════════════════════════════════════════

function httpGet(string $url, bool $followRedirects = false): array
{
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => true,
    CURLOPT_FOLLOWLOCATION => $followRedirects,
    CURLOPT_TIMEOUT        => 5,
  ]);
  $raw = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

  if ($raw === false) {
    return ['code' => 0, 'headers' => '', 'body' => ''];
  }

  return [
    'code'    => $code,
    'headers' => substr((string) $raw, 0, $headerSize),
    'body'    => substr((string) $raw, $headerSize),
  ];
}

/**
 * Requisição HTTP com o cookie jar de um ator (sessão real do app).
 * Retorna código, headers, corpo e destino do redirect, sem segui-lo.
 */
function httpAs(string $jar, string $method, string $url, array $fields = []): array
{
  $ch = curl_init($url);
  $opts = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_COOKIEJAR      => $jar,
    CURLOPT_COOKIEFILE     => $jar,
  ];
  if ($method === 'POST') {
    $opts[CURLOPT_POST] = true;
    $opts[CURLOPT_POSTFIELDS] = http_build_query($fields);
  }
  curl_setopt_array($ch, $opts);
  $raw = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  $location = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);

  if ($raw === false) {
    return ['code' => 0, 'headers' => '', 'body' => '', 'location' => ''];
  }

  return [
    'code'     => $code,
    'headers'  => substr((string) $raw, 0, $headerSize),
    'body'     => substr((string) $raw, $headerSize),
    'location' => $location,
  ];
}

function extractCsrf(string $html): string
{
  return preg_match('/name="_csrf_token" value="([^"]+)"/', $html, $m) ? html_entity_decode($m[1]) : '';
}

/** Faz login pelo fluxo real (GET /login → token CSRF → POST) e devolve o cookie jar do ator. */
function loginAs(string $base, string $email, string $password): string
{
  $jar = tempnam(sys_get_temp_dir(), 'algoia_jar_');
  $page = httpAs($jar, 'GET', "{$base}/login");
  httpAs($jar, 'POST', "{$base}/login", [
    '_csrf_token' => extractCsrf($page['body']),
    'email'       => $email,
    'password'    => $password,
  ]);
  return $jar;
}

/** POST autenticado com o token CSRF da sessão do ator (lido da página de conta). */
function postAs(string $base, string $jar, string $path, array $fields = []): array
{
  $page = httpAs($jar, 'GET', "{$base}/conta");
  return httpAs($jar, 'POST', "{$base}{$path}", ['_csrf_token' => extractCsrf($page['body'])] + $fields);
}

if (!extension_loaded('curl')) {
  echo "curl indisponível — smoke HTTP pulado.\n";
} else {
  $port = 18080 + random_int(0, 500);
  $host = "127.0.0.1:{$port}";

  $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

  // IA falsa local: a Correção na hora da prática fala com ela, nunca com a OpenAI.
  $fakeAiPort = $port + 1000;
  $fakeAiLog = tempnam(sys_get_temp_dir(), 'fake_openai_');
  putenv("FAKE_OPENAI_LOG={$fakeAiLog}");
  putenv("OPENAI_API_URL=http://127.0.0.1:{$fakeAiPort}/v1/chat/completions");
  putenv('OPENAI_API_KEY=chave-de-teste');
  putenv('OPENAI_MAX_RETRIES=1');
  $fakeAiProcess = proc_open(
    ['php', '-d', 'variables_order=EGPCS', '-S', "127.0.0.1:{$fakeAiPort}", ROOT_PATH . '/bin/fixtures/fake_openai_server.php'],
    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $fakeAiPipes,
    ROOT_PATH
  );

  $serverProcess = proc_open(
    // Mesmo fuso do processo de teste: a janela da Publicação precisa bater com o banco.
    // Mesmas variáveis de ambiente do processo de teste (EGPCS): o servidor filho
    // precisa falar com o MESMO banco, não com o do .env.
    ['php', '-d', 'variables_order=EGPCS', '-d', 'date.timezone=' . date_default_timezone_get(), '-S', $host, '-t', ROOT_PATH . '/public', ROOT_PATH . '/public/index.php'],
    $descriptors,
    $pipes,
    ROOT_PATH
  );

  if (!is_resource($serverProcess)) {
    echo "Não foi possível iniciar o servidor embutido — smoke HTTP pulado.\n";
  } else {
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $ready = false;
    for ($i = 0; $i < 40; $i++) {
      usleep(100_000);
      $probe = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
      if ($probe !== false) {
        fclose($probe);
        $ready = true;
        break;
      }
    }

    if (!$ready) {
      echo "Servidor embutido não respondeu a tempo — smoke HTTP pulado.\n";
    } else {
      $login = httpGet("http://{$host}/login");
      check($login['code'] === 200, 'HTTP: GET /login responde 200');
      check(str_contains($login['headers'], 'Content-Security-Policy:'), 'HTTP: resposta inclui Content-Security-Policy');
      check(str_contains($login['headers'], 'X-Frame-Options:'), 'HTTP: resposta inclui X-Frame-Options');
      check(str_contains($login['headers'], 'X-Content-Type-Options:'), 'HTTP: resposta inclui X-Content-Type-Options');

      $adminNoAuth = httpGet("http://{$host}/admin/dashboard");
      check($adminNoAuth['code'] === 302, 'HTTP: GET /admin/dashboard sem sessão responde 302 (autorização negativa)');
      check(
        str_contains($adminNoAuth['headers'], '/login'),
        'HTTP: redirect de /admin/dashboard sem sessão aponta para /login'
      );

      $ch = curl_init("http://{$host}/login");
      curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(['email' => 'x@x.com', 'password' => 'x']),
        CURLOPT_TIMEOUT        => 5,
      ]);
      $csrfRaw = curl_exec($ch);
      $csrfCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      check($csrfCode === 403, 'HTTP: POST /login sem token CSRF responde 403');

      // ── RP-23-03 — autorização negativa autenticada ─────────────────────
      $base = "http://{$host}";
      $pw = 'SenhaForte1';
      $pwHash = password_hash($pw, PASSWORD_BCRYPT);
      $sfx = bin2hex(random_bytes(4));
      $mkUser = function (string $name, string $role) use ($pdoA, $pwHash, $sfx): array {
        $email = strtolower(str_replace(' ', '.', $name)) . ".{$sfx}@test.local";
        $pdoA->exec("INSERT INTO users (name, email, password_hash, role, status) VALUES ('{$name}', '{$email}', '{$pwHash}', '{$role}', 'active')");
        return ['id' => (int) $pdoA->lastInsertId(), 'email' => $email];
      };
      $docenteA = $mkUser('Docente A', 'teacher');

      $jarDocenteA = loginAs($base, $docenteA['email'], $pw);
      $dash = httpAs($jarDocenteA, 'GET', "{$base}/teacher/dashboard");
      check($dash['code'] === 200, 'RP-23-03: Docente logado pelo fluxo real acessa o próprio painel');

      $docenteB = $mkUser('Docente B', 'teacher');
      $alunoA = $mkUser('Aluno A', 'student');
      $alunoB = $mkUser('Aluno B', 'student');
      $alunoPendente = $mkUser('Aluno Pendente HTTP', 'student');

      $mkTurma = function (int $teacherId, string $name) use ($pdoA): array {
        $key = strtoupper(bin2hex(random_bytes(3)));
        $pdoA->exec("INSERT INTO turmas (teacher_id, name, access_key) VALUES ({$teacherId}, '{$name}', '{$key}')");
        return ['id' => (int) $pdoA->lastInsertId(), 'key' => $key];
      };
      $turmaA = $mkTurma($docenteA['id'], 'Turma HTTP A');
      $turmaB = $mkTurma($docenteB['id'], 'Turma HTTP B');
      $pdoA->exec("INSERT INTO student_turma (student_id, turma_id, status) VALUES
                   ({$alunoA['id']}, {$turmaA['id']}, 'active'),
                   ({$alunoB['id']}, {$turmaB['id']}, 'active'),
                   ({$alunoPendente['id']}, {$turmaA['id']}, 'pending')");

      $enrollmentState = fn(): string => implode(',', array_map(
        fn($r) => "{$r['student_id']}:{$r['turma_id']}:{$r['status']}",
        $pdoA->query("SELECT student_id, turma_id, status FROM student_turma ORDER BY student_id, turma_id")->fetchAll()
      ));
      $turmaKey = fn(int $id): string => (string) $pdoA->query("SELECT access_key FROM turmas WHERE id = {$id}")->fetchColumn();

      // Linha 1 — Docente B não opera na Turma A
      $jarDocenteB = loginAs($base, $docenteB['email'], $pw);
      check(
        httpAs($jarDocenteB, 'GET', "{$base}/teacher/turmas/{$turmaA['id']}")['code'] === 403,
        'RP-23-03: Docente não vê Turma de outro Docente (403)'
      );

      $before = $enrollmentState();
      $idorPosts = [
        "/teacher/turmas/{$turmaA['id']}/key",
        "/teacher/turmas/{$turmaA['id']}/approve/{$alunoPendente['id']}",
        "/teacher/turmas/{$turmaA['id']}/reject/{$alunoPendente['id']}",
        "/teacher/turmas/{$turmaA['id']}/detach/{$alunoA['id']}",
      ];
      foreach ($idorPosts as $path) {
        check(postAs($base, $jarDocenteB, $path)['code'] === 403, "RP-23-03: Docente recebe 403 em POST {$path} de outra Turma");
      }
      check($turmaKey($turmaA['id']) === $turmaA['key'], 'RP-23-03: Chave de acesso não muda por outro Docente');
      check($enrollmentState() === $before, 'RP-23-03: Matrículas não mudam por outro Docente');

      // Controle positivo: o 403 acima é de propriedade, não de CSRF inválido.
      $ownKey = postAs($base, $jarDocenteA, "/teacher/turmas/{$turmaA['id']}/key");
      check(
        $ownKey['code'] === 302 && $turmaKey($turmaA['id']) !== $turmaA['key'],
        'RP-23-03: dono da Turma gera nova Chave de acesso com o mesmo helper (CSRF válido)'
      );
      $turmaA['key'] = $turmaKey($turmaA['id']);

      // Exercício A publicado só na Turma A; Aluno A com uma Tentativa corrigida e uma em andamento.
      $pdoA->exec("INSERT INTO exercises (teacher_id, title, status) VALUES ({$docenteA['id']}, 'Exercício HTTP A', 'active')");
      $exA = (int) $pdoA->lastInsertId();
      $pdoA->exec("INSERT INTO exercise_turmas (exercise_id, turma_id, opens_at, closes_at, max_attempts)
                   VALUES ({$exA}, {$turmaA['id']}, NOW() - INTERVAL 1 HOUR, NOW() + INTERVAL 1 HOUR, 0)");
      $pdoA->exec("INSERT INTO questions (exercise_id, text, expected_answer_hint, max_score, order_index) VALUES ({$exA}, 'Explique pilha.', 'LIFO.', 10.0, 1)");
      $qA = (int) $pdoA->lastInsertId();
      $pdoA->exec("INSERT INTO attempts (exercise_id, student_id, turma_id, status, total_score, submitted_at) VALUES ({$exA}, {$alunoA['id']}, {$turmaA['id']}, 'graded', 7.0, NOW())");
      $attGradedA = (int) $pdoA->lastInsertId();
      $pdoA->exec("INSERT INTO attempts (exercise_id, student_id, turma_id, status) VALUES ({$exA}, {$alunoA['id']}, {$turmaA['id']}, 'in_progress')");
      $attOpenA = (int) $pdoA->lastInsertId();
      $countRows = fn(string $sql): int => (int) $pdoA->query($sql)->fetchColumn();

      // Linha 2 — Docente B não vê Exercício nem resultado do Docente A
      check(
        httpAs($jarDocenteB, 'GET', "{$base}/teacher/exercises/{$exA}")['code'] === 403,
        'RP-23-03: Docente não vê Exercício de outro Docente (403)'
      );
      check(
        httpAs($jarDocenteB, 'GET', "{$base}/teacher/attempts/{$attGradedA}/result")['code'] === 403,
        'RP-23-03: Docente não vê resultado de Tentativa de Aluno de outro Docente (403)'
      );

      // Linha 3 — Aluno B não abre nem inicia Exercício só publicado na Turma A
      $jarAlunoA = loginAs($base, $alunoA['email'], $pw);
      $jarAlunoB = loginAs($base, $alunoB['email'], $pw);
      $showB = httpAs($jarAlunoB, 'GET', "{$base}/student/exercises/{$exA}");
      check(in_array($showB['code'], [403, 404], true), "RP-23-03: Aluno de outra Turma não abre o Exercício (recebeu {$showB['code']})");
      $startB = postAs($base, $jarAlunoB, "/student/exercises/{$exA}/start");
      check(in_array($startB['code'], [403, 404], true), "RP-23-03: Aluno de outra Turma não inicia Tentativa (recebeu {$startB['code']})");
      check(
        $countRows("SELECT COUNT(*) FROM attempts WHERE student_id = {$alunoB['id']}") === 0,
        'RP-23-03: nenhuma Tentativa é criada para Aluno de outra Turma'
      );

      // Linha 4 — Aluno B não escreve nem lê a Tentativa do Aluno A
      $answerB = postAs($base, $jarAlunoB, "/student/attempts/{$attOpenA}/answer", ['question_id' => $qA, 'answer' => 'resposta forjada']);
      check($answerB['code'] === 403, "RP-23-03: Aluno não salva Resposta na Tentativa de outro Aluno (recebeu {$answerB['code']})");
      check(
        $countRows("SELECT COUNT(*) FROM answers WHERE attempt_id = {$attOpenA}") === 0,
        'RP-23-03: Resposta forjada não é gravada'
      );
      check(
        httpAs($jarAlunoB, 'GET', "{$base}/student/attempts/{$attGradedA}/result")['code'] === 403,
        'RP-23-03: Aluno não vê resultado da Tentativa de outro Aluno (403)'
      );

      // Controle positivo da linha 4: o dono salva a própria Resposta.
      $answerA = postAs($base, $jarAlunoA, "/student/attempts/{$attOpenA}/answer", ['question_id' => $qA, 'answer' => 'LIFO: último a entrar, primeiro a sair.']);
      check(
        $answerA['code'] === 200 && $countRows("SELECT COUNT(*) FROM answers WHERE attempt_id = {$attOpenA}") === 1,
        "RP-23-03: dono da Tentativa salva a própria Resposta (recebeu {$answerA['code']})"
      );

      // Linha 5 — papel errado volta para o próprio painel
      $wrongRole = httpAs($jarAlunoA, 'GET', "{$base}/teacher/dashboard");
      check(
        $wrongRole['code'] === 302 && str_ends_with($wrongRole['location'], '/student/dashboard'),
        'RP-23-03: Aluno em rota de Docente é redirecionado ao painel do Aluno'
      );
      $wrongRole = httpAs($jarDocenteA, 'GET', "{$base}/admin/dashboard");
      check(
        $wrongRole['code'] === 302 && str_ends_with($wrongRole['location'], '/teacher/dashboard'),
        'RP-23-03: Docente em rota de Admin é redirecionado ao painel do Docente'
      );

      $auditCount = fn(string $action, int $entityId): int =>
        $countRows("SELECT COUNT(*) FROM audit_logs WHERE action = " . $pdoA->quote($action) . " AND entity_id = {$entityId}");

      // Linha 8 — Auditoria de Recusar/Desvincular pela interface (Turma ativa)
      $approve = postAs($base, $jarDocenteA, "/teacher/turmas/{$turmaA['id']}/approve/{$alunoPendente['id']}");
      check($approve['code'] === 302, 'RP-23-03: Docente aprova Matrícula pendente da própria Turma');
      $rejectActive = postAs($base, $jarDocenteA, "/teacher/turmas/{$turmaA['id']}/reject/{$alunoA['id']}");
      check(
        $rejectActive['code'] === 302
          && $auditCount('teacher.student.reject_denied', $alunoA['id']) === 1
          && $auditCount('teacher.student.reject', $alunoA['id']) === 0
          && $countRows("SELECT COUNT(*) FROM student_turma WHERE student_id = {$alunoA['id']} AND turma_id = {$turmaA['id']} AND status = 'active'") === 1,
        'RP-23-03: recusar Matrícula ativa pela interface audita reject_denied e preserva a Matrícula'
      );
      $detach = postAs($base, $jarDocenteA, "/teacher/turmas/{$turmaA['id']}/detach/{$alunoPendente['id']}");
      check(
        $detach['code'] === 302
          && $auditCount('teacher.student.detach_turma', $alunoPendente['id']) === 1
          && $countRows("SELECT COUNT(*) FROM student_turma WHERE student_id = {$alunoPendente['id']}") === 0,
        'RP-23-03: desvincular da Turma pela interface audita detach_turma e remove só aquela Matrícula'
      );

      // Linha 6 — Admin inativa a Turma A; Aluno A perde o acesso ao Exercício
      $admin = $mkUser('Admin HTTP', 'admin');
      $jarAdmin = loginAs($base, $admin['email'], $pw);
      check(
        $countRows("SELECT COUNT(*) FROM exercise_turmas WHERE exercise_id = {$exA}") === 1
          && httpAs($jarAlunoA, 'GET', "{$base}/student/exercises/{$exA}")['code'] === 200,
        'RP-23-03: Aluno da Turma ativa abre o Exercício (controle)'
      );
      postAs($base, $jarAdmin, "/admin/turmas/{$turmaA['id']}/deactivate");
      check(
        $countRows("SELECT active FROM turmas WHERE id = {$turmaA['id']}") === 0,
        'RP-23-03: Admin inativa a Turma pela interface'
      );
      $showInactive = httpAs($jarAlunoA, 'GET', "{$base}/student/exercises/{$exA}");
      check(in_array($showInactive['code'], [403, 404], true), "RP-23-03: Aluno não abre Exercício de Turma inativa (recebeu {$showInactive['code']})");

      // Linha 7 — Docente não altera Turma inativa; cada tentativa fica auditada como bloqueada
      $pdoA->exec("INSERT INTO student_turma (student_id, turma_id, status) VALUES ({$alunoPendente['id']}, {$turmaA['id']}, 'pending')");
      $before = $enrollmentState();
      $blocked = [
        ["/teacher/turmas/{$turmaA['id']}/key", 'teacher.turma.regenerate_key.blocked'],
        ["/teacher/turmas/{$turmaA['id']}/approve/{$alunoPendente['id']}", 'teacher.student.approve.blocked'],
        ["/teacher/turmas/{$turmaA['id']}/reject/{$alunoPendente['id']}", 'teacher.student.reject.blocked'],
        ["/teacher/turmas/{$turmaA['id']}/detach/{$alunoA['id']}", 'teacher.student.detach_turma.blocked'],
      ];
      foreach ($blocked as [$path, $action]) {
        $r = postAs($base, $jarDocenteA, $path);
        check(
          $r['code'] === 302 && str_ends_with($r['location'], "/teacher/turmas/{$turmaA['id']}") && $auditCount($action, $turmaA['id']) === 1,
          "RP-23-03: Turma inativa bloqueia POST {$path} e audita {$action}"
        );
      }
      check($turmaKey($turmaA['id']) === $turmaA['key'], 'RP-23-03: Chave de acesso não muda em Turma inativa');
      check($enrollmentState() === $before, 'RP-23-03: Matrículas não mudam em Turma inativa');

      // ── Banco de exercícios ──────────────────────────────────────────────
      $mkExercise = function (int $teacherId, string $title, string $status, array $questionTexts) use ($pdoA): array {
        $pdoA->exec("INSERT INTO exercises (teacher_id, title, description, status) VALUES ({$teacherId}, " . $pdoA->quote($title) . ", 'Descrição de origem', '{$status}')");
        $id = (int) $pdoA->lastInsertId();
        $qids = [];
        foreach ($questionTexts as $i => $text) {
          $score = 2.0 + $i;
          $pdoA->exec("INSERT INTO questions (exercise_id, text, expected_answer_hint, max_score, order_index)
                       VALUES ({$id}, " . $pdoA->quote($text) . ", " . $pdoA->quote("Ref: {$text}") . ", {$score}, " . ($i + 1) . ")");
          $qids[] = (int) $pdoA->lastInsertId();
        }
        return ['id' => $id, 'questions' => $qids];
      };
      $questionsOf = fn(int $exerciseId): array => $pdoA->query(
        "SELECT text, expected_answer_hint, max_score, order_index FROM questions WHERE exercise_id = {$exerciseId} ORDER BY order_index"
      )->fetchAll();
      $draftIdFrom = static function (array $response): int {
        return preg_match('#/teacher/exercises/(\d+)/questions/create$#', $response['location'], $m) ? (int) $m[1] : 0;
      };

      // BANCO-01 — Docente importa Questões escolhidas de um Exercício próprio para um Rascunho novo
      $ownSource = $mkExercise($docenteA['id'], 'Recursão (origem)', 'ready', ['Q1 base', 'Q2 meio', 'Q3 fim']);
      $originalQuestions = $questionsOf($ownSource['id']);
      $import = postAs($base, $jarDocenteA, '/teacher/bank/import', [
        'source' => 'own',
        'source_id' => $ownSource['id'],
        'question_ids' => [$ownSource['questions'][0], $ownSource['questions'][2]],
        'title' => 'Recursão (cópia)',
      ]);
      $newDraftId = $draftIdFrom($import);
      check($import['code'] === 302 && $newDraftId > 0, "BANCO-01: importação redireciona para as Questões do Rascunho novo (recebeu {$import['code']} {$import['location']})");
      $newDraft = $newDraftId > 0 ? $pdoA->query("SELECT teacher_id, title, status FROM exercises WHERE id = {$newDraftId}")->fetch() : false;
      check(
        $newDraft !== false && (int) $newDraft['teacher_id'] === $docenteA['id'] && $newDraft['status'] === 'draft' && $newDraft['title'] === 'Recursão (cópia)',
        'BANCO-01: Rascunho novo pertence ao Docente, com o título informado'
      );
      check(
        $newDraftId > 0 && $questionsOf($newDraftId) === [
          ['text' => 'Q1 base', 'expected_answer_hint' => 'Ref: Q1 base', 'max_score' => '2.0', 'order_index' => 1],
          ['text' => 'Q3 fim', 'expected_answer_hint' => 'Ref: Q3 fim', 'max_score' => '4.0', 'order_index' => 2],
        ],
        'BANCO-01: só as Questões marcadas são copiadas, com Resposta de referência e Pontuação, renumeradas'
      );
      check($questionsOf($ownSource['id']) === $originalQuestions, 'BANCO-01: Exercício de origem fica intacto');
      check($auditCount('teacher.bank.import', $newDraftId) === 1, 'BANCO-01: importação é auditada');

      // BANCO-02 — Importar para um Rascunho existente acrescenta depois das Questões que já existem
      $append = postAs($base, $jarDocenteA, '/teacher/bank/import', [
        'source' => 'own', 'source_id' => $ownSource['id'], 'exercise_id' => $newDraftId,
        'question_ids' => [$ownSource['questions'][1]],
      ]);
      check($draftIdFrom($append) === $newDraftId, 'BANCO-02: importação para Rascunho existente volta às Questões dele');
      check(
        array_column($questionsOf($newDraftId), 'text') === ['Q1 base', 'Q3 fim', 'Q2 meio']
          && array_column($questionsOf($newDraftId), 'order_index') === [1, 2, 3],
        'BANCO-02: Questões importadas entram depois das existentes'
      );

      // BANCO-03 — Negações: nada é copiado
      $countAll = fn(): int => $countRows('SELECT COUNT(*) FROM questions');
      $before = $countAll();
      $foreignDraft = $mkExercise($docenteB['id'], 'Rascunho do Docente B', 'draft', ['QB1']);
      $r = postAs($base, $jarDocenteA, '/teacher/bank/import', [
        'source' => 'own', 'source_id' => $ownSource['id'], 'exercise_id' => $foreignDraft['id'],
        'question_ids' => [$ownSource['questions'][0]],
      ]);
      check($r['code'] === 403, "BANCO-03: importar para Rascunho de outro Docente responde 403 (recebeu {$r['code']})");

      $published = $mkExercise($docenteA['id'], 'Publicado do Docente A', 'active', ['QP1']);
      $r = postAs($base, $jarDocenteA, '/teacher/bank/import', [
        'source' => 'own', 'source_id' => $ownSource['id'], 'exercise_id' => $published['id'],
        'question_ids' => [$ownSource['questions'][0]],
      ]);
      check($r['code'] === 302 && !str_contains($r['location'], '/questions/create'), 'BANCO-03: importar para Exercício Publicado é recusado');

      $foreignSource = $mkExercise($docenteB['id'], 'Concluído do Docente B', 'ready', ['QB fonte']);
      $r = postAs($base, $jarDocenteA, '/teacher/bank/import', [
        'source' => 'own', 'source_id' => $foreignSource['id'], 'question_ids' => $foreignSource['questions'],
      ]);
      check($r['code'] === 302 && !str_contains($r['location'], '/questions/create'), 'BANCO-03: "Meus" não aceita Exercício de outro Docente como origem');

      $r = postAs($base, $jarDocenteA, '/teacher/bank/import', [
        'source' => 'own', 'source_id' => $ownSource['id'], 'question_ids' => [$foreignSource['questions'][0]],
      ]);
      check($r['code'] === 302 && !str_contains($r['location'], '/questions/create'), 'BANCO-03: Questão que não pertence à origem é ignorada');
      check($countAll() === $before + 1 + 1 + 1, 'BANCO-03: nenhuma Questão foi copiada nas tentativas negadas (só as de seed)');

      $blockedSource = $mkExercise($docenteA['id'], 'Com Questão Bloqueada', 'ready', ['Liberada', 'Bloqueada']);
      $pdoA->exec("UPDATE questions SET admin_review_status = 'blocked' WHERE id = {$blockedSource['questions'][1]}");
      $r = postAs($base, $jarDocenteA, '/teacher/bank/import', [
        'source' => 'own', 'source_id' => $blockedSource['id'], 'question_ids' => $blockedSource['questions'], 'title' => 'Sem bloqueada',
      ]);
      check(array_column($questionsOf($draftIdFrom($r)), 'text') === ['Liberada'], 'BANCO-03: Questão Bloqueada nunca é copiada');

      $studentBank = postAs($base, $jarAlunoA, '/teacher/bank/import', [
        'source' => 'own', 'source_id' => $ownSource['id'], 'question_ids' => $ownSource['questions'],
      ]);
      check($studentBank['code'] === 302 && str_ends_with($studentBank['location'], '/student/dashboard'), 'BANCO-03: Aluno não usa o banco');

      // BANCO-04 — Docente sugere, Admin aprova com Temas, item aparece no banco e é importado
      $bankRow = fn(int $sourceExerciseId): array|false => $pdoA->query(
        "SELECT id, status, review_note, reviewed_by FROM bank_exercises WHERE source_exercise_id = {$sourceExerciseId} ORDER BY id DESC LIMIT 1"
      )->fetch();
      $suggestSource = $mkExercise($docenteA['id'], 'Vetores e laços', 'active', ['Some um vetor', 'Inverta um vetor']);
      $r = postAs($base, $jarDocenteA, "/teacher/exercises/{$suggestSource['id']}/suggest-bank");
      $suggested = $bankRow($suggestSource['id']);
      check($r['code'] === 302 && $suggested !== false && $suggested['status'] === 'suggested', 'BANCO-04: Docente sugere Exercício Publicado próprio');
      check(
        $suggested !== false && $countRows("SELECT COUNT(*) FROM bank_questions WHERE bank_exercise_id = {$suggested['id']}") === 2,
        'BANCO-04: a Sugestão guarda cópia das Questões'
      );
      postAs($base, $jarDocenteA, "/teacher/exercises/{$suggestSource['id']}/suggest-bank");
      check($countRows("SELECT COUNT(*) FROM bank_exercises WHERE source_exercise_id = {$suggestSource['id']}") === 1, 'BANCO-04: Sugestão repetida não duplica');

      $r = postAs($base, $jarDocenteA, "/teacher/exercises/{$newDraftId}/suggest-bank");
      check($bankRow($newDraftId) === false, 'BANCO-04: Rascunho não pode ser sugerido');
      $r = postAs($base, $jarDocenteA, "/teacher/exercises/{$foreignSource['id']}/suggest-bank");
      check($r['code'] === 403 && $bankRow($foreignSource['id']) === false, 'BANCO-04: Docente não sugere Exercício de outro Docente (403)');
      $pdoA->exec("UPDATE exercises SET admin_review_status = 'flagged' WHERE id = {$published['id']}");
      postAs($base, $jarDocenteA, "/teacher/exercises/{$published['id']}/suggest-bank");
      check($bankRow($published['id']) === false, 'BANCO-04: Exercício Sinalizado não pode ser sugerido');

      $r = postAs($base, $jarDocenteA, "/admin/bank/{$suggested['id']}/approve", ['themes' => 'Vetores, Laços']);
      check($r['code'] === 302 && ($bankRow($suggestSource['id'])['status'] ?? '') === 'suggested', 'BANCO-04: Docente não aprova Sugestão');
      $r = postAs($base, $jarAdmin, "/admin/bank/{$suggested['id']}/approve", ['themes' => 'Vetores, Laços']);
      $approved = $bankRow($suggestSource['id']);
      check(
        $r['code'] === 302 && $approved['status'] === 'active' && (int) $approved['reviewed_by'] === $admin['id']
          && $auditCount('admin.bank.approve', (int) $suggested['id']) === 1,
        'BANCO-04: Admin aprova a Sugestão, com auditoria'
      );
      check(
        $countRows("SELECT COUNT(*) FROM bank_exercise_themes bet JOIN bank_themes bt ON bt.id = bet.theme_id
                    WHERE bet.bank_exercise_id = {$suggested['id']} AND bt.name IN ('Vetores', 'Laços')") === 2,
        'BANCO-04: Temas definidos na aprovação ficam ligados ao item'
      );

      $listed = httpAs($jarDocenteB, 'GET', "{$base}/teacher/bank?theme=" . rawurlencode('Vetores'));
      check($listed['code'] === 200 && str_contains($listed['body'], 'Vetores e laços'), 'BANCO-04: item aprovado aparece no banco filtrado pelo Tema');
      $other = httpAs($jarDocenteB, 'GET', "{$base}/teacher/bank?theme=" . rawurlencode('Recursão'));
      check($other['code'] === 200 && !str_contains($other['body'], 'Vetores e laços'), 'BANCO-04: filtro por outro Tema não traz o item');

      $bankQuestionIds = array_map('intval', array_column(
        $pdoA->query("SELECT id FROM bank_questions WHERE bank_exercise_id = {$suggested['id']} ORDER BY order_index")->fetchAll(), 'id'
      ));
      $fromBank = postAs($base, $jarDocenteB, '/teacher/bank/import', [
        'source' => 'bank', 'source_id' => $suggested['id'], 'question_ids' => [$bankQuestionIds[1]], 'title' => 'Do banco',
      ]);
      $bankDraftId = $draftIdFrom($fromBank);
      check(
        $bankDraftId > 0 && array_column($questionsOf($bankDraftId), 'text') === ['Inverta um vetor']
          && (int) $pdoA->query("SELECT teacher_id FROM exercises WHERE id = {$bankDraftId}")->fetchColumn() === $docenteB['id'],
        'BANCO-04: outro Docente importa do banco institucional para um Rascunho dele'
      );

      // BANCO-05 — Recusa com motivo, inclusão direta pelo Admin e arquivamento
      $rejectSource = $mkExercise($docenteA['id'], 'Para recusar', 'ready', ['QR']);
      postAs($base, $jarDocenteA, "/teacher/exercises/{$rejectSource['id']}/suggest-bank");
      $toReject = $bankRow($rejectSource['id']);
      postAs($base, $jarAdmin, "/admin/bank/{$toReject['id']}/reject", ['review_note' => 'Enunciado ambíguo']);
      $rejected = $bankRow($rejectSource['id']);
      check($rejected['status'] === 'rejected' && $rejected['review_note'] === 'Enunciado ambíguo', 'BANCO-05: recusa grava o motivo');

      $directSource = $mkExercise($docenteB['id'], 'Incluído pelo Admin', 'ready', ['QD']);
      postAs($base, $jarAdmin, "/admin/exercises/{$directSource['id']}/add-to-bank", ['themes' => 'Recursão']);
      check(($bankRow($directSource['id'])['status'] ?? '') === 'active', 'BANCO-05: Admin inclui Exercício direto no banco, já ativo');

      postAs($base, $jarAdmin, "/admin/bank/{$suggested['id']}/archive");
      $afterArchive = httpAs($jarDocenteB, 'GET', "{$base}/teacher/bank");
      $r = postAs($base, $jarDocenteB, '/teacher/bank/import', [
        'source' => 'bank', 'source_id' => $suggested['id'], 'question_ids' => $bankQuestionIds,
      ]);
      check(
        !str_contains($afterArchive['body'], 'Vetores e laços') && !str_contains($r['location'], '/questions/create'),
        'BANCO-05: item arquivado some do banco e não pode mais ser importado'
      );
      postAs($base, $jarAdmin, "/admin/bank/{$toReject['id']}/approve", ['themes' => 'Reabrir']);
      postAs($base, $jarAdmin, "/admin/bank/{$suggested['id']}/approve", ['themes' => 'Reabrir']);
      check(
        ($bankRow($rejectSource['id'])['status'] ?? '') === 'rejected' && ($bankRow($suggestSource['id'])['status'] ?? '') === 'archived',
        'BANCO-05: só Sugestões podem ser aprovadas (recusado e arquivado não voltam ao banco por "aprovar")'
      );

      // BANCO-06 — Telas: pontos de entrada e escopo da aba "Meus"
      $page = fn(string $jar, string $path): array => httpAs($jar, 'GET', "{$base}{$path}");
      $create = $page($jarDocenteA, '/teacher/exercises/create');
      check($create['code'] === 200 && str_contains($create['body'], 'Partir do banco') && str_contains($create['body'], 'name="title"'),
        'BANCO-06: "Criar exercício" oferece começar do zero e partir do banco');
      $draftPage = $page($jarDocenteA, "/teacher/exercises/{$newDraftId}");
      check($draftPage['code'] === 200 && str_contains($draftPage['body'], "/teacher/bank?exercise={$newDraftId}"),
        'BANCO-06: Rascunho oferece importar questões do banco');
      $readyPage = $page($jarDocenteA, "/teacher/exercises/{$rejectSource['id']}");
      check($readyPage['code'] === 200 && str_contains($readyPage['body'], 'Enunciado ambíguo') && str_contains($readyPage['body'], 'Sugerir para o banco'),
        'BANCO-06: Exercício recusado mostra o motivo e permite sugerir de novo');
      $targetPage = $page($jarDocenteA, "/teacher/bank?exercise={$newDraftId}&tab=own");
      check($targetPage['code'] === 200 && str_contains($targetPage['body'], 'Adicionar ao Rascunho') && str_contains($targetPage['body'], 'Recursão (origem)'),
        'BANCO-06: banco em modo "adicionar ao Rascunho" lista os Exercícios próprios');
      check(!str_contains($targetPage['body'], 'Concluído do Docente B'), 'BANCO-06: aba "Meus" não mostra Exercícios de outro Docente');
      check($page($jarDocenteA, "/teacher/bank?exercise={$foreignDraft['id']}")['code'] === 403,
        'BANCO-06: banco não abre em modo "adicionar" para Rascunho de outro Docente (403)');
      $adminBank = $page($jarAdmin, '/admin/bank?status=rejected');
      check($adminBank['code'] === 200 && str_contains($adminBank['body'], 'Para recusar'), 'BANCO-06: curadoria do Admin lista os recusados');
      $adminExercise = $page($jarAdmin, "/admin/exercises/{$ownSource['id']}");
      check($adminExercise['code'] === 200 && str_contains($adminExercise['body'], 'Adicionar ao banco'), 'BANCO-06: Admin pode incluir Exercício direto no banco');
      check($page($jarDocenteA, '/admin/bank')['code'] === 302, 'BANCO-06: Docente não acessa a curadoria');

      // EXC-01 — Excluir Exercício: Rascunho e Concluído sem Publicação podem; Publicado não
      $exists = fn(int $id): bool => (int) $pdoA->query("SELECT COUNT(*) FROM exercises WHERE id = {$id}")->fetchColumn() === 1;
      $readyToDelete = $mkExercise($docenteA['id'], 'Concluído para excluir', 'ready', ['QX1', 'QX2']);
      $readyPage = $page($jarDocenteA, "/teacher/exercises/{$readyToDelete['id']}");
      check(str_contains($readyPage['body'], 'Excluir exercício'), 'EXC-01: Concluído sem Publicação mostra o botão de excluir');
      $del = postAs($base, $jarDocenteA, "/teacher/exercises/{$readyToDelete['id']}/delete");
      check(
        $del['code'] === 302 && str_ends_with($del['location'], '/teacher/exercises') && !$exists($readyToDelete['id'])
          && $countRows("SELECT COUNT(*) FROM questions WHERE exercise_id = {$readyToDelete['id']}") === 0,
        'EXC-01: Docente exclui o próprio Exercício Concluído, com as Questões'
      );

      $publishedPage = $page($jarDocenteA, "/teacher/exercises/{$exA}");
      check(!str_contains($publishedPage['body'], 'Excluir exercício'), 'EXC-01: Exercício Publicado não mostra o botão de excluir');
      $del = postAs($base, $jarDocenteA, "/teacher/exercises/{$exA}/delete");
      check($exists($exA), 'EXC-01: Exercício Publicado não é excluído mesmo com POST direto');

      $foreignReady = $mkExercise($docenteB['id'], 'Concluído do B para excluir', 'ready', ['QXB']);
      $del = postAs($base, $jarDocenteA, "/teacher/exercises/{$foreignReady['id']}/delete");
      check($del['code'] === 403 && $exists($foreignReady['id']), 'EXC-01: Docente não exclui Exercício de outro Docente (403)');

      // ENG-01 — Aluno vê os Motivos de desconto, em linguagem amigável e com dica de estudo
      $pdoA->exec("INSERT INTO answers (attempt_id, question_id, student_answer, ai_score, ai_feedback, deduction_reasons_json, evaluated_at)
                   VALUES ({$attGradedA}, {$qA}, 'Pilha é uma lista.', 1.0, 'Faltou explicar a ordem de saída.',
                           '[\"missing_concept\", \"logic_error\", \"<script>x</script>\"]', NOW())");
      $studentResult = httpAs($jarAlunoA, 'GET', "{$base}/student/attempts/{$attGradedA}/result");
      check(
        $studentResult['code'] === 200 && str_contains($studentResult['body'], 'O que faltou para a nota máxima'),
        "ENG-01: Aluno vê o bloco de Motivos de desconto no resultado (recebeu {$studentResult['code']})"
      );
      check(
        str_contains($studentResult['body'], \App\Models\DeductionReason::label('missing_concept'))
          && str_contains($studentResult['body'], \App\Models\DeductionReason::tip('missing_concept'))
          && str_contains($studentResult['body'], \App\Models\DeductionReason::label('logic_error')),
        'ENG-01: cada motivo aparece com rótulo amigável e dica de estudo'
      );
      check(!str_contains($studentResult['body'], '<script>x</script>') && !str_contains($studentResult['body'], 'missing_concept'),
        'ENG-01: códigos internos e motivos desconhecidos não aparecem para o Aluno');
      $teacherResult = httpAs($jarDocenteA, 'GET', "{$base}/teacher/attempts/{$attGradedA}/result");
      check(str_contains($teacherResult['body'], \App\Models\DeductionReason::label('logic_error')), 'ENG-01: Docente continua vendo os motivos');

      // REV — Revisão de nota: ajuste pelo Docente e pedido de revisão pelo Aluno
      $answerA = (int) $pdoA->query("SELECT id FROM answers WHERE attempt_id = {$attGradedA} AND question_id = {$qA}")->fetchColumn();
      $scoreOf = fn(): string => (string) $pdoA->query("SELECT ai_score FROM answers WHERE id = {$answerA}")->fetchColumn();
      $totalOf = fn(): string => (string) $pdoA->query("SELECT total_score FROM attempts WHERE id = {$attGradedA}")->fetchColumn();
      $reviews = fn(): array => $pdoA->query("SELECT status, previous_score, new_score, justification, request_message FROM score_reviews WHERE answer_id = {$answerA} ORDER BY id")->fetchAll();

      // REV-01 — Docente ajusta a nota com justificativa; a Tentativa é recalculada
      $r = postAs($base, $jarDocenteA, "/teacher/answers/{$answerA}/review", ['score' => '6.5', 'justification' => 'A ordem LIFO foi explicada corretamente.']);
      $reviewRows = $reviews();
      check(
        $r['code'] === 302 && $scoreOf() === '6.5' && (float) $totalOf() === 6.5,
        "REV-01: ajuste do Docente muda a Pontuação e recalcula a nota da Tentativa (score={$scoreOf()} total={$totalOf()})"
      );
      check(
        count($reviewRows) === 1 && $reviewRows[0]['status'] === 'adjusted' && $reviewRows[0]['previous_score'] === '1.0'
          && $reviewRows[0]['new_score'] === '6.5' && $reviewRows[0]['justification'] === 'A ordem LIFO foi explicada corretamente.',
        'REV-01: a Revisão de nota guarda a nota anterior, a nova e a justificativa'
      );
      check($auditCount('teacher.score.adjust', $answerA) === 1, 'REV-01: ajuste é auditado');

      // REV-02 — Validações e permissões do ajuste
      postAs($base, $jarDocenteA, "/teacher/answers/{$answerA}/review", ['score' => '11', 'justification' => 'Acima do máximo']);
      postAs($base, $jarDocenteA, "/teacher/answers/{$answerA}/review", ['score' => '3', 'justification' => '']);
      check($scoreOf() === '6.5' && count($reviews()) === 1, 'REV-02: nota acima do máximo ou sem justificativa é recusada');
      $r = postAs($base, $jarDocenteB, "/teacher/answers/{$answerA}/review", ['score' => '0', 'justification' => 'Tentativa indevida']);
      check($r['code'] === 403 && $scoreOf() === '6.5', 'REV-02: outro Docente não revisa a nota (403)');

      // REV-03 — Aluno vê a nota revisada e pede revisão uma única vez
      $studentView = httpAs($jarAlunoA, 'GET', "{$base}/student/attempts/{$attGradedA}/result");
      check(str_contains($studentView['body'], 'Nota revisada pelo Docente') && str_contains($studentView['body'], 'A ordem LIFO foi explicada corretamente.'),
        'REV-03: Aluno vê que a nota foi revisada, com a justificativa');
      $r = postAs($base, $jarAlunoA, "/student/answers/{$answerA}/review-request", ['message' => 'Acredito que expliquei também o caso de pilha vazia.']);
      postAs($base, $jarAlunoA, "/student/answers/{$answerA}/review-request", ['message' => 'Segundo pedido.']);
      $requested = array_values(array_filter($reviews(), static fn(array $row): bool => $row['status'] === 'requested'));
      check(
        $r['code'] === 302 && count($requested) === 1 && $requested[0]['request_message'] === 'Acredito que expliquei também o caso de pilha vazia.',
        'REV-03: Aluno pede revisão uma única vez por Resposta'
      );
      $r = postAs($base, $jarAlunoB, "/student/answers/{$answerA}/review-request", ['message' => 'Pedido indevido']);
      check($r['code'] === 403 && count($reviews()) === 2, 'REV-03: outro Aluno não pede revisão da Resposta alheia (403)');
      check(str_contains(httpAs($jarAlunoA, 'GET', "{$base}/student/attempts/{$attGradedA}/result")['body'], 'Revisão pedida'),
        'REV-03: Aluno acompanha que o pedido está aguardando o Docente');

      // REV-04 — Pedido aparece para o Docente, que mantém a nota com justificativa
      $dashboard = httpAs($jarDocenteA, 'GET', "{$base}/teacher/dashboard");
      check(str_contains($dashboard['body'], 'Revisões pedidas') && str_contains($dashboard['body'], 'Aluno A'), 'REV-04: painel do Docente lista o pedido de revisão');
      $requestId = (int) $pdoA->query("SELECT id FROM score_reviews WHERE answer_id = {$answerA} AND status = 'requested'")->fetchColumn();
      $r = postAs($base, $jarDocenteA, "/teacher/score-reviews/{$requestId}/keep", ['justification' => 'O caso de pilha vazia não aparece na resposta.']);
      $kept = $pdoA->query("SELECT status, justification FROM score_reviews WHERE id = {$requestId}")->fetch();
      check($r['code'] === 302 && $kept['status'] === 'kept' && $scoreOf() === '6.5', 'REV-04: Docente mantém a nota, que não muda');
      check(str_contains(httpAs($jarAlunoA, 'GET', "{$base}/student/attempts/{$attGradedA}/result")['body'], 'O caso de pilha vazia não aparece na resposta.'),
        'REV-04: Aluno vê a resposta do Docente ao pedido');

      // NOT — Avisos dentro do app
      $notes = fn(int $userId, string $type): array => $pdoA->query(
        "SELECT title, link, read_at FROM notifications WHERE user_id = {$userId} AND type = " . $pdoA->quote($type) . " ORDER BY id"
      )->fetchAll();
      $unread = fn(int $userId): int => (int) $pdoA->query("SELECT COUNT(*) FROM notifications WHERE user_id = {$userId} AND read_at IS NULL")->fetchColumn();

      // NOT-01 — Publicar avisa os Alunos da Turma
      $notifyEx = $mkExercise($docenteB['id'], 'Exercício para avisar', 'ready', ['QN1']);
      $r = postAs($base, $jarDocenteB, "/teacher/exercises/{$notifyEx['id']}/activate", [
        'publication' => [$turmaB['id'] => [
          'enabled' => '1',
          'opens_at' => date('Y-m-d\TH:i', time() - 3600),
          'closes_at' => date('Y-m-d\TH:i', time() + 5 * 3600),
          'max_attempts' => '2',
        ]],
      ]);
      $published = $notes($alunoB['id'], 'exercise_published');
      check(
        count($published) === 1 && str_contains($published[0]['title'], 'Exercício para avisar')
          && str_ends_with($published[0]['link'], "/student/exercises/{$notifyEx['id']}"),
        'NOT-01: publicar o Exercício avisa o Aluno da Turma, com link para o Exercício'
      );
      check($notes($alunoA['id'], 'exercise_published') === [], 'NOT-01: Aluno de outra Turma não recebe o aviso');

      // NOT-02 — Sino mostra não lidos; a página de avisos lista e marca como lidos
      $alunoBPage = httpAs($jarAlunoB, 'GET', "{$base}/student/dashboard");
      check(str_contains($alunoBPage['body'], 'data-unread="' . $unread($alunoB['id']) . '"') && $unread($alunoB['id']) >= 1,
        'NOT-02: sino no topo mostra a quantidade de avisos não lidos');
      $deadline = $notes($alunoB['id'], 'deadline');
      check(count($deadline) === 1 && str_contains($deadline[0]['title'], 'Exercício para avisar'),
        'NOT-02: prazo em menos de 24 h gera aviso para quem ainda não enviou');
      httpAs($jarAlunoB, 'GET', "{$base}/student/dashboard");
      check(count($notes($alunoB['id'], 'deadline')) === 1, 'NOT-02: aviso de prazo não se repete');
      $inbox = httpAs($jarAlunoB, 'GET', "{$base}/notificacoes");
      check($inbox['code'] === 200 && str_contains($inbox['body'], 'Exercício para avisar') && $unread($alunoB['id']) === 0,
        'NOT-02: página de avisos lista os avisos e os marca como lidos');

      // NOT-03 — Correção concluída, Matrícula aprovada e Revisão de nota avisam
      $pdoA->exec("INSERT INTO attempts (exercise_id, student_id, turma_id, status) VALUES ({$notifyEx['id']}, {$alunoB['id']}, {$turmaB['id']}, 'submitted')");
      $submittedId = (int) $pdoA->lastInsertId();
      (new App\Models\Attempt($dbA))->markGraded($submittedId, 1.0);
      $graded = $notes($alunoB['id'], 'attempt_graded');
      check(count($graded) === 1 && str_ends_with($graded[0]['link'], "/student/attempts/{$submittedId}/result"),
        'NOT-03: Tentativa corrigida avisa o Aluno com link para o resultado');
      (new App\Models\Attempt($dbA))->markGraded($submittedId, 1.0);
      check(count($notes($alunoB['id'], 'attempt_graded')) === 1, 'NOT-03: corrigir de novo não duplica o aviso');
      check(count($notes($alunoPendente['id'], 'enrollment_approved')) >= 1, 'NOT-03: Matrícula aprovada avisa o Aluno');
      check(count($notes($docenteA['id'], 'review_requested')) === 1, 'NOT-03: Pedido de revisão avisa o Docente');
      check(count($notes($alunoA['id'], 'review_answered')) === 2, 'NOT-03: Revisão de nota e nota mantida avisam o Aluno');

      // AGE-01 — Agenda do Aluno
      $agenda = httpAs($jarAlunoB, 'GET', "{$base}/student/agenda");
      check(
        $agenda['code'] === 200 && str_contains($agenda['body'], 'Exercício para avisar') && str_contains($agenda['body'], 'Vence em menos de 24 h')
          && str_contains($agenda['body'], '1 de 2 tentativas usadas'),
        'AGE-01: agenda lista a Publicação com prazo e tentativas usadas'
      );
      check(!str_contains($agenda['body'], 'Exercício HTTP A'), 'AGE-01: agenda não mostra Publicações de outras Turmas');
      $closedEx = $mkExercise($docenteB['id'], 'Exercício já encerrado', 'active', ['QN2']);
      $pdoA->exec("INSERT INTO exercise_turmas (exercise_id, turma_id, opens_at, closes_at, max_attempts)
                   VALUES ({$closedEx['id']}, {$turmaB['id']}, NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 1 DAY, 1)");
      $pdoA->exec("INSERT INTO attempts (exercise_id, student_id, turma_id, status) VALUES ({$notifyEx['id']}, {$alunoB['id']}, {$turmaB['id']}, 'submitted')");
      $agenda = httpAs($jarAlunoB, 'GET', "{$base}/student/agenda");
      check(!str_contains($agenda['body'], 'Exercício já encerrado'), 'AGE-01: agenda não mostra Publicação encerrada');
      check(str_contains($agenda['body'], '2 de 2 tentativas usadas'), 'AGE-01: Tentativa Enviada (ainda sem correção) conta como usada');
      check($page($jarDocenteA, '/student/agenda')['code'] === 302, 'AGE-01: agenda é só do Aluno');

      // PRA-01 — Modo prática: Temas do banco e correções do dia
      $alunoPratica = $mkUser('Aluno Pratica', 'student');
      $jarPratica = loginAs($base, $alunoPratica['email'], $pw);
      $pdoA->exec("INSERT INTO bank_themes (name) VALUES ('Tema de Pratica')");
      $praticaTheme = (int) $pdoA->lastInsertId();
      $pdoA->exec("INSERT INTO bank_exercises (title, status) VALUES ('Lista de pratica', 'active')");
      $praticaList = (int) $pdoA->lastInsertId();
      $pdoA->exec("INSERT INTO bank_exercise_themes (bank_exercise_id, theme_id) VALUES ({$praticaList}, {$praticaTheme})");
      $pdoA->exec("INSERT INTO bank_questions (bank_exercise_id, text, expected_answer_hint, max_score, order_index)
                   VALUES ({$praticaList}, 'Some os elementos de um vetor de inteiros', 'Percorrer o vetor acumulando', 10, 1),
                          ({$praticaList}, 'Encontre o maior elemento de um vetor', 'Guardar o maior visto', 10, 2)");
      $praticaQ1 = (int) $pdoA->lastInsertId(); // INSERT de várias linhas devolve o id da primeira
      $praticaQ2 = $praticaQ1 + 1;
      $pdoA->exec("INSERT INTO bank_exercises (title, status) VALUES ('Lista arquivada', 'archived')");
      $archivedList = (int) $pdoA->lastInsertId();
      $pdoA->exec("INSERT INTO bank_questions (bank_exercise_id, text, expected_answer_hint, max_score, order_index)
                   VALUES ({$archivedList}, 'Questao arquivada', 'x', 10, 1)");
      $archivedQ = (int) $pdoA->lastInsertId();

      $home = httpAs($jarPratica, 'GET', "{$base}/student/pratica");
      check($home['code'] === 200 && str_contains($home['body'], 'Tema de Pratica') && str_contains($home['body'], '0 de 10 correções hoje'),
        'PRA-01: Aluno vê os Temas do banco e as correções do dia');
      check($page($jarDocenteA, '/student/pratica')['code'] === 302, 'PRA-01: prática é só do Aluno');
      check(httpAs($jarPratica, 'GET', "{$base}/student/pratica/questao/{$archivedQ}")['code'] === 404, 'PRA-01: Questão de item arquivado não abre');

      // PRA-02 — Sortear Questão do Tema e corrigir na hora
      $draw = httpAs($jarPratica, 'GET', "{$base}/student/pratica/sortear?tema=" . rawurlencode('Tema de Pratica'));
      check($draw['code'] === 302 && preg_match('#Location: [^\r\n]*/student/pratica/questao/(\d+)#i', $draw['headers'], $m) === 1
        && in_array((int) $m[1], [$praticaQ1, $praticaQ2], true), 'PRA-02: sortear leva a uma Questão do Tema');
      $qPage = httpAs($jarPratica, 'GET', "{$base}/student/pratica/questao/{$praticaQ1}");
      check($qPage['code'] === 200 && str_contains($qPage['body'], 'Some os elementos de um vetor de inteiros'), 'PRA-02: página mostra o enunciado');
      file_put_contents($fakeAiLog, '');
      postAs($base, $jarPratica, "/student/pratica/questao/{$praticaQ1}", ['answer' => 'para cada elemento soma no acumulador']);
      $qPage = httpAs($jarPratica, 'GET', "{$base}/student/pratica/questao/{$praticaQ1}");
      $sent = (string) file_get_contents($fakeAiLog);
      check(str_contains($sent, 'Some os elementos de um vetor de inteiros') && str_contains($sent, 'para cada elemento soma no acumulador'),
        'PRA-02: a IA recebe o enunciado e a resposta');
      check(str_contains($qPage['body'], '6,0') && str_contains($qPage['body'], 'Faltou tratar o vetor vazio.')
        && str_contains($qPage['body'], 'Faltou um conceito essencial'),
        'PRA-02: Aluno vê na hora a Pontuação, o Feedback e os Motivos de desconto');
      check(str_contains(httpAs($jarPratica, 'GET', "{$base}/student/pratica")['body'], '1 de 10 correções hoje'), 'PRA-02: correção conta no limite do dia');
      $draw = httpAs($jarPratica, 'GET', "{$base}/student/pratica/sortear?tema=" . rawurlencode('Tema de Pratica'));
      check(str_contains($draw['headers'], "/student/pratica/questao/{$praticaQ2}"), 'PRA-02: sorteio prefere Questão ainda não praticada');
      check($countRows("SELECT COUNT(*) FROM attempts WHERE student_id = {$alunoPratica['id']}") === 0, 'PRA-02: prática não cria Tentativa');

      // PRA-03 — Falha da IA e limite diário
      $fail = postAs($base, $jarPratica, "/student/pratica/questao/{$praticaQ2}", ['answer' => 'FALHA_SIMULADA resposta guardada']);
      $qPage = httpAs($jarPratica, 'GET', "{$base}/student/pratica/questao/{$praticaQ2}");
      check(str_contains($qPage['body'], 'Não foi possível corrigir agora') && str_contains($qPage['body'], 'FALHA_SIMULADA resposta guardada')
        && $countRows("SELECT COUNT(*) FROM practice_answers WHERE student_id = {$alunoPratica['id']}") === 1,
        'PRA-03: falha da IA avisa, mantém o texto e não conta no limite');
      for ($i = 0; $i < 9; $i++) {
        $pdoA->exec("INSERT INTO practice_answers (student_id, bank_question_id, student_answer, score, max_score, feedback)
                     VALUES ({$alunoPratica['id']}, {$praticaQ1}, 'x', 1, 10, 'f')");
      }
      file_put_contents($fakeAiLog, '');
      postAs($base, $jarPratica, "/student/pratica/questao/{$praticaQ2}", ['answer' => 'decima primeira']);
      $qPage = httpAs($jarPratica, 'GET', "{$base}/student/pratica/questao/{$praticaQ2}");
      check(str_contains($qPage['body'], 'limite de 10 correções') && (string) file_get_contents($fakeAiLog) === ''
        && $countRows("SELECT COUNT(*) FROM practice_answers WHERE student_id = {$alunoPratica['id']}") === 10,
        'PRA-03: no limite diário a IA não é chamada');

      // PROG-01 — Progresso por Tema: Prática + Tentativas de Questões vindas do banco
      $alunoProg = $mkUser('Aluno Progresso', 'student');
      $jarProg = loginAs($base, $alunoProg['email'], $pw);
      $progImport = postAs($base, $jarDocenteA, '/teacher/bank/import', [
        'source' => 'bank',
        'source_id' => $praticaList,
        'question_ids' => [$praticaQ1, $praticaQ2],
        'title' => 'Prova importada do banco',
      ]);
      $progExercise = $draftIdFrom($progImport);
      $progQuestions = array_map('intval', array_column(
        $pdoA->query("SELECT id FROM questions WHERE exercise_id = {$progExercise} ORDER BY order_index")->fetchAll(), 'id'
      ));
      $pdoA->exec("UPDATE exercises SET status = 'active' WHERE id = {$progExercise}");
      $ownCopy = $draftIdFrom(postAs($base, $jarDocenteA, '/teacher/bank/import', [
        'source' => 'own', 'source_id' => $progExercise, 'question_ids' => $progQuestions, 'title' => 'Cópia da prova',
      ]));
      check(
        $countRows("SELECT COUNT(*) FROM questions WHERE exercise_id = {$ownCopy} AND bank_question_id IN ({$praticaQ1}, {$praticaQ2})") === 2,
        'PROG-01: copiar de um Exercício próprio mantém a origem no banco'
      );
      $pdoA->exec("INSERT INTO attempts (exercise_id, student_id, status, total_score) VALUES ({$progExercise}, {$alunoProg['id']}, 'graded', 17)");
      $progAttempt = (int) $pdoA->lastInsertId();
      $pdoA->exec("INSERT INTO answers (attempt_id, question_id, student_answer, ai_score, evaluated_at)
                   VALUES ({$progAttempt}, {$progQuestions[0]}, 'r1', 9, NOW()), ({$progAttempt}, {$progQuestions[1]}, 'r2', 8, NOW())");
      $pdoA->exec("INSERT INTO practice_answers (student_id, bank_question_id, student_answer, score, max_score, feedback)
                   VALUES ({$alunoProg['id']}, {$praticaQ1}, 'p', 9, 10, 'f')");
      // Correção pela metade (Tentativa ainda Enviada) não conta.
      $pdoA->exec("INSERT INTO attempts (exercise_id, student_id, status) VALUES ({$progExercise}, {$alunoProg['id']}, 'submitted')");
      $halfAttempt = (int) $pdoA->lastInsertId();
      $pdoA->exec("INSERT INTO answers (attempt_id, question_id, student_answer, ai_score, evaluated_at)
                   VALUES ({$halfAttempt}, {$progQuestions[0]}, 'r', 0, NOW())");

      $mkThemeList = function (string $theme, string $questionText) use ($pdoA): int {
        $pdoA->exec("INSERT INTO bank_themes (name) VALUES (" . $pdoA->quote($theme) . ")");
        $themeId = (int) $pdoA->lastInsertId();
        $pdoA->exec("INSERT INTO bank_exercises (title, status) VALUES (" . $pdoA->quote("Lista {$theme}") . ", 'active')");
        $listId = (int) $pdoA->lastInsertId();
        $pdoA->exec("INSERT INTO bank_exercise_themes (bank_exercise_id, theme_id) VALUES ({$listId}, {$themeId})");
        $pdoA->exec("INSERT INTO bank_questions (bank_exercise_id, text, expected_answer_hint, max_score, order_index)
                     VALUES ({$listId}, " . $pdoA->quote($questionText) . ", 'ref', 10, 1)");
        return (int) $pdoA->lastInsertId();
      };
      $weakQ = $mkThemeList('Tema Fraco', 'Questao do tema fraco');
      for ($i = 0; $i < 3; $i++) {
        $pdoA->exec("INSERT INTO practice_answers (student_id, bank_question_id, student_answer, score, max_score, feedback)
                     VALUES ({$alunoProg['id']}, {$weakQ}, 'p', 2, 10, 'f')");
      }
      $newQ = $mkThemeList('Tema Novo', 'Questao do tema novo');
      $pdoA->exec("INSERT INTO practice_answers (student_id, bank_question_id, student_answer, score, max_score, feedback)
                   VALUES ({$alunoProg['id']}, {$newQ}, 'p', 10, 10, 'f')");

      $progRow = static function (string $html, string $theme): string {
        return preg_match('#<tr>(?:(?!</tr>).)*' . preg_quote($theme, '#') . '(?:(?!</tr>).)*</tr>#s', $html, $m) ? $m[0] : '';
      };
      $progress = httpAs($jarProg, 'GET', "{$base}/student/progresso");
      $strong = $progRow($progress['body'], 'Tema de Pratica');
      check($progress['code'] === 200 && str_contains($strong, '87%') && str_contains($strong, '3 respostas corrigidas') && str_contains($strong, 'Ponto forte'),
        'PROG-01: Tema junta Prática e Tentativas de Questões importadas do banco (ponto forte ≥ 70%)');
      $weak = $progRow($progress['body'], 'Tema Fraco');
      check(str_contains($weak, '20%') && str_contains($weak, 'A melhorar') && str_contains($weak, '/student/pratica/sortear?tema=Tema%20Fraco'),
        'PROG-01: Tema abaixo de 50% aparece como "A melhorar", com atalho para praticar');
      check(str_contains($progRow($progress['body'], 'Tema Novo'), 'Poucos dados'), 'PROG-01: menos de 3 respostas corrigidas não classifica');
      check($page($jarDocenteA, '/student/progresso')['code'] === 302, 'PROG-01: progresso é só do Aluno');

      // PROG-02 — Migration liga Questões importadas antes da origem existir (texto idêntico)
      $legacy = $mkExercise($docenteA['id'], 'Exercício antigo', 'active', ['Questao do tema fraco']);
      $pdoA->exec("UPDATE questions SET expected_answer_hint = 'ref', bank_question_id = NULL WHERE id = {$legacy['questions'][0]}");
      $pdoA->exec("INSERT INTO attempts (exercise_id, student_id, status, total_score) VALUES ({$legacy['id']}, {$alunoProg['id']}, 'graded', 10)");
      $legacyAttempt = (int) $pdoA->lastInsertId();
      $pdoA->exec("INSERT INTO answers (attempt_id, question_id, student_answer, ai_score, evaluated_at)
                   VALUES ({$legacyAttempt}, {$legacy['questions'][0]}, 'r', 10, NOW())");
      applySchema($cfg, ROOT_PATH . '/database/migrations/027_questions_bank_origin.sql');
      $weak = $progRow(httpAs($jarProg, 'GET', "{$base}/student/progresso")['body'], 'Tema Fraco');
      check(str_contains($weak, '40%') && str_contains($weak, '4 respostas corrigidas'),
        'PROG-02: migration liga Questão importada antes (texto idêntico) e ela entra no progresso');

      // DES-01 — Desempenho da Turma: grade Alunos × Exercícios publicados
      $turmaDes = $mkTurma($docenteA['id'], 'Turma Desempenho');
      $desAlta = $mkUser('Aluna Nota Alta', 'student');
      $desBaixa = $mkUser('Aluno Nota Baixa', 'student');
      $desAusente = $mkUser('Aluno Sem Tentativa', 'student');
      $desFora = $mkUser('Aluno de Fora', 'student');
      $pdoA->exec("INSERT INTO student_turma (student_id, turma_id, status) VALUES
                   ({$desAlta['id']}, {$turmaDes['id']}, 'active'),
                   ({$desBaixa['id']}, {$turmaDes['id']}, 'active'),
                   ({$desAusente['id']}, {$turmaDes['id']}, 'active')");
      $desEx1 = $mkExercise($docenteA['id'], 'Desempenho Prova 1', 'active', ['Questao D1', 'Questao D2']);
      $desEx2 = $mkExercise($docenteA['id'], 'Desempenho Prova 2', 'active', ['Questao D3', 'Questao D4']);
      $pdoA->exec("INSERT INTO exercise_turmas (exercise_id, turma_id, opens_at, closes_at, max_attempts) VALUES
                   ({$desEx1['id']}, {$turmaDes['id']}, NOW() - INTERVAL 5 DAY, NOW() - INTERVAL 1 DAY, 0),
                   ({$desEx2['id']}, {$turmaDes['id']}, NOW() - INTERVAL 1 DAY, NOW() + INTERVAL 5 DAY, 0)");
      $desAttempt = function (array $student, array $exercise, string $status, ?float $total, array $answers) use ($pdoA, $turmaDes): void {
        $totalSql = $total === null ? 'NULL' : (string) $total;
        $pdoA->exec("INSERT INTO attempts (exercise_id, student_id, turma_id, status, total_score)
                     VALUES ({$exercise['id']}, {$student['id']}, {$turmaDes['id']}, '{$status}', {$totalSql})");
        $attemptId = (int) $pdoA->lastInsertId();
        foreach ($answers as $i => [$score, $reasons]) {
          $pdoA->exec("INSERT INTO answers (attempt_id, question_id, student_answer, ai_score, deduction_reasons_json, evaluated_at)
                       VALUES ({$attemptId}, {$exercise['questions'][$i]}, 'r', {$score}, " . $pdoA->quote(json_encode($reasons)) . ", NOW())");
        }
      };
      $desAttempt($desAlta, $desEx1, 'graded', 4.0, [[1.0, ['missing_concept']], [3.0, []]]);
      $desAttempt($desAlta, $desEx2, 'graded', 5.0, [[2.0, []], [3.0, []]]);
      $desAttempt($desBaixa, $desEx1, 'graded', 1.0, [[0.0, ['missing_concept', 'logic_error']], [1.0, ['logic_error']]]);
      $desAttempt($desBaixa, $desEx1, 'graded', 0.5, [[0.0, ['missing_concept']], [0.5, ['logic_error']]]);
      $desAttempt($desBaixa, $desEx2, 'submitted', null, []);
      $desAttempt($desFora, $desEx1, 'graded', 0.0, [[0.0, []], [0.0, []]]);
      // Tentativa em outra Turma não entra no desempenho desta.
      $pdoA->exec("INSERT INTO attempts (exercise_id, student_id, turma_id, status, total_score)
                   VALUES ({$desEx1['id']}, {$desAusente['id']}, {$turmaA['id']}, 'graded', 5)");

      $desRow = static function (string $html, string $needle): string {
        return preg_match('#<tr>(?:(?!</tr>).)*' . preg_quote($needle, '#') . '(?:(?!</tr>).)*</tr>#s', $html, $m) ? $m[0] : '';
      };
      $des = httpAs($jarDocenteA, 'GET', "{$base}/teacher/turmas/{$turmaDes['id']}/desempenho");
      $alta = $desRow($des['body'], 'Aluna Nota Alta');
      $baixa = $desRow($des['body'], 'Aluno Nota Baixa');
      check($des['code'] === 200 && str_contains($alta, '4,0') && str_contains($alta, '5,0') && str_contains($alta, '90%'),
        'DES-01: grade mostra a melhor nota por Exercício e a média do Aluno');
      check(str_contains($baixa, '1,0') && str_contains($baixa, 'Pendente') && str_contains($baixa, '20%') && str_contains($baixa, 'Abaixo da média'),
        'DES-01: Tentativa pendente e Aluno abaixo da média da Turma ficam destacados');
      check(str_contains($desRow($des['body'], 'Aluno Sem Tentativa'), 'Nunca tentou'), 'DES-01: Aluno que nunca tentou fica destacado');
      check(str_contains($des['body'], 'Média da Turma: 55%') && !str_contains($des['body'], 'Aluno de Fora'),
        'DES-01: média da Turma considera só os Alunos dela');
      check(httpAs($jarDocenteB, 'GET', "{$base}/teacher/turmas/{$turmaDes['id']}/desempenho")['code'] === 403, 'DES-01: outro Docente não vê a Turma');
      check($page($jarAlunoA, "/teacher/turmas/{$turmaDes['id']}/desempenho")['code'] === 302, 'DES-01: Aluno não acessa o desempenho');

      // DES-02 — Por Questão: média e Motivos de desconto mais comuns (melhor Tentativa de cada Aluno)
      $byQ = httpAs($jarDocenteA, 'GET', "{$base}/teacher/turmas/{$turmaDes['id']}/desempenho?exercicio={$desEx1['id']}");
      $q1 = $desRow($byQ['body'], 'Questao D1');
      $q2 = $desRow($byQ['body'], 'Questao D2');
      check(str_contains($q1, '25%') && str_contains($q1, 'Faltou um conceito essencial (2)') && str_contains($q1, 'Há um erro na lógica (1)'),
        'DES-02: Questão mostra a média e os Motivos de desconto mais comuns');
      check(str_contains($q2, '67%'), 'DES-02: média da Questão usa a melhor Tentativa de cada Aluno da Turma');

      // DES-03 — Exportar CSV das notas da Turma
      $csv = httpAs($jarDocenteA, 'GET', "{$base}/teacher/turmas/{$turmaDes['id']}/desempenho/csv");
      $csvRows = array_map(
        static fn(string $line): array => str_getcsv($line, ';', '"', ''),
        array_filter(explode("\n", str_replace("\r", '', ltrim($csv['body'], "\xEF\xBB\xBF"))))
      );
      check(
        $csv['code'] === 200 && str_contains($csv['headers'], 'text/csv')
          && in_array(['Aluno', 'E-mail', 'Desempenho Prova 1', 'Desempenho Prova 2', 'Média'], $csvRows, true)
          && in_array(['Aluna Nota Alta', $desAlta['email'], '4,0', '5,0', '90%'], $csvRows, true)
          && in_array(['Aluno Nota Baixa', $desBaixa['email'], '1,0', 'Pendente', '20%'], $csvRows, true)
          && !str_contains($csv['body'], 'Aluno de Fora'),
        'DES-03: CSV traz as notas da Turma no mesmo formato da tela'
      );
      check($auditCount('teacher.turma.export', $turmaDes['id']) === 1, 'DES-03: exportação é auditada');
      check(httpAs($jarDocenteB, 'GET', "{$base}/teacher/turmas/{$turmaDes['id']}/desempenho/csv")['code'] === 403, 'DES-03: outro Docente não exporta');

      // MAIL — Avisos por e-mail: fila + worker (driver "log" grava cada e-mail como JSON)
      $mailLog = tempnam(sys_get_temp_dir(), 'mail_log_');
      $runMailer = static function (array $env) use ($mailLog): int {
        $proc = proc_open(
          [PHP_BINARY, '-d', 'variables_order=EGPCS', ROOT_PATH . '/bin/send_notification_emails.php'],
          [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
          $pipes,
          ROOT_PATH,
          array_merge(getenv(), ['MAIL_DRIVER' => 'log', 'MAIL_LOG_PATH' => $mailLog, 'APP_URL' => 'https://algoia.test'], $env)
        );
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return proc_close($proc);
      };
      $mails = static function () use ($mailLog): array {
        $lines = array_filter(explode("\n", (string) file_get_contents($mailLog)));
        return array_values(array_map(static fn(string $l): array => json_decode($l, true) ?: [], $lines));
      };
      $mailsTo = static fn(string $email): array => array_values(array_filter($mails(), static fn(array $m): bool => ($m['to'] ?? '') === $email));
      $hasSubject = static fn(array $list, string $subject): bool => array_filter($list, static fn(array $m): bool => str_contains((string) ($m['subject'] ?? ''), $subject)) !== [];

      // MAIL-02 (antes do envio) — Aluno desliga o e-mail em Minha conta
      $optOut = postAs($base, $jarAlunoA, '/conta/avisos-email', []);
      $conta = httpAs($jarAlunoA, 'GET', "{$base}/conta");
      check($optOut['code'] === 302 && str_contains($conta['body'], 'id="email-notifications"')
        && preg_match('#id="email-notifications"[^>]*checked#', $conta['body']) !== 1,
        'MAIL-02: Aluno desliga os avisos por e-mail em Minha conta');

      // MAIL-03 — Decisão do cadastro docente
      $reqOk = $mkUser('Docente Aprovado', 'teacher');
      $reqNo = $mkUser('Docente Recusado', 'teacher');
      $pdoA->exec("UPDATE users SET status = 'pending', registration_source = 'teacher_public' WHERE id IN ({$reqOk['id']}, {$reqNo['id']})");
      postAs($base, $jarAdmin, "/admin/teacher-requests/{$reqOk['id']}/approve");
      postAs($base, $jarAdmin, "/admin/teacher-requests/{$reqNo['id']}/reject");

      // MAIL-04 — Pedido de Matrícula avisa o Docente
      postAs($base, $jarProg, '/student/turma/join', ['turma_key' => $turmaDes['key']]);
      check(count($notes($docenteA['id'], 'enrollment_requested')) === 1, 'MAIL-04: pedido de Matrícula vira Aviso para o Docente');

      // MAIL-05 — Prazo encerrando chega por e-mail mesmo sem abrir o app
      $flashEx = $mkExercise($docenteA['id'], 'Prova relampago', 'active', ['Questao R1']);
      $pdoA->exec("INSERT INTO exercise_turmas (exercise_id, turma_id, opens_at, closes_at, max_attempts)
                   VALUES ({$flashEx['id']}, {$turmaDes['id']}, NOW() - INTERVAL 1 HOUR, NOW() + INTERVAL 3 HOUR, 1)");

      // MAIL-06 — Aviso antigo (mais de 1 dia) não vira e-mail
      $pdoA->exec("INSERT INTO notifications (user_id, type, title, created_at)
                   VALUES ({$alunoPratica['id']}, 'exercise_published', 'Aviso antigo demais', NOW() - INTERVAL 3 DAY)");

      // MAIL-07 — Sem remetente configurado o worker não envia nada
      check($runMailer(['MAIL_FROM' => '']) === 2 && $mails() === [], 'MAIL-07: sem MAIL_FROM o worker sai com código 2 e não envia');

      check($runMailer(['MAIL_FROM' => 'nao-responda@algoia.test']) === 0, 'MAIL-01: worker de e-mail termina sem erro');
      $toB = $mailsTo($alunoB['email']);
      check(
        $hasSubject($toB, 'Correção concluída: Exercício para avisar')
          && array_filter($toB, static fn(array $m): bool => str_contains((string) $m['body'], "https://algoia.test/student/attempts/{$submittedId}/result")
            && str_contains((string) $m['body'], 'https://algoia.test/conta')) !== [],
        'MAIL-01: Aviso não lido vira e-mail com link completo e caminho para desligar'
      );
      check(!$hasSubject($toB, 'Novo exercício: Exercício para avisar'), 'MAIL-01: Aviso já lido no app não vira e-mail');
      check($mailsTo($alunoA['email']) === [], 'MAIL-02: quem desligou não recebe e-mail');
      check($hasSubject($mailsTo($reqOk['email']), 'Cadastro de docente aprovado'), 'MAIL-03: aprovação do cadastro docente chega por e-mail');
      check($hasSubject($mailsTo($reqNo['email']), 'Cadastro de docente não aprovado'), 'MAIL-03: recusa do cadastro docente chega por e-mail');
      check($hasSubject($mailsTo($docenteA['email']), 'Pedido de Matrícula na turma Turma Desempenho'), 'MAIL-04: Docente recebe o pedido de Matrícula por e-mail');
      check($hasSubject($mailsTo($desAusente['email']), 'Prazo encerrando: Prova relampago'), 'MAIL-05: Aluno recebe o prazo encerrando por e-mail sem abrir o app');
      check(!$hasSubject($mailsTo($alunoPratica['email']), 'Aviso antigo demais'), 'MAIL-06: Aviso antigo não vira e-mail');
      $sentOnce = count($mails());
      $runMailer(['MAIL_FROM' => 'nao-responda@algoia.test']);
      check(count($mails()) === $sentOnce, 'MAIL-01: rodar o worker de novo não repete e-mails');
      @unlink($mailLog);

      // EVO-01 — Histórico de evolução do Aluno: primeira x última Tentativa Corrigida
      $desAttempt($desAlta, $desEx1, 'graded', 5.0, [[2.0, []], [3.0, []]]);
      // Tentativa em andamento depois das Corrigidas não vira "última"; Aluno fora da Turma não entra.
      $desAttempt($desBaixa, $desEx1, 'in_progress', null, []);
      $desAttempt($desFora, $desEx1, 'graded', 4.0, [[2.0, []], [2.0, []]]);
      $jarAlta = loginAs($base, $desAlta['email'], $pw);
      $jarBaixa = loginAs($base, $desBaixa['email'], $pw);
      $evoAlta = httpAs($jarAlta, 'GET', "{$base}/student/progresso");
      $altaRow = $desRow($evoAlta['body'], 'Desempenho Prova 1');
      check($evoAlta['code'] === 200 && str_contains($altaRow, '4,0') && str_contains($altaRow, '5,0') && str_contains($altaRow, '+1,0')
        && str_contains($altaRow, 'Melhorou') && str_contains($altaRow, '2 Tentativas'),
        'EVO-01: Aluno vê a primeira e a última nota da Publicação e quanto melhorou');
      check(!str_contains($evoAlta['body'], 'Desempenho Prova 2'), 'EVO-01: Publicação com uma só Tentativa Corrigida não entra na evolução');
      $baixaRow = $desRow(httpAs($jarBaixa, 'GET', "{$base}/student/progresso")['body'], 'Desempenho Prova 1');
      check(str_contains($baixaRow, '1,0') && str_contains($baixaRow, '0,5') && str_contains($baixaRow, '-0,5') && str_contains($baixaRow, 'Caiu'),
        'EVO-01: queda de nota também aparece');

      foreach ([3.0, 1.0, 3.0] as $total) {
        $desAttempt($desAusente, $desEx2, 'graded', $total, []);
      }
      $ausenteRow = $desRow(httpAs(loginAs($base, $desAusente['email'], $pw), 'GET', "{$base}/student/progresso")['body'], 'Desempenho Prova 2');
      check(str_contains($ausenteRow, '3 Tentativas') && str_contains($ausenteRow, 'Manteve') && str_contains($ausenteRow, '0,0'),
        'EVO-01: compara a primeira com a última (não com a do meio); mesma nota aparece como "Manteve"');

      // EVO-02 — Docente vê a evolução de quem refez o Exercício
      $evoDoc = httpAs($jarDocenteA, 'GET', "{$base}/teacher/turmas/{$turmaDes['id']}/desempenho?exercicio={$desEx1['id']}");
      $evoSection = preg_match('#Evolução de quem refez.*#s', $evoDoc['body'], $em) ? $em[0] : '';
      check(str_contains($desRow($evoSection, 'Aluna Nota Alta'), '+1,0') && str_contains($desRow($evoSection, 'Aluno Nota Baixa'), '-0,5')
        && str_contains($evoSection, '1 de 2 melhoraram') && !str_contains($evoSection, 'Aluno de Fora'),
        'EVO-02: Docente vê primeira x última nota de quem refez e quantos melhoraram');

      // MUR-01 — Docente publica recado no mural; Alunos da Turma leem e recebem Aviso
      $postCount = fn(): int => $countRows("SELECT COUNT(*) FROM turma_posts WHERE turma_id = {$turmaDes['id']}");
      $r = postAs($base, $jarDocenteA, "/teacher/turmas/{$turmaDes['id']}/mural", [
        'title' => 'Prova remarcada', 'body' => "A prova passa para sexta.\nTragam calculadora.", 'pinned' => '1', 'expires_on' => '',
      ]);
      $firstPost = (int) $pdoA->query("SELECT MAX(id) FROM turma_posts")->fetchColumn();
      check($r['code'] === 302 && $postCount() === 1 && $auditCount('teacher.turma.post', $turmaDes['id']) === 1, 'MUR-01: Docente publica recado no mural (auditado)');
      check(str_contains(httpAs($jarDocenteA, 'GET', "{$base}/teacher/turmas/{$turmaDes['id']}")['body'], 'Prova remarcada'), 'MUR-01: recado aparece na página da Turma');
      $muralAlta = httpAs($jarAlta, 'GET', "{$base}/student/mural");
      check($muralAlta['code'] === 200 && str_contains($muralAlta['body'], 'Prova remarcada') && str_contains($muralAlta['body'], 'Tragam calculadora.')
        && str_contains($muralAlta['body'], 'Turma Desempenho'), 'MUR-01: Aluno da Turma lê o recado no Mural');
      $postNotes = $notes($desAlta['id'], 'turma_post');
      check(count($postNotes) === 1 && str_contains($postNotes[0]['title'], 'Prova remarcada') && str_ends_with((string) $postNotes[0]['link'], '/student/mural'),
        'MUR-01: recado vira Aviso para os Alunos da Turma');
      check(!str_contains(httpAs($jarAlunoA, 'GET', "{$base}/student/mural")['body'], 'Prova remarcada'), 'MUR-01: Aluno de outra Turma não vê o recado');

      // MUR-02 — Fixados primeiro; painel do Aluno mostra os recados mais recentes
      postAs($base, $jarDocenteA, "/teacher/turmas/{$turmaDes['id']}/mural", ['title' => 'Recado comum', 'body' => 'Lembrete geral.', 'expires_on' => '']);
      $muralAlta = httpAs($jarAlta, 'GET', "{$base}/student/mural")['body'];
      check(strpos($muralAlta, 'Prova remarcada') < strpos($muralAlta, 'Recado comum'), 'MUR-02: recado fixado vem antes do mais novo');
      check(str_contains(httpAs($jarAlta, 'GET', "{$base}/student/dashboard")['body'], 'Prova remarcada'), 'MUR-02: painel do Aluno mostra o mural');

      // MUR-03 — Expiração
      $r = postAs($base, $jarDocenteA, "/teacher/turmas/{$turmaDes['id']}/mural", [
        'title' => 'Data no passado', 'body' => 'x', 'expires_on' => date('Y-m-d', time() - 86400),
      ]);
      check($postCount() === 2, 'MUR-03: data de expiração no passado é recusada');
      $pdoA->exec("INSERT INTO turma_posts (turma_id, teacher_id, title, body, expires_at)
                   VALUES ({$turmaDes['id']}, {$docenteA['id']}, 'Recado vencido', 'x', NOW() - INTERVAL 1 HOUR)");
      check(!str_contains(httpAs($jarAlta, 'GET', "{$base}/student/mural")['body'], 'Recado vencido'), 'MUR-03: recado expirado some do Mural do Aluno');
      $turmaPage = httpAs($jarDocenteA, 'GET', "{$base}/teacher/turmas/{$turmaDes['id']}")['body'];
      check(str_contains($turmaPage, 'Recado vencido') && str_contains($turmaPage, 'Expirado'), 'MUR-03: Docente continua vendo o recado expirado, marcado');

      // MUR-04 — Validação e acesso
      postAs($base, $jarDocenteA, "/teacher/turmas/{$turmaDes['id']}/mural", ['title' => '', 'body' => 'sem título', 'expires_on' => '']);
      check($postCount() === 3, 'MUR-04: recado sem título é recusado');
      $r = postAs($base, $jarDocenteB, "/teacher/turmas/{$turmaDes['id']}/mural", ['title' => 'Intruso', 'body' => 'x', 'expires_on' => '']);
      check($r['code'] === 403 && $postCount() === 3, 'MUR-04: outro Docente não publica na Turma');
      check($page($jarAlta, '/teacher/turmas/' . $turmaDes['id'])['code'] === 302, 'MUR-04: Aluno não acessa a gestão do mural');

      // MUR-05 — Desafixar e excluir
      postAs($base, $jarDocenteA, "/teacher/turmas/{$turmaDes['id']}/mural/{$firstPost}/fixar");
      $muralAlta = httpAs($jarAlta, 'GET', "{$base}/student/mural")['body'];
      check(strpos($muralAlta, 'Recado comum') < strpos($muralAlta, 'Prova remarcada'), 'MUR-05: desafixar volta o recado para a ordem por data');
      $r = postAs($base, $jarDocenteB, "/teacher/turmas/{$turmaDes['id']}/mural/{$firstPost}/excluir");
      check($r['code'] === 403 && $postCount() === 3, 'MUR-05: outro Docente não exclui o recado');
      postAs($base, $jarDocenteA, "/teacher/turmas/{$turmaDes['id']}/mural/{$firstPost}/excluir");
      check($postCount() === 2 && !str_contains(httpAs($jarAlta, 'GET', "{$base}/student/mural")['body'], 'Prova remarcada')
        && $auditCount('teacher.turma.post.delete', $turmaDes['id']) === 1, 'MUR-05: Docente exclui o recado (auditado)');

      // ACH-01 — Conquistas: visíveis só para o Aluno, obtidas uma vez, com Aviso
      $alunoConq = $mkUser('Aluno Conquistas', 'student');
      $jarConq = loginAs($base, $alunoConq['email'], $pw);
      $earned = static function (string $html, string $label): bool {
        return preg_match('#class="conquista conquista--earned"(?:(?!</li>).)*' . preg_quote($label, '#') . '#s', $html) === 1;
      };
      $locked = static function (string $html, string $label): bool {
        return preg_match('#class="conquista conquista--locked"(?:(?!</li>).)*' . preg_quote($label, '#') . '#s', $html) === 1;
      };
      $conqPage = httpAs($jarConq, 'GET', "{$base}/student/progresso")['body'];
      check($locked($conqPage, 'Primeiros passos') && !$earned($conqPage, 'Primeiros passos'), 'ACH-01: sem respostas, conquistas aparecem bloqueadas');
      check($countRows("SELECT COUNT(*) FROM student_achievements WHERE student_id = {$alunoConq['id']}") === 0, 'ACH-01: nenhuma conquista sem atividade');

      // Primeira correção na Prática (IA falsa): Primeiros passos + Explorador do Tema, na hora
      postAs($base, $jarConq, "/student/pratica/questao/{$praticaQ1}", ['answer' => 'minha primeira resposta']);
      $achNotes = array_column($notes($alunoConq['id'], 'achievement'), 'title');
      check(in_array('Conquista desbloqueada: Primeiros passos', $achNotes, true) && in_array('Conquista desbloqueada: Explorador: Tema de Pratica', $achNotes, true),
        'ACH-01: primeira correção desbloqueia Primeiros passos e o Explorador do Tema, com Aviso');

      // ACH-02 — Sequência, volume, nota máxima, volta por cima e ponto forte
      $pdoA->exec("INSERT INTO practice_answers (student_id, bank_question_id, student_answer, score, max_score, feedback, created_at) VALUES
                   ({$alunoConq['id']}, {$praticaQ1}, 'x', 5, 10, 'f', NOW() - INTERVAL 1 DAY),
                   ({$alunoConq['id']}, {$praticaQ1}, 'x', 5, 10, 'f', NOW() - INTERVAL 2 DAY),
                   ({$alunoConq['id']}, {$praticaQ1}, 'x', 5, 10, 'f', NOW() - INTERVAL 5 DAY)");
      for ($i = 0; $i < 3; $i++) {
        $pdoA->exec("INSERT INTO practice_answers (student_id, bank_question_id, student_answer, score, max_score, feedback, created_at)
                     VALUES ({$alunoConq['id']}, {$newQ}, 'x', 9, 10, 'f', NOW() - INTERVAL 10 DAY)");
      }
      for ($i = 0; $i < 3; $i++) {
        $pdoA->exec("INSERT INTO practice_answers (student_id, bank_question_id, student_answer, score, max_score, feedback, created_at)
                     VALUES ({$alunoConq['id']}, {$weakQ}, 'x', 1, 10, 'f', NOW() - INTERVAL 20 DAY)");
      }
      $pdoA->exec("INSERT INTO attempts (exercise_id, student_id, status, total_score, submitted_at) VALUES
                   ({$desEx2['id']}, {$alunoConq['id']}, 'graded', 5.0, NOW() - INTERVAL 30 DAY),
                   ({$desEx1['id']}, {$alunoConq['id']}, 'graded', 2.0, NOW() - INTERVAL 31 DAY),
                   ({$desEx1['id']}, {$alunoConq['id']}, 'graded', 4.0, NOW() - INTERVAL 29 DAY)");
      $conqPage = httpAs($jarConq, 'GET', "{$base}/student/progresso")['body'];
      check($earned($conqPage, 'Sequência de 3 dias') && $locked($conqPage, 'Sequência de 7 dias'), 'ACH-02: 3 dias seguidos desbloqueiam a sequência de 3, não a de 7');
      check($earned($conqPage, 'Praticante') && $locked($conqPage, 'Praticante dedicado'), 'ACH-02: 10 correções de prática desbloqueiam Praticante, não a de 50');
      check($earned($conqPage, 'Nota máxima'), 'ACH-02: nota máxima em um Exercício desbloqueia a conquista');
      check($earned($conqPage, 'Volta por cima'), 'ACH-02: melhorar a nota ao refazer desbloqueia Volta por cima');
      check($earned($conqPage, 'Ponto forte'), 'ACH-02: Tema com 70% ou mais desbloqueia Ponto forte');
      $mkThemeList('Tema Intocado', 'Questao do tema intocado');
      $conqPage = httpAs($jarConq, 'GET', "{$base}/student/progresso")['body'];
      check($earned($conqPage, 'Explorador: Tema Fraco') && $locked($conqPage, 'Explorador: Tema Intocado'), 'ACH-02: Explorador por Tema só onde houve resposta corrigida');

      // Correção de Tentativa (worker) também desbloqueia, sem abrir o app
      $alunoGrad = $mkUser('Aluno Corrigido', 'student');
      $pdoA->exec("INSERT INTO attempts (exercise_id, student_id, status) VALUES ({$desEx2['id']}, {$alunoGrad['id']}, 'submitted')");
      $gradAttempt = (int) $pdoA->lastInsertId();
      $pdoA->exec("INSERT INTO answers (attempt_id, question_id, student_answer, ai_score, evaluated_at)
                   VALUES ({$gradAttempt}, {$desEx2['questions'][0]}, 'r', 1, NOW())");
      (new App\Models\Attempt($dbA))->markGraded($gradAttempt, 1.0);
      check(in_array('Conquista desbloqueada: Primeiros passos', array_column($notes($alunoGrad['id'], 'achievement'), 'title'), true),
        'ACH-02: Tentativa Corrigida desbloqueia conquista na hora da correção');

      // Sequência contada também por Tentativas enviadas (sem Prática)
      $pdoA->exec("UPDATE attempts SET submitted_at = NOW() WHERE id = {$gradAttempt}");
      $pdoA->exec("INSERT INTO attempts (exercise_id, student_id, status, submitted_at) VALUES
                   ({$desEx2['id']}, {$alunoGrad['id']}, 'submitted', NOW() - INTERVAL 1 DAY),
                   ({$desEx2['id']}, {$alunoGrad['id']}, 'submitted', NOW() - INTERVAL 2 DAY)");
      $gradPage = httpAs(loginAs($base, $alunoGrad['email'], $pw), 'GET', "{$base}/student/progresso")['body'];
      check($earned($gradPage, 'Sequência de 3 dias'), 'ACH-02: Tentativas enviadas em dias seguidos contam na sequência');
      check($locked(httpAs($jarPratica, 'GET', "{$base}/student/progresso")['body'], 'Ponto forte'), 'ACH-02: Tema só abaixo de 50% não dá Ponto forte');

      // ACH-03 — Idempotente e privado
      httpAs($jarConq, 'GET', "{$base}/student/progresso");
      check(count(array_filter($notes($alunoConq['id'], 'achievement'), static fn(array $n): bool => $n['title'] === 'Conquista desbloqueada: Primeiros passos')) === 1,
        'ACH-03: conquista não se repete nem gera Aviso de novo');
      check(!$earned(httpAs($jarPratica, 'GET', "{$base}/student/progresso")['body'], 'Volta por cima'), 'ACH-03: conquistas são de cada Aluno');

      // MAIL-08 — Driver SMTP (servidor SMTP falso local, sem TLS)
      $smtpPort = $port + 2000;
      $smtpLog = tempnam(sys_get_temp_dir(), 'fake_smtp_');
      $smtpProc = proc_open(
        [PHP_BINARY, ROOT_PATH . '/bin/fixtures/fake_smtp_server.php', (string) $smtpPort],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $smtpPipes,
        ROOT_PATH,
        array_merge(getenv(), ['FAKE_SMTP_LOG' => $smtpLog])
      );
      for ($i = 0; $i < 30 && @fsockopen('127.0.0.1', $smtpPort) === false; $i++) {
        usleep(100_000);
      }
      $smtpEnv = static fn(string $password): array => [
        'MAIL_DRIVER' => 'smtp', 'MAIL_FROM' => 'no-reply@algoia.test', 'MAIL_FROM_NAME' => 'AlgoIA',
        'MAIL_SMTP_HOST' => '127.0.0.1', 'MAIL_SMTP_PORT' => (string) $smtpPort, 'MAIL_SMTP_ENCRYPTION' => 'none',
        'MAIL_SMTP_USER' => 'no-reply@algoia.test', 'MAIL_SMTP_PASSWORD' => $password,
      ];
      $pdoA->exec("INSERT INTO notifications (user_id, type, title, body, link) VALUES
                   ({$desAlta['id']}, 'exercise_published', 'Teste SMTP do AlgoIA: ação', 'Corpo com acentuação.\n.linha que começa com ponto', '/student/mural')");
      $smtpNote = (int) $pdoA->lastInsertId();
      $noteState = fn(): array => $pdoA->query("SELECT emailed_at, email_attempts FROM notifications WHERE id = {$smtpNote}")->fetch();

      check($runMailer($smtpEnv('senha-errada')) === 1 && $noteState()['emailed_at'] === null && (int) $noteState()['email_attempts'] === 1,
        'MAIL-08: senha SMTP errada falha (código 1) sem marcar como enviado');
      check($runMailer($smtpEnv('senha-certa')) === 0 && $noteState()['emailed_at'] !== null, 'MAIL-08: envio por SMTP autenticado marca o Aviso como enviado');
      $smtpMsgs = array_values(array_filter(array_map(
        static fn(string $l): array => json_decode($l, true) ?: [],
        array_filter(explode("\n", (string) file_get_contents($smtpLog)))
      ), static fn(array $m): bool => in_array($desAlta['email'], $m['to'] ?? [], true)));
      $smtpMsg = null;
      foreach ($smtpMsgs as $m) {
        [$head, $body] = array_pad(explode("\r\n\r\n", (string) $m['data'], 2), 2, '');
        $head = str_replace("\r\n", "\n", preg_replace("/\r\n[ \t]+/", ' ', $head));
        $subject = preg_match('/^Subject: (.*)$/mi', $head, $sm) ? mb_decode_mimeheader(trim($sm[1])) : '';
        if ($subject === 'Teste SMTP do AlgoIA: ação') {
          $smtpMsg = ['head' => $head, 'body' => base64_decode(preg_replace('/\s+/', '', $body)), 'm' => $m];
        }
      }
      check(
        $smtpMsg !== null && $smtpMsg['m']['auth_user'] === 'no-reply@algoia.test' && $smtpMsg['m']['from'] === 'no-reply@algoia.test'
          && str_contains($smtpMsg['head'], 'From: ') && str_contains($smtpMsg['head'], '<no-reply@algoia.test>')
          && preg_match('/^Message-ID: <[^@>]+@algoia\.test>$/mi', $smtpMsg['head']) === 1 && preg_match('/^Date: /mi', $smtpMsg['head']) === 1,
        'MAIL-08: mensagem SMTP com autenticação, remetente, Message-ID e Date'
      );
      check($smtpMsg !== null && str_contains($smtpMsg['body'], 'Corpo com acentuação.') && str_contains($smtpMsg['body'], '.linha que começa com ponto')
        && str_contains($smtpMsg['body'], 'https://algoia.test/student/mural'), 'MAIL-08: corpo chega íntegro (UTF-8 e linha iniciada por ponto)');
      if (is_resource($smtpProc)) {
        proc_terminate($smtpProc);
        proc_close($smtpProc);
      }
      @unlink($smtpLog);

      // RANK-01 — Ranking da Turma: desligado por padrão; Docente escolhe o período
      $turmaRank = $mkTurma($docenteA['id'], 'Turma Ranking');
      $pdoA->exec("UPDATE turmas SET created_at = NOW() - INTERVAL 60 DAY WHERE id = {$turmaRank['id']}");
      $rankers = [];
      foreach (['Um', 'Dois', 'Tres', 'Quatro', 'Cinco', 'Seis'] as $n) {
        $rankers[$n] = $mkUser("Ranking {$n}", 'student');
        $pdoA->exec("INSERT INTO student_turma (student_id, turma_id, status) VALUES ({$rankers[$n]['id']}, {$turmaRank['id']}, 'active')");
      }
      $practiced = function (array $student, int $questionId, float $score, string $when = 'NOW()') use ($pdoA): void {
        $pdoA->exec("INSERT INTO practice_answers (student_id, bank_question_id, student_answer, score, max_score, feedback, created_at)
                     VALUES ({$student['id']}, {$questionId}, 'x', {$score}, 10, 'f', {$when})");
      };
      // Semana atual: Um 20 (10 + 10; a 2ª correção da mesma Questão não soma), Dois 18, Tres 15, Quatro 12, Cinco 8, Seis 5
      $practiced($rankers['Um'], $praticaQ1, 10); $practiced($rankers['Um'], $praticaQ1, 10); $practiced($rankers['Um'], $praticaQ2, 10);
      $practiced($rankers['Dois'], $praticaQ1, 9); $practiced($rankers['Dois'], $praticaQ2, 9);
      $practiced($rankers['Tres'], $praticaQ1, 6); $practiced($rankers['Tres'], $praticaQ1, 8); $practiced($rankers['Tres'], $praticaQ2, 7);
      $practiced($rankers['Quatro'], $newQ, 7); $practiced($rankers['Quatro'], $weakQ, 5);
      $practiced($rankers['Cinco'], $newQ, 8); $practiced($rankers['Cinco'], $praticaQ2, 4); // 12, empata com Quatro
      $practiced($rankers['Seis'], $weakQ, 5);
      // Semana passada: Cinco faz 30 (campeão da semana passada); 40 dias atrás: Seis faz 100
      $practiced($rankers['Cinco'], $praticaQ1, 10, 'NOW() - INTERVAL 7 DAY'); $practiced($rankers['Cinco'], $praticaQ2, 10, 'NOW() - INTERVAL 7 DAY');
      $practiced($rankers['Cinco'], $weakQ, 10, 'NOW() - INTERVAL 7 DAY');
      foreach ([$praticaQ1, $praticaQ2, $newQ, $weakQ] as $q) {
        $practiced($rankers['Seis'], $q, 10, 'NOW() - INTERVAL 40 DAY');
      }
      // Aluno ativo de OUTRA Turma, com muitos pontos nesta semana, não entra neste ranking
      foreach ([$praticaQ1, $praticaQ2, $newQ, $weakQ] as $q) {
        $practiced($desAlta, $q, 10);
      }
      $jarSeis = loginAs($base, $rankers['Seis']['email'], $pw);
      $jarTres = loginAs($base, $rankers['Tres']['email'], $pw);
      $rankPage = fn(string $jar): string => httpAs($jar, 'GET', "{$base}/student/ranking")['body'];
      check(str_contains($rankPage($jarSeis), 'Turma Ranking') && !str_contains($rankPage($jarSeis), 'Ranking Um'),
        'RANK-01: ranking começa desligado (Aluno não vê colegas)');

      $r = postAs($base, $jarDocenteA, "/teacher/turmas/{$turmaRank['id']}/ranking", ['ranking_mode' => 'weekly']);
      check($r['code'] === 302 && $auditCount('teacher.turma.ranking', $turmaRank['id']) === 1, 'RANK-01: Docente liga o ranking semanal (auditado)');
      $r = postAs($base, $jarDocenteB, "/teacher/turmas/{$turmaRank['id']}/ranking", ['ranking_mode' => 'all']);
      check($r['code'] === 403, 'RANK-01: outro Docente não altera o ranking');

      // RANK-02 — Top 5 + própria posição; melhor nota por Questão; campeão da semana passada
      $seis = $rankPage($jarSeis);
      $pos = static fn(string $html, string $name): int|false => strpos($html, $name);
      check($pos($seis, 'Ranking Um') !== false && $pos($seis, 'Ranking Um') < $pos($seis, 'Ranking Dois')
        && $pos($seis, 'Ranking Dois') < $pos($seis, 'Ranking Tres') && $pos($seis, 'Ranking Cinco') !== false,
        'RANK-02: top 5 em ordem de pontos');
      check(!str_contains($seis, 'Aluna Nota Alta'), 'RANK-02: só Alunos desta Turma entram no ranking');
      check(substr_count($seis, '<span class="ranking-row__pos">4º</span>') === 2 && str_contains($seis, 'Você está em 6º de 6'),
        'RANK-02: empate divide a posição (1, 2, 3, 4, 4, 6)');
      check(str_contains($seis, '20,0 pts') && !str_contains($seis, '30,0 pts'), 'RANK-02: só a melhor nota de cada Questão conta');
      check(str_contains($seis, 'Você está em 6º de 6') && !preg_match('#<li[^>]*class="ranking-row[^"]*"[^>]*>(?:(?!</li>).)*Ranking Seis#s', $seis),
        'RANK-02: fora do top 5, o Aluno vê só a própria posição');
      check(str_contains($seis, 'Campeão da semana passada: Ranking Cinco'), 'RANK-02: mostra o campeão do período anterior');

      // RANK-03 — Anônimo para os colegas, nome real para o Docente
      $r = postAs($base, $jarTres, '/conta/ranking', ['ranking_anonymous' => '1']);
      $seis = $rankPage($jarSeis);
      check($r['code'] === 302 && !str_contains($seis, 'Ranking Tres') && str_contains($seis, 'Aluno anônimo'), 'RANK-03: Aluno pode aparecer como anônimo');
      check(str_contains($rankPage($jarTres), 'Você está em 3º de 6'), 'RANK-03: anônimo continua vendo a própria posição');
      $docRank = httpAs($jarDocenteA, 'GET', "{$base}/teacher/turmas/{$turmaRank['id']}")['body'];
      check(str_contains($docRank, 'Ranking Tres') && str_contains($docRank, 'Ranking Seis'), 'RANK-03: Docente vê o ranking completo com nomes');

      // RANK-04 — Desde o início da Turma: pontos antigos contam
      postAs($base, $jarDocenteA, "/teacher/turmas/{$turmaRank['id']}/ranking", ['ranking_mode' => 'all']);
      check(str_contains($rankPage($jarSeis), 'Você está em 1º de 6'), 'RANK-04: período "desde o início" inclui pontos antigos');
      postAs($base, $jarDocenteA, "/teacher/turmas/{$turmaRank['id']}/ranking", ['ranking_mode' => 'monthly']);
      check(!str_contains($rankPage($jarSeis), 'Você está em 1º de 6'), 'RANK-04: período mensal não inclui pontos de 40 dias atrás');
      $r = postAs($base, $jarDocenteA, "/teacher/turmas/{$turmaRank['id']}/ranking", ['ranking_mode' => 'diario']);
      check($r['code'] === 302 && $countRows("SELECT COUNT(*) FROM turmas WHERE id = {$turmaRank['id']} AND ranking_mode = 'monthly'") === 1, 'RANK-04: período inválido é recusado');

      // IA-01 — Cada correção na Prática registra tokens e custo (IA falsa devolve 1000 + 200 tokens)
      $aiRows = fn(string $where): array => $pdoA->query("SELECT * FROM ai_usage_logs WHERE {$where} ORDER BY id")->fetchAll();
      $alunoIa = $mkUser('Aluno Uso IA', 'student');
      $jarIa = loginAs($base, $alunoIa['email'], $pw);
      postAs($base, $jarIa, "/student/pratica/questao/{$praticaQ1}", ['answer' => 'resposta para medir uso']);
      $iaPractice = $aiRows("student_id = {$alunoIa['id']}");
      check(
        count($iaPractice) === 1 && $iaPractice[0]['kind'] === 'practice' && $iaPractice[0]['status'] === 'ok'
          && (int) $iaPractice[0]['prompt_tokens'] === 1000 && (int) $iaPractice[0]['completion_tokens'] === 200
          && (int) $iaPractice[0]['bank_question_id'] === $praticaQ1 && $iaPractice[0]['teacher_id'] === null
          && abs((float) $iaPractice[0]['cost_usd'] - 0.0045) < 0.000001,
        'IA-01: correção na Prática registra tokens e custo (preço padrão US$ 2,50 / 10,00 por 1M)'
      );

      // IA-02 — Worker de correção registra Docente, Turma, Exercício e Tentativa
      $iaEx = $mkExercise($docenteA['id'], 'Exercicio medido', 'active', ['Questao IA1', 'Questao IA2']);
      $pdoA->exec("INSERT INTO attempts (exercise_id, student_id, turma_id, status, submitted_at)
                   VALUES ({$iaEx['id']}, {$desAusente['id']}, {$turmaDes['id']}, 'submitted', NOW())");
      $iaAttempt = (int) $pdoA->lastInsertId();
      $pdoA->exec("INSERT INTO answers (attempt_id, question_id, student_answer) VALUES
                   ({$iaAttempt}, {$iaEx['questions'][0]}, 'resposta um'), ({$iaAttempt}, {$iaEx['questions'][1]}, 'resposta dois')");
      $pdoA->exec("INSERT INTO grading_jobs (attempt_id) VALUES ({$iaAttempt})");
      $workerProc = proc_open(
        [PHP_BINARY, '-d', 'variables_order=EGPCS', ROOT_PATH . '/bin/process_grading_jobs.php', '50'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $workerPipes,
        ROOT_PATH
      );
      stream_get_contents($workerPipes[1]);
      stream_get_contents($workerPipes[2]);
      fclose($workerPipes[1]);
      fclose($workerPipes[2]);
      proc_close($workerProc);
      $iaGrading = $aiRows("attempt_id = {$iaAttempt}");
      check(
        count($iaGrading) === 2 && $iaGrading[0]['kind'] === 'grading' && (int) $iaGrading[0]['teacher_id'] === $docenteA['id']
          && (int) $iaGrading[0]['turma_id'] === $turmaDes['id'] && (int) $iaGrading[0]['exercise_id'] === $iaEx['id']
          && (int) $iaGrading[0]['student_id'] === $desAusente['id'] && $iaGrading[0]['answer_id'] !== null,
        'IA-02: correção de Tentativa pelo worker registra Docente, Turma, Exercício e Resposta'
      );
      check(
        $countRows("SELECT COUNT(*) FROM attempts WHERE id = {$iaAttempt} AND status = 'graded'") === 1
          && $countRows("SELECT COUNT(*) FROM grading_jobs WHERE attempt_id = {$iaAttempt} AND status = 'completed'") === 1,
        'GRADE-01: worker conclui a correção mesmo renovando o lease no mesmo segundo do claim'
      );

      // IA-03 — Painel do Admin: totais, US$/R$, por Docente e Turma; filtros; só Admin
      $iaPage = httpAs($jarAdmin, 'GET', "{$base}/admin/ia?period=30d");
      check($iaPage['code'] === 200 && str_contains($iaPage['body'], 'Uso da IA') && str_contains($iaPage['body'], 'US$')
        && str_contains($iaPage['body'], 'R$') && str_contains($iaPage['body'], '<td>Turma Desempenho</td>')
        && str_contains($iaPage['body'], 'id="ai-usage-data"'), 'IA-03: Admin vê o painel com custos, Turmas e dados dos gráficos');
      $filtered = httpAs($jarAdmin, 'GET', "{$base}/admin/ia?period=30d&teacher_id={$docenteB['id']}")['body'];
      check(!str_contains($filtered, '<td>Turma Desempenho</td>'), 'IA-03: filtro por Docente restringe os dados');
      check($page($jarDocenteA, '/admin/ia')['code'] !== 200 && $page($jarIa, '/admin/ia')['code'] !== 200, 'IA-03: só o Admin acessa o painel');

      // IA-04 — Preço novo vale só para chamadas novas (auditado)
      $r = postAs($base, $jarAdmin, '/admin/ia/configuracoes', [
        'price_input' => '5', 'price_output' => '20', 'usd_brl_rate' => '6', 'monthly_budget_brl' => '0',
      ]);
      check($r['code'] === 302 && $countRows("SELECT COUNT(*) FROM audit_logs WHERE action = 'admin.ai_settings.update'") === 1, 'IA-04: Admin altera preços e câmbio (auditado)');
      postAs($base, $jarIa, "/student/pratica/questao/{$praticaQ2}", ['answer' => 'outra resposta']);
      $iaPractice = $aiRows("student_id = {$alunoIa['id']}");
      check(count($iaPractice) === 2 && abs((float) $iaPractice[0]['cost_usd'] - 0.0045) < 0.000001
        && abs((float) $iaPractice[1]['cost_usd'] - 0.009) < 0.000001, 'IA-04: custo antigo não muda; chamada nova usa o preço novo');

      // IA-05 — Orçamento: Aviso aos Admins ao cruzar 80% e 100%, uma vez por mês e por limite
      $pdoA->exec("UPDATE ai_usage_logs SET created_at = created_at - INTERVAL 40 DAY");
      postAs($base, $jarAdmin, '/admin/ia/configuracoes', [
        'price_input' => '5', 'price_output' => '20', 'usd_brl_rate' => '6', 'monthly_budget_brl' => '0.06',
      ]);
      $budgetNotes = fn(): array => array_column($notes($admin['id'], 'ai_budget'), 'title');
      $alunoIa2 = $mkUser('Aluno Uso IA 2', 'student');
      $jarIa2 = loginAs($base, $alunoIa2['email'], $pw);
      postAs($base, $jarIa2, "/student/pratica/questao/{$praticaQ1}", ['answer' => 'primeira']); // R$ 0,054 = 90%
      $afterFirst = $budgetNotes();
      postAs($base, $jarIa2, "/student/pratica/questao/{$praticaQ2}", ['answer' => 'segunda']); // 180%
      postAs($base, $jarIa2, "/student/pratica/questao/{$newQ}", ['answer' => 'terceira']);
      $afterAll = $budgetNotes();
      check(count($afterFirst) === 1 && str_contains($afterFirst[0], '80%'), 'IA-05: passar de 80% do orçamento avisa os Admins');
      check(count($afterAll) === 2 && count(array_filter($afterAll, static fn(string $t): bool => str_contains($t, '100%'))) === 1,
        'IA-05: passar de 100% avisa uma vez; novas chamadas não repetem o aviso');

      postAs($base, $jarAdmin, '/admin/ia/configuracoes', [
        'price_input' => 'abc', 'price_output' => '-1', 'usd_brl_rate' => '6', 'monthly_budget_brl' => '0.06',
      ]);
      check($countRows("SELECT COUNT(*) FROM system_settings WHERE setting_key = 'ai_price_input_per_1m_usd' AND setting_value = '5'") === 1,
        'IA-04: valores inválidos são recusados sem alterar a configuração');

      // IA-06 — Falha da IA vira log de erro sem derrubar a Prática; CSV exporta
      $alunoIa3 = $mkUser('Aluno Uso IA 3', 'student');
      $jarIa3 = loginAs($base, $alunoIa3['email'], $pw);
      postAs($base, $jarIa3, "/student/pratica/questao/{$praticaQ1}", ['answer' => 'FALHA_SIMULADA aqui']);
      $iaFail = $aiRows("student_id = {$alunoIa3['id']}");
      check(count($iaFail) === 1 && $iaFail[0]['status'] === 'error' && (int) $iaFail[0]['prompt_tokens'] === 0
        && (float) $iaFail[0]['cost_usd'] === 0.0, 'IA-06: falha da IA registra log de erro, sem custo');
      $iaCsv = httpAs($jarAdmin, 'GET', "{$base}/admin/ia/export?period=7d");
      check($iaCsv['code'] === 200 && str_contains($iaCsv['headers'], 'text/csv') && str_contains($iaCsv['body'], 'tipo;')
        && str_contains($iaCsv['body'], 'Aluno Uso IA 3'), 'IA-06: exportação CSV do uso da IA');

      // EXC-02 — Lista de Exercícios: excluir pela grade e rótulo da coluna Turmas
      $listReady = $mkExercise($docenteA['id'], 'Pronto na lista', 'ready', ['QL1']);
      $listDraft = $mkExercise($docenteA['id'], 'Rascunho na lista', 'draft', ['QL2']);
      $list = $page($jarDocenteA, '/teacher/exercises');
      $deleteAction = fn(int $id): string => "/teacher/exercises/{$id}/delete";
      $rowOf = static function (string $html, string $title): string {
        return preg_match('#<tr>(?:(?!</tr>).)*' . preg_quote($title, '#') . '(?:(?!</tr>).)*</tr>#s', $html, $m) ? $m[0] : '';
      };
      check(
        str_contains($list['body'], $deleteAction($listReady['id'])) && str_contains($list['body'], $deleteAction($listDraft['id'])),
        'EXC-02: lista mostra "Excluir" para Rascunho e Pronto para publicar sem Publicação'
      );
      check(!str_contains($list['body'], $deleteAction($exA)), 'EXC-02: lista não mostra "Excluir" para Exercício Publicado');
      check(str_contains($rowOf($list['body'], 'Pronto na lista'), 'Aguardando publicação'), 'EXC-02: Pronto para publicar mostra "Aguardando publicação" na coluna Turmas');
      check(str_contains($rowOf($list['body'], 'Rascunho na lista'), 'Pendente de finalização'), 'EXC-02: Rascunho continua "Pendente de finalização"');
      $del = postAs($base, $jarDocenteA, $deleteAction($listReady['id']));
      $after = httpAs($jarDocenteA, 'GET', "{$base}/teacher/exercises");
      check(!$exists($listReady['id']) && str_contains($after['body'], 'Exercício excluído'), 'EXC-02: excluir pela lista confirma com mensagem de sucesso');
    }

    proc_terminate($serverProcess);
    proc_close($serverProcess);
  }

  if (is_resource($fakeAiProcess)) {
    proc_terminate($fakeAiProcess);
    proc_close($fakeAiProcess);
  }
  @unlink($fakeAiLog);
}

// ═══════════════════════════════════════════════════════════════════════════
// SEED-ALG — seed do banco de algoritmos (300 Questões) como processo real
// ═══════════════════════════════════════════════════════════════════════════
// Fica depois dos testes HTTP: os Temas do seed (ex.: "Vetores") encheriam a
// primeira página dos filtros usados pelos cenários BANCO-0x.

$runSeed = static function (array $args): array {
  $cmd = array_merge(
    [PHP_BINARY, '-d', 'variables_order=EGPCS', ROOT_PATH . '/database/seeds/003_seed_banco_algoritmos.php'],
    $args
  );
  $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT_PATH);
  $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
  fclose($pipes[1]);
  fclose($pipes[2]);
  return ['code' => proc_close($proc), 'output' => $out];
};

$pdoA->exec("INSERT INTO users (name, email, password_hash, role, status) VALUES ('Docente Seed', 'docente.seed." . bin2hex(random_bytes(3)) . "@test.local', 'x', 'teacher', 'active')");
$seedTeacherId = (int) $pdoA->lastInsertId();
$seedBankCount = fn(): int => (int) $pdoA->query("SELECT COUNT(*) FROM bank_exercises WHERE status = 'active' AND title LIKE '% — Lista %'")->fetchColumn();
$seedOwnCount  = fn(): int => (int) $pdoA->query("SELECT COUNT(*) FROM exercises WHERE teacher_id = {$seedTeacherId} AND status = 'ready'")->fetchColumn();

$bad = $runSeed(['--teacher-id=999999']);
check($bad['code'] !== 0 && $seedBankCount() === 0, 'SEED-ALG: Docente inexistente é recusado sem criar nada');

$first = $runSeed(["--teacher-id={$seedTeacherId}"]);
check($first['code'] === 0, 'SEED-ALG: seed termina com sucesso (' . trim(substr($first['output'], -160)) . ')');
check($seedBankCount() === 60, 'SEED-ALG: 60 Exercícios ativos no banco institucional (obtido ' . $seedBankCount() . ')');
check(
  (int) $pdoA->query("SELECT COUNT(*) FROM bank_questions bq JOIN bank_exercises be ON be.id = bq.bank_exercise_id WHERE be.title LIKE '% — Lista %'")->fetchColumn() === 300,
  'SEED-ALG: 300 Questões no banco institucional'
);
check(
  (int) $pdoA->query("SELECT COUNT(DISTINCT bt.name) FROM bank_themes bt JOIN bank_exercise_themes bet ON bet.theme_id = bt.id
                      WHERE bt.name IN ('Variáveis', 'Estruturas de Decisão', 'Estruturas de Repetição', 'Vetores', 'Matrizes', 'Modularização')")->fetchColumn() === 6,
  'SEED-ALG: os 6 tópicos viram Temas do banco'
);
check(
  $seedOwnCount() === 60
    && (int) $pdoA->query("SELECT COUNT(*) FROM questions q JOIN exercises e ON e.id = q.exercise_id WHERE e.teacher_id = {$seedTeacherId}")->fetchColumn() === 300,
  'SEED-ALG: 60 Exercícios Concluídos (300 Questões) na aba "Meus" do Docente'
);
check(
  (int) $pdoA->query("SELECT COUNT(DISTINCT max_score) FROM questions q JOIN exercises e ON e.id = q.exercise_id WHERE e.teacher_id = {$seedTeacherId}")->fetchColumn() === 3,
  'SEED-ALG: Pontuações 1, 2 e 3 presentes'
);

$again = $runSeed(["--teacher-id={$seedTeacherId}"]);
check($again['code'] === 0 && $seedBankCount() === 60 && $seedOwnCount() === 60, 'SEED-ALG: rodar de novo não duplica nada');

// ── Limpeza ──────────────────────────────────────────────────────────────
$pdoA->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($allTables as $t) {
  $pdoA->exec("TRUNCATE TABLE {$t}");
}
$pdoA->exec('SET FOREIGN_KEY_CHECKS = 1');

// ── Resumo ───────────────────────────────────────────────────────────────
$total = $GLOBALS['__tests'];
$fails = $GLOBALS['__fails'];

if ($fails > 0) {
  echo "\n{$fails} de {$total} verificação(ões) de integração falharam.\n";
  exit(1);
}

echo "Todas as {$total} verificações de integração passaram.\n";
exit(0);
