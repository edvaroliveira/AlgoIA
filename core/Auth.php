<?php

declare(strict_types=1);

namespace Core;

class Auth
{
  private static ?Session $session = null;

  public static function setSession(Session $session): void
  {
    self::$session = $session;
  }

  public static function login(array $user): void
  {
    self::$session->regenerate();
    self::$session->set('user', [
      'id'   => (int) $user['id'],
      'name' => $user['name'],
      'email' => $user['email'],
      'role' => $user['role'],
      'must_change_password' => !empty($user['must_change_password']),
    ]);
  }

  public static function logout(): void
  {
    self::$session->destroy();
  }

  public static function check(): bool
  {
    return self::$session->has('user');
  }

  public static function user(): ?array
  {
    return self::$session->get('user');
  }

  public static function id(): ?int
  {
    $u = self::user();
    return $u ? (int) $u['id'] : null;
  }

  public static function isTeacher(): bool
  {
    $u = self::user();
    return $u && $u['role'] === 'teacher';
  }

  public static function isAdmin(): bool
  {
    $u = self::user();
    return $u && $u['role'] === 'admin';
  }

  public static function isStudent(): bool
  {
    $u = self::user();
    return $u && $u['role'] === 'student';
  }

  public static function mustChangePassword(): bool
  {
    $u = self::user();
    return $u && !empty($u['must_change_password']);
  }

  public static function clearMustChangePassword(): void
  {
    $u = self::user();
    if (!$u) {
      return;
    }

    $u['must_change_password'] = false;
    self::$session->set('user', $u);
  }

  public static function requireAuth(): void
  {
    if (!self::check()) {
      View::redirect('/login');
    }

    $path = app_request_path();
    if (
      self::mustChangePassword()
      && $path !== '/password/change'
      && $path !== '/logout'
    ) {
      View::redirect('/password/change');
    }
  }

  public static function requireTeacher(): void
  {
    self::requireAuth();
    if (!self::isTeacher()) {
      View::redirect(self::isAdmin() ? '/admin/dashboard' : '/student/dashboard');
    }
  }

  public static function requireAdmin(): void
  {
    self::requireAuth();
    if (!self::isAdmin()) {
      View::redirect(self::isTeacher() ? '/teacher/dashboard' : '/student/dashboard');
    }
  }

  public static function requireStudent(): void
  {
    self::requireAuth();
    if (!self::isStudent()) {
      View::redirect(self::isAdmin() ? '/admin/dashboard' : '/teacher/dashboard');
    }
  }

  public static function ensure(bool $condition, string $message = 'Acesso negado.', int $code = 403, bool $json = false): void
  {
    if (!$condition) {
      self::deny($message, $code, $json);
    }
  }

  public static function deny(string $message = 'Acesso negado.', int $code = 403, bool $json = false): never
  {
    http_response_code($code);

    if ($json) {
      header('Content-Type: application/json');
      exit(json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    exit($message);
  }
}
