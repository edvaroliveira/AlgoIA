<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Answer;
use App\Models\GradingJob;
use App\Models\Question;
use Core\Database;

class AttemptSubmissionService
{
  /**
   * Submits an attempt atomically.
   *
   * Returns 'queued' on success (new or idempotent repeat).
   * Throws \RuntimeException on invalid state.
   */
  public function submit(int $attemptId, int $studentId, array $postData): string
  {
    $db = Database::getInstance();
    $db->beginTransaction();

    try {
      $attempt = $db->fetchOne(
        "SELECT * FROM attempts WHERE id = ? FOR UPDATE",
        [$attemptId]
      );

      if (!$attempt || (int) $attempt['student_id'] !== $studentId) {
        $db->rollback();
        throw new \RuntimeException('Tentativa não encontrada ou acesso negado.');
      }

      $status = (string) ($attempt['status'] ?? '');

      // Idempotent: concurrent duplicate request after first succeeded
      if ($status === 'submitted' || $status === 'graded') {
        $db->commit();
        return 'queued';
      }

      if ($status !== 'in_progress') {
        $db->rollback();
        throw new \RuntimeException('Tentativa inválida ou já enviada.');
      }

      $questions = (new Question())->findByExercise((int) $attempt['exercise_id']);
      $answers   = new Answer();

      foreach ($questions as $q) {
        $text = trim((string) ($postData["answer_{$q['id']}"] ?? ''));
        $answers->saveOrUpdate($attemptId, (int) $q['id'], $text);
      }

      $db->execute(
        "UPDATE attempts
               SET status = 'submitted', submitted_at = COALESCE(submitted_at, NOW())
               WHERE id = ? AND status = 'in_progress'",
        [$attemptId]
      );

      (new GradingJob())->enqueueAttempt($attemptId);

      $db->commit();
      return 'queued';
    } catch (\Throwable $e) {
      if ($db->inTransaction()) {
        $db->rollback();
      }
      throw $e;
    }
  }
}
