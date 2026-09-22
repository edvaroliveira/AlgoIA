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
 * admin). HTTP smoke (headers, redirect de rota protegida, CSRF) via
 * `php -S` servindo public/ de verdade.
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
  fwrite(STDERR,
    "Recusado: DB_DATABASE ('{$dbName}') não parece um banco de teste.\n" .
    "Defina INTEGRATION_TESTS_CONFIRM=1 para confirmar explicitamente — " .
    "este script TRUNCA todas as tabelas do banco configurado.\n");
  exit(0);
}

function newRawConnection(array $cfg): PDO
{
  $dsn = "mysql:host={$cfg['host']};dbname={$cfg['database']};charset=utf8mb4";
  return new PDO($dsn, $cfg['username'], $cfg['password'], [
    PDO::ATTR_ERRMODE               => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE    => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES      => false,
    // Sem isso, o driver mysqlnd pode deixar um resultset aberto entre
    // instruções DDL/PREPARE em sequência (schema tem PREPARE/EXECUTE em
    // 020_admin_reviewed_by_fk.sql) e a próxima chamada falha com
    // "Cannot execute queries while other unbuffered queries are active".
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
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
function applySchema(PDO $pdo, string $path): void
{
  $sql = (string) file_get_contents($path);
  $sql = preg_replace('/^--.*$/m', '', $sql) ?? $sql;

  foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
    $pdo->exec($stmt);
  }
}

applySchema($pdoA, ROOT_PATH . '/database/migrations/001_create_tables.sql');
applySchema($pdoA, ROOT_PATH . '/database/migrations/020_admin_reviewed_by_fk.sql');

$allTables = [
  'injection_logs', 'answers', 'grading_jobs', 'attempts', 'questions',
  'exercise_turmas', 'exercises', 'student_turma', 'turmas',
  'login_attempts', 'system_settings', 'audit_logs', 'users',
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

  $pdo->exec("INSERT INTO exercises (teacher_id, turma_id, title, status) VALUES ({$teacherId}, {$turmaId}, 'Exercício IT', 'active')");
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
  curl_close($ch);

  if ($raw === false) {
    return ['code' => 0, 'headers' => '', 'body' => ''];
  }

  return [
    'code'    => $code,
    'headers' => substr((string) $raw, 0, $headerSize),
    'body'    => substr((string) $raw, $headerSize),
  ];
}

if (!extension_loaded('curl')) {
  echo "curl indisponível — smoke HTTP pulado.\n";
} else {
  $port = 18080 + random_int(0, 500);
  $host = "127.0.0.1:{$port}";

  $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
  $serverProcess = proc_open(
    ['php', '-S', $host, '-t', ROOT_PATH . '/public', ROOT_PATH . '/public/index.php'],
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
      curl_close($ch);
      check($csrfCode === 403, 'HTTP: POST /login sem token CSRF responde 403');
    }

    proc_terminate($serverProcess);
    proc_close($serverProcess);
  }
}

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
