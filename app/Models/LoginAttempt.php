<?php

declare(strict_types=1);

namespace App\Models;

class LoginAttempt extends Model
{
  private const MAX_ATTEMPTS = 5;
  private const WINDOW_SECONDS = 300;
  private const RETENTION_DAYS = 30;

  protected string $table = 'login_attempts';

  public function isLocked(string $email, string $ipAddress): bool
  {
    $row = $this->db->fetchOne(
      "SELECT COUNT(*) AS total, MAX(created_at) AS last_failed_at
             FROM login_attempts
             WHERE email = ?
               AND ip_address = ?
               AND succeeded = 0
               AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)",
      [$this->normalizeEmail($email), $ipAddress, self::WINDOW_SECONDS]
    );

    return (int) ($row['total'] ?? 0) >= self::MAX_ATTEMPTS
      && !empty($row['last_failed_at'])
      && strtotime((string) $row['last_failed_at']) > (time() - self::WINDOW_SECONDS);
  }

  public function recordFailure(string $email, string $ipAddress, string $userAgent): void
  {
    $this->db->insert(
      "INSERT INTO login_attempts (email, ip_address, user_agent, succeeded)
             VALUES (?, ?, ?, 0)",
      [$this->normalizeEmail($email), $ipAddress, $this->summarizeUserAgent($userAgent)]
    );
  }

  public function recordSuccess(string $email, string $ipAddress, string $userAgent): void
  {
    $this->db->beginTransaction();

    try {
      $this->db->insert(
        "INSERT INTO login_attempts (email, ip_address, user_agent, succeeded)
               VALUES (?, ?, ?, 1)",
        [$this->normalizeEmail($email), $ipAddress, $this->summarizeUserAgent($userAgent)]
      );

      $this->db->execute(
        "DELETE FROM login_attempts
               WHERE email = ?
                 AND ip_address = ?
                 AND succeeded = 0",
        [$this->normalizeEmail($email), $ipAddress]
      );

      $this->db->commit();
    } catch (\Throwable $e) {
      if ($this->db->inTransaction()) {
        $this->db->rollback();
      }

      throw $e;
    }
  }

  public function pruneOld(): void
  {
    $this->db->execute(
      "DELETE FROM login_attempts
             WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
      [self::RETENTION_DAYS]
    );
  }

  private function normalizeEmail(string $email): string
  {
    return mb_strtolower(trim($email));
  }

  private function summarizeUserAgent(string $userAgent): string
  {
    return mb_substr(trim($userAgent), 0, 255);
  }
}
