<?php

declare(strict_types=1);

spl_autoload_register(function (string $class): void {
  $map = [
    'Core\\'              => ROOT_PATH . '/core/',
    'App\\Controllers\\'  => ROOT_PATH . '/app/Controllers/',
    'App\\Models\\'       => ROOT_PATH . '/app/Models/',
    'App\\Services\\'     => ROOT_PATH . '/app/Services/',
    'App\\Middleware\\'   => ROOT_PATH . '/app/Middleware/',
  ];

  foreach ($map as $prefix => $dir) {
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
      continue;
    }
    $relative = substr($class, strlen($prefix));
    $file     = $dir . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
      require $file;
      return;
    }
  }
});
