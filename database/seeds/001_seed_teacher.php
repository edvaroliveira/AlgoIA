<?php

/**
 * Seed 001 — Cria o docente inicial.
 * Execute: php database/seeds/001_seed_teacher.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));

require ROOT_PATH . '/core/Env.php';
(new Core\Env(ROOT_PATH . '/.env'))->load();

require ROOT_PATH . '/autoload.php';
require __DIR__ . '/seed_password.php';

$db = Core\Database::getInstance();

$name     = \Core\env('SEED_TEACHER_NAME', 'Professor Admin');
$email    = \Core\env('SEED_TEACHER_EMAIL', 'admin@example.com');
try {
  $password = seed_password('SEED_TEACHER_PASSWORD', \Core\env('SEED_TEACHER_PASSWORD'));
} catch (\RuntimeException $e) {
  fwrite(STDERR, $e->getMessage() . "\n");
  exit(1);
}

$existing = $db->fetchOne('SELECT id FROM users WHERE email = ?', [$email]);

if ($existing) {
  echo "Docente já existe: {$email}\n";
  exit(0);
}

$hash = password_hash($password, PASSWORD_BCRYPT);
$db->execute(
  "INSERT INTO users (name, email, password_hash, must_change_password, role, status) VALUES (?, ?, ?, 1, 'teacher', 'active')",
  [$name, $email, $hash]
);

echo "Docente criado com sucesso:\n";
echo "  E-mail: {$email}\n";
echo "A senha é a definida em SEED_TEACHER_PASSWORD; a troca é obrigatória no primeiro acesso.\n";
