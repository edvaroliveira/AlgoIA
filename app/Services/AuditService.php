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

  private static function clientIp(): ?string
  {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
      $value = trim((string) ($_SERVER[$key] ?? ''));
      if ($value === '') {
        continue;
      }

      if ($key === 'HTTP_X_FORWARDED_FOR') {
        $value = trim(explode(',', $value)[0]);
      }

      return substr($value, 0, 45);
    }

    return null;
  }
}
