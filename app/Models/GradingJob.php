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
               available_at = CASE
                 WHEN status IN ('completed', 'processing') THEN available_at
                 ELSE NOW()
               END,
               last_error = CASE
                 WHEN status IN ('completed', 'processing') THEN last_error
                 ELSE NULL
               END,
               status = CASE
                 WHEN status IN ('completed', 'processing') THEN status
                 ELSE VALUES(status)
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

  public function operationalSummary(?int $teacherId = null): array
  {
    $defaults = [
      'queued' => 0,
      'processing' => 0,
      'failed' => 0,
      'completed_24h' => 0,
      'stale' => 0,
    ];

    try {
      $teacherJoin = $teacherId !== null ? 'JOIN attempts a ON a.id = gj.attempt_id JOIN exercises e ON e.id = a.exercise_id' : '';
      $teacherWhere = $teacherId !== null ? 'AND e.teacher_id = ?' : '';
      $params = $teacherId !== null ? [$teacherId] : [];

      $rows = $this->db->fetchAll(
        "SELECT gj.status, COUNT(*) AS total
               FROM grading_jobs gj
               {$teacherJoin}
               WHERE 1 = 1 {$teacherWhere}
               GROUP BY gj.status",
        $params
      );

      $summary = $defaults;
      foreach ($rows as $row) {
        $status = (string) ($row['status'] ?? '');
        if (array_key_exists($status, $summary)) {
          $summary[$status] = (int) ($row['total'] ?? 0);
        }
      }

      $summary['completed_24h'] = $this->countCompletedSince($teacherId, 24);
      $summary['stale'] = $this->countStale($teacherId);

      return $summary;
    } catch (\Throwable $e) {
      error_log('grading_jobs summary unavailable: ' . $e->getMessage());
      return $defaults;
    }
  }

  public function recentFailures(?int $teacherId = null, int $limit = 5): array
  {
    try {
      $safeLimit = max(1, $limit);
      $teacherWhere = $teacherId !== null ? 'AND e.teacher_id = ?' : '';
      $params = $teacherId !== null ? [$teacherId] : [];

      return $this->db->fetchAll(
        "SELECT gj.*, a.exercise_id, a.student_id, e.title AS exercise_title,
                      student.name AS student_name, student.email AS student_email,
                      teacher.name AS teacher_name
               FROM grading_jobs gj
               JOIN attempts a ON a.id = gj.attempt_id
               JOIN exercises e ON e.id = a.exercise_id
               JOIN users student ON student.id = a.student_id
               JOIN users teacher ON teacher.id = e.teacher_id
               WHERE gj.status = 'failed' {$teacherWhere}
               ORDER BY gj.updated_at DESC, gj.id DESC
               LIMIT {$safeLimit}",
        $params
      );
    } catch (\Throwable $e) {
      error_log('grading_jobs failures unavailable: ' . $e->getMessage());
      return [];
    }
  }

  public function statusesForAttempts(array $attemptIds): array
  {
    $ids = array_values(array_unique(array_filter(array_map('intval', $attemptIds), static fn(int $id): bool => $id > 0)));

    if ($ids === []) {
      return [];
    }

    try {
      $placeholders = implode(',', array_fill(0, count($ids), '?'));
      $rows = $this->db->fetchAll(
        "SELECT attempt_id, status, attempts, last_error
               FROM grading_jobs
               WHERE attempt_id IN ({$placeholders})",
        $ids
      );

      $map = [];
      foreach ($rows as $row) {
        $map[(int) $row['attempt_id']] = $row;
      }

      return $map;
    } catch (\Throwable $e) {
      error_log('grading_jobs status map unavailable: ' . $e->getMessage());
      return [];
    }
  }

  private function countCompletedSince(?int $teacherId, int $hours): int
  {
    $teacherJoin = $teacherId !== null ? 'JOIN attempts a ON a.id = gj.attempt_id JOIN exercises e ON e.id = a.exercise_id' : '';
    $teacherWhere = $teacherId !== null ? 'AND e.teacher_id = ?' : '';
    $params = $teacherId !== null ? [$teacherId] : [];

    $row = $this->db->fetchOne(
      "SELECT COUNT(*) AS total
             FROM grading_jobs gj
             {$teacherJoin}
             WHERE gj.status = 'completed'
               AND gj.completed_at >= DATE_SUB(NOW(), INTERVAL {$hours} HOUR)
               {$teacherWhere}",
      $params
    );

    return (int) ($row['total'] ?? 0);
  }

  private function countStale(?int $teacherId): int
  {
    $teacherJoin = $teacherId !== null ? 'JOIN attempts a ON a.id = gj.attempt_id JOIN exercises e ON e.id = a.exercise_id' : '';
    $teacherWhere = $teacherId !== null ? 'AND e.teacher_id = ?' : '';
    $params = $teacherId !== null ? [$teacherId] : [];

    $row = $this->db->fetchOne(
      "SELECT COUNT(*) AS total
             FROM grading_jobs gj
             {$teacherJoin}
             WHERE (
               (gj.status = 'queued' AND gj.available_at <= DATE_SUB(NOW(), INTERVAL 15 MINUTE))
               OR (gj.status = 'processing' AND gj.locked_at <= DATE_SUB(NOW(), INTERVAL 15 MINUTE))
             )
             {$teacherWhere}",
      $params
    );

    return (int) ($row['total'] ?? 0);
  }
}
