<?php

declare(strict_types=1);

/**
 * Runner de testes unitários sem dependências (sem Composer/PHPUnit), no mesmo
 * espírito dos smoke tests. Cobre lógica pura e crítica que não depende de banco
 * nem de HTTP: sanitização de entrada, allowlist de templates (anti-LFI),
 * geração de rota e cache-busting de assets.
 *
 * Uso:  php bin/run_tests.php
 * Saída: lista de falhas (se houver) e um resumo; código de saída != 0 em falha.
 */

define('ROOT_PATH', dirname(__DIR__));
require ROOT_PATH . '/core/Env.php';
require ROOT_PATH . '/autoload.php';

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

// ── Core\Request: sanitização de entrada ─────────────────────────────────────
$_POST = [
  'name'  => '  <b>Edvar</b> ',
  'bio'   => "linha1\r\nlinha2\r linha3  ",
  'email' => '  User@Example.com ',
  'qtd'   => '42abc',
  'score' => '7.5x',
];

same('Edvar', \Core\Request::str('name'), 'Request::str remove tags e trim');
same("linha1\nlinha2\n linha3", \Core\Request::text('bio'), 'Request::text normaliza CRLF e faz trim');
same('User@Example.com', \Core\Request::email('email'), 'Request::email faz trim sem mutar');
same(42, \Core\Request::int('qtd'), 'Request::int faz cast');
same(7.5, \Core\Request::float('score'), 'Request::float faz cast');
same('padrao', \Core\Request::str('inexistente', 'padrao'), 'Request::str usa default quando ausente');
same(0, \Core\Request::int('inexistente'), 'Request::int default 0');
$_POST = [];

// ── Core\Router: geração e casamento de padrão de rota ───────────────────────
$router  = new \Core\Router();
$toPat   = new ReflectionMethod(\Core\Router::class, 'toPattern');
$pattern = $toPat->invoke($router, '/teacher/exercises/{id}');

check(preg_match($pattern, '/teacher/exercises/42', $m) === 1, 'Router casa rota com parâmetro');
same('42', $m['id'] ?? null, 'Router captura parâmetro nomeado');
check(preg_match($pattern, '/teacher/exercises/42/edit') === 0, 'Router não casa segmento extra');
check(preg_match($pattern, '/teacher/exercises/') === 0, 'Router exige o parâmetro');

$patProf = $toPat->invoke($router, '/admin/turmas/{id}/approve/{studentId}');
check(preg_match($patProf, '/admin/turmas/3/approve/9', $m2) === 1, 'Router casa rota com dois parâmetros');
same('3', $m2['id'] ?? null, 'Router captura primeiro parâmetro');
same('9', $m2['studentId'] ?? null, 'Router captura segundo parâmetro');

// ── Core\View: allowlist de template (anti path-traversal/LFI) ───────────────
$traversalBlocked = false;
try {
  \Core\View::render('../config/app', []);
} catch (\RuntimeException $e) {
  $traversalBlocked = true;
}
check($traversalBlocked, 'View::render bloqueia traversal para fora de views/');

$missingBlocked = false;
try {
  \Core\View::render('nao/existe/aqui', []);
} catch (\RuntimeException $e) {
  $missingBlocked = true;
}
check($missingBlocked, 'View::render rejeita template inexistente');

// ── Core\asset_url: cache-busting por mtime ──────────────────────────────────
$cssUrl = \Core\asset_url('/assets/css/app.css');
check(str_contains($cssUrl, '?v='), 'asset_url adiciona ?v= para arquivo existente');
$noFile = \Core\asset_url('/assets/nao-existe-xyz.css');
check(!str_contains($noFile, '?v='), 'asset_url não adiciona ?v= para arquivo inexistente');

// ── Resumo ───────────────────────────────────────────────────────────────────
$total = $GLOBALS['__tests'];
$fails = $GLOBALS['__fails'];

if ($fails > 0) {
  echo "\n{$fails} de {$total} verificação(ões) falharam.\n";
  exit(1);
}

echo "Todos os {$total} testes passaram.\n";
exit(0);
