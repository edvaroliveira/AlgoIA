<?php

declare(strict_types=1);

namespace Core;

class Session
{
  public function start(): void
  {
    if (session_status() !== PHP_SESSION_NONE) {
      return;
    }

    session_set_cookie_params([
      'lifetime' => 0,
      'path'     => '/',
      'secure'   => isset($_SERVER['HTTPS']),
      'httponly' => true,
      'samesite' => 'Lax',
    ]);

    session_start();

    if (empty($_SESSION['csrf_token'])) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
  }

  public function get(string $key, mixed $default = null): mixed
  {
    return $_SESSION[$key] ?? $default;
  }

  public function set(string $key, mixed $value): void
  {
    $_SESSION[$key] = $value;
  }

  public function has(string $key): bool
  {
    return isset($_SESSION[$key]);
  }

  public function remove(string $key): void
  {
    unset($_SESSION[$key]);
  }

  public function flash(string $key, mixed $value): void
  {
    $_SESSION['_flash'][$key] = $value;
  }

  public function getFlash(string $key, mixed $default = null): mixed
  {
    $value = $_SESSION['_flash'][$key] ?? $default;
    unset($_SESSION['_flash'][$key]);
    return $value;
  }

  public function regenerate(): void
  {
    session_regenerate_id(true);
  }

  public function destroy(): void
  {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $p = session_get_cookie_params();
      setcookie(
        session_name(),
        '',
        time() - 42000,
        $p['path'],
        $p['domain'],
        $p['secure'],
        $p['httponly']
      );
    }
    session_destroy();
  }

  public function csrfToken(): string
  {
    return $_SESSION['csrf_token'] ?? '';
  }

  public function validateCsrf(string $token): bool
  {
    return !empty($_SESSION['csrf_token'])
      && hash_equals($_SESSION['csrf_token'], $token);
  }
}
