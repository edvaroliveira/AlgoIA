<?php

declare(strict_types=1);

namespace Core;

/**
 * Loads a .env file into $_ENV / getenv().
 * Must be required directly — available before the autoloader.
 */
class Env
{
  public function __construct(private string $path) {}

  public function load(): void
  {
    if (!file_exists($this->path)) {
      return;
    }

    $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
      $line = trim($line);

      if ($line === '' || str_starts_with($line, '#')) {
        continue;
      }

      if (!str_contains($line, '=')) {
        continue;
      }

      [$key, $value] = explode('=', $line, 2);
      $key   = trim($key);
      $value = trim($value);

      // Strip surrounding quotes
      if (preg_match('/^(["\'])(.*)\1$/', $value, $m)) {
        $value = $m[2];
      }

      if (!array_key_exists($key, $_ENV)) {
        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
      }
    }
  }
}

/**
 * Global helper — available everywhere after core/Env.php is required.
 */
function env(string $key, mixed $default = null): mixed
{
  $val = $_ENV[$key] ?? getenv($key);
  return ($val !== false && $val !== null) ? $val : $default;
}
