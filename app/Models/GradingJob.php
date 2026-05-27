<?php

declare(strict_types=1);

namespace App\Models;

class GradingJob extends Model
{
  public const STATUS_QUEUED = 'queued';
  public const STATUS_PROCESSING = 'processing';
  public const STATUS_COMPLETED = 'completed';
  public const STATUS_FAILED = 'failed';

  private const MAX_ATTEMPTS = 3;

  protected string $table = 'grading_jobs';

  public function enqueueAttempt(int $attemptId): void
  {
    $this->db->execute(
      "INSERT INTO grading_jobs (attempt_id, status, available_at)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE
               status = CASE
                 WHEN status IN ('completed', 'processing') THEN status
                 ELSE VALUES(status)
               END,
               available_at = CASE
                 WHEN status IN ('completed', 'processing') THEN available_at
                 ELSE NOW()
               END,
               last_error = CASE
                 WHEN status IN ('completed', 'processing') THEN last_error
                 ELSE NULL
               END",
      [$attemptId, self::STATUS_QUEUED]
    );
  }

  public function claimNext(): array|false
  {
    $this->db->beginTransaction();

    try {
      $job = $this->db->fetchOne(
        "SELECT *
               FROM grading_jobs
               WHERE status IN ('queued', 'failed')
                 AND attempts < ?
                 AND available_at <= NOW()
               ORDER BY available_at ASC, id ASC
               LIMIT 1
               FOR UPDATE",
        [self::MAX_ATTEMPTS]
      );

      if (!$job) {
        $this->db->commit();
        return false;
      }

      $this->db->execute(
        "UPDATE grading_jobs
               SET status = ?,
                   attempts = attempts + 1,
                   locked_at = NOW(),
                   last_error = NULL
               WHERE id = ?",
        [self::STATUS_PROCESSING, (int) $job['id']]
      );

      $this->db->commit();

      $job['status'] = self::STATUS_PROCESSING;
      $job['attempts'] = (int) $job['attempts'] + 1;
      return $job;
    } catch (\Throwable $e) {
      if ($this->db->inTransaction()) {
        $this->db->rollback();
      }

      throw $e;
    }
  }

  public function markCompleted(int $jobId): void
  {
    $this->db->execute(
      "UPDATE grading_jobs
             SET status = ?, completed_at = NOW(), last_error = NULL
             WHERE id = ?",
      [self::STATUS_COMPLETED, $jobId]
    );
  }

  public function markFailed(int $jobId, string $error, int $delaySeconds = 300): void
  {
    $safeDelay = max(60, $delaySeconds);

    $this->db->execute(
      "UPDATE grading_jobs
             SET status = ?,
                 available_at = DATE_ADD(NOW(), INTERVAL {$safeDelay} SECOND),
                 last_error = ?
             WHERE id = ?",
      [self::STATUS_FAILED, mb_substr($error, 0, 2000), $jobId]
    );
  }
}
