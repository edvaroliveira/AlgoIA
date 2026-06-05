<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

$cspNonce = base64_encode(random_bytes(18));

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; script-src 'self' 'nonce-{$cspNonce}' https://cdn.jsdelivr.net; connect-src 'self'; font-src 'self' https://cdn.jsdelivr.net; form-action 'self'; base-uri 'self'; frame-ancestors 'self'");

// Env loader must come before autoloader (defines Core\Env and env())
require ROOT_PATH . '/core/Env.php';
(new Core\Env(ROOT_PATH . '/.env'))->load();

require ROOT_PATH . '/autoload.php';

// ── Tratamento global de erros (não vazar detalhes em produção) ──────────────
$appConfig = require ROOT_PATH . '/config/app.php';
$appDebug  = (bool) ($appConfig['debug'] ?? false);

ini_set('display_errors', $appDebug ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (!$appDebug) {
  $emitGeneric500 = static function (): void {
    if (!headers_sent()) {
      http_response_code(500);
      header('Content-Type: text/html; charset=UTF-8');
      echo '<!doctype html><meta charset="utf-8"><title>Erro interno</title>'
        . '<h1>Erro interno</h1>'
        . '<p>Ocorreu um erro ao processar sua solicitação. Tente novamente mais tarde.</p>';
    }
  };

  set_exception_handler(static function (\Throwable $e) use ($emitGeneric500): void {
    error_log('Uncaught ' . get_class($e) . ': ' . $e->getMessage()
      . ' in ' . $e->getFile() . ':' . $e->getLine());
    $emitGeneric500();
  });

  register_shutdown_function(static function () use ($emitGeneric500): void {
    $err = error_get_last();
    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if ($err !== null && in_array($err['type'], $fatal, true)) {
      error_log('Fatal ' . $err['type'] . ': ' . $err['message']
        . ' in ' . $err['file'] . ':' . $err['line']);
      $emitGeneric500();
    }
  });
}

\Core\View::$nonce = $cspNonce;

// Globals used across controllers
global $session;
$session = new Core\Session();
$session->start();

Core\Auth::setSession($session);

$router = new Core\Router();
require ROOT_PATH . '/routes/web.php';
$router->dispatch();
