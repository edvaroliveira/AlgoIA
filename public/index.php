<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'self'");

// Env loader must come before autoloader (defines Core\Env and env())
require ROOT_PATH . '/core/Env.php';
(new Core\Env(ROOT_PATH . '/.env'))->load();

require ROOT_PATH . '/autoload.php';

// Globals used across controllers
global $session;
$session = new Core\Session();
$session->start();

Core\Auth::setSession($session);

$router = new Core\Router();
require ROOT_PATH . '/routes/web.php';
$router->dispatch();
