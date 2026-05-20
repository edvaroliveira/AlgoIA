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

  public static function isStudent(): bool
  {
    $u = self::user();
    return $u && $u['role'] === 'student';
  }

  public static function requireAuth(): void
  {
    if (!self::check()) {
      header('Location: /login');
      exit;
    }
  }

  public static function requireTeacher(): void
  {
    self::requireAuth();
    if (!self::isTeacher()) {
      header('Location: /student/dashboard');
      exit;
    }
  }

  public static function requireStudent(): void
  {
    self::requireAuth();
    if (!self::isStudent()) {
      header('Location: /teacher/dashboard');
      exit;
    }
  }
}
