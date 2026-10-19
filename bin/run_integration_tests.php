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
    }

    proc_terminate($serverProcess);
    proc_close($serverProcess);
  }
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
