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

$db = Core\Database::getInstance();

$name     = \Core\env('SEED_TEACHER_NAME', 'Professor Admin');
$email    = \Core\env('SEED_TEACHER_EMAIL', 'admin@example.com');
$password = \Core\env('SEED_TEACHER_PASSWORD', 'TrocaEsta123!');

$existing = $db->fetchOne('SELECT id FROM users WHERE email = ?', [$email]);

if ($existing) {
  echo "Docente já existe: {$email}\n";
  exit(0);
}

$hash = password_hash($password, PASSWORD_BCRYPT);
$db->execute(
  "INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, 'teacher', 'active')",
  [$name, $email, $hash]
);

echo "Docente criado com sucesso:\n";
echo "  E-mail: {$email}\n";
echo "  Senha : {$password}\n";
echo "Altere a senha após o primeiro acesso.\n";
