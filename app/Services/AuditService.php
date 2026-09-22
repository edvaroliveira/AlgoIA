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

    if (\Core\is_trusted_proxy($remoteAddr)) {
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
}
