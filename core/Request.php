<?php

declare(strict_types=1);

namespace Core;

class Request
{
  public static function post(string $key, mixed $default = null): mixed
  {
    return $_POST[$key] ?? $default;
  }

  public static function get(string $key, mixed $default = null): mixed
  {
    return $_GET[$key] ?? $default;
  }

  public static function isPost(): bool
  {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
  }

  /**
   * Validates CSRF token from POST data.
   * Aborts with 403 on failure.
   */
  public static function validateCsrf(): void
  {
    global $session;
    $token = (string) ($_POST['_csrf_token'] ?? '');

    if (!$session->validateCsrf($token)) {
      http_response_code(403);
      exit('Token CSRF inválido. Use o botão voltar e tente novamente.');
    }
  }

  /** Returns a sanitized string: strips tags, trims whitespace. */
  public static function str(string $key, string $default = ''): string
  {
    $value = (string) ($_POST[$key] ?? $default);
    return trim(strip_tags($value));
  }

  /** Returns freeform text preserving line breaks and symbols like <- used in pseudocode. */
  public static function text(string $key, string $default = ''): string
  {
    $value = (string) ($_POST[$key] ?? $default);
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    return trim($value);
  }

  /** Returns a sanitized email from POST. */
  public static function email(string $key, string $default = ''): string
  {
    return (string) filter_var($_POST[$key] ?? $default, FILTER_SANITIZE_EMAIL);
  }

  /** Returns an int from POST. */
  public static function int(string $key, int $default = 0): int
  {
    return (int) ($_POST[$key] ?? $default);
  }

  /** Returns a float from POST. */
  public static function float(string $key, float $default = 0.0): float
  {
    return (float) ($_POST[$key] ?? $default);
  }
}
