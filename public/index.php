<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

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
