<?php

/**
 * Seed 002 — Cria o administrador inicial.
 * Execute: php database/seeds/002_seed_admin.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));

require ROOT_PATH . '/core/Env.php';
(new Core\Env(ROOT_PATH . '/.env'))->load();

require ROOT_PATH . '/autoload.php';

$db = Core\Database::getInstance();

$name     = \Core\env('SEED_ADMIN_NAME', 'Administrador do Sistema');
$email    = \Core\env('SEED_ADMIN_EMAIL', 'admin@algoia.local');
$password = \Core\env('SEED_ADMIN_PASSWORD', 'AdminAlgoIA123');

$existing = $db->fetchOne('SELECT id FROM users WHERE email = ?', [$email]);

if ($existing) {
  echo "Administrador já existe: {$email}\n";
  exit(0);
}

$hash = password_hash($password, PASSWORD_BCRYPT);
$db->execute(
  "INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, 'admin', 'active')",
  [$name, $email, $hash]
);

echo "Administrador criado com sucesso:\n";
echo "  E-mail: {$email}\n";
echo "  Senha : {$password}\n";
echo "Altere a senha após o primeiro acesso.\n";
