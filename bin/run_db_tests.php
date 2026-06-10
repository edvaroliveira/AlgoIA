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

// ── Resumo ───────────────────────────────────────────────────────────────────
$total = $GLOBALS['__tests'];
$fails = $GLOBALS['__fails'];

if ($fails > 0) {
  echo "\n{$fails} de {$total} verificação(ões) falharam.\n";
  exit(1);
}

echo "Todos os {$total} testes de banco passaram.\n";
exit(0);
