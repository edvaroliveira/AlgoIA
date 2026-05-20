<?php

declare(strict_types=1);

namespace Core;

class Session
{
  private const INACTIVITY_TIMEOUT_SECONDS = 1800;

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

    $this->enforceInactivityTimeout();

    if (empty($_SESSION['csrf_token'])) {
      $this->regenerateCsrfToken();
    }

    $_SESSION['_last_activity_at'] = time();
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
    $this->regenerateCsrfToken();
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

  public function regenerateCsrfToken(): string
  {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
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

  private function enforceInactivityTimeout(): void
  {
    $now          = time();
    $lastActivity = (int) ($_SESSION['_last_activity_at'] ?? 0);

    if ($lastActivity > 0 && ($now - $lastActivity) > self::INACTIVITY_TIMEOUT_SECONDS) {
      $_SESSION = [
        '_flash' => [
          'error' => 'Sua sessão expirou por inatividade. Faça login novamente.',
        ],
      ];
      session_regenerate_id(true);
    }
  }
}
