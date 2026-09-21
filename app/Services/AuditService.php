<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Core\Auth;

class AuditService
{
  public static function record(string $action, string $entityType, ?int $entityId = null, array $metadata = []): void
  {
    try {
      $auditLog = new AuditLog();
      $user     = Auth::user();

      $auditLog->create(
        $user['id'] ?? null,
        $user['role'] ?? 'guest',
        $action,
        $entityType,
        $entityId,
        $metadata,
        self::clientIp(),
        $_SERVER['HTTP_USER_AGENT'] ?? null,
      );
    } catch (\Throwable $e) {
      error_log('audit_log failed: ' . $e->getMessage());
    }
  }

  /**
   * IP do cliente para a trilha de auditoria.
   *
   * Cabeçalho de proxy é controlado pelo cliente: só é aceito quando a conexão
   * vem de um proxy declarado em TRUSTED_PROXIES. Sem essa lista, vale apenas
   * REMOTE_ADDR — um IP a menos vale mais que um IP forjado no audit_logs.
   */
  private static function clientIp(): ?string
  {
    $remoteAddr = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

    if (self::isTrustedProxy($remoteAddr)) {
      foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR'] as $key) {
        $value = trim((string) ($_SERVER[$key] ?? ''));
        if ($value === '') {
          continue;
        }

        if ($key === 'HTTP_X_FORWARDED_FOR') {
          $value = trim(explode(',', $value)[0]);
        }

        if (filter_var($value, FILTER_VALIDATE_IP) !== false) {
          return substr($value, 0, 45);
        }
      }
    }

    return $remoteAddr !== '' ? substr($remoteAddr, 0, 45) : null;
  }

  /** TRUSTED_PROXIES: lista separada por vírgula de IPs ou blocos CIDR. */
  private static function isTrustedProxy(string $remoteAddr): bool
  {
    if ($remoteAddr === '') {
      return false;
    }

    $configured = trim((string) \Core\env('TRUSTED_PROXIES', ''));
    if ($configured === '') {
      return false;
    }

    foreach (explode(',', $configured) as $entry) {
      $entry = trim($entry);
      if ($entry !== '' && self::ipMatches($remoteAddr, $entry)) {
        return true;
      }
    }

    return false;
  }

  private static function ipMatches(string $ip, string $rule): bool
  {
    if (!str_contains($rule, '/')) {
      return $ip === $rule;
    }

    [$subnet, $bits] = explode('/', $rule, 2);
    $ipBin     = @inet_pton($ip);
    $subnetBin = @inet_pton($subnet);
    $bits      = (int) $bits;

    if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
      return false;
    }

    $maxBits = strlen($ipBin) * 8;
    if ($bits < 0 || $bits > $maxBits) {
      return false;
    }

    $wholeBytes = intdiv($bits, 8);
    $restBits   = $bits % 8;

    if ($wholeBytes > 0 && strncmp($ipBin, $subnetBin, $wholeBytes) !== 0) {
      return false;
    }

    if ($restBits === 0) {
      return true;
    }

    $mask = ~((1 << (8 - $restBits)) - 1) & 0xFF;

    return (ord($ipBin[$wholeBytes]) & $mask) === (ord($subnetBin[$wholeBytes]) & $mask);
  }
}
