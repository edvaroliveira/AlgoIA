<?php

declare(strict_types=1);

namespace App\Models;

class InjectionLog extends Model
{
  /**
   * Retention window for prompt-injection incidents.
   *
   * Longer than login_attempts (30d) because injection incidents feed
   * pedagogical/security review, which happens on a slower cadence.
   * After this window the redacted excerpt is purged.
   */
  private const RETENTION_DAYS = 180;

  protected string $table = 'injection_logs';

  /** Removes injection logs older than the retention window. */
  public function pruneOld(): void
  {
    $this->db->execute(
      "DELETE FROM injection_logs
             WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
      [self::RETENTION_DAYS]
    );
  }
}
