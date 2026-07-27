<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Answer;
use App\Models\GradingJob;
use App\Models\Question;
use Core\Database;

class AttemptSubmissionService
{
  private Database $db;

  public function __construct(?Database $db = null)
  {
    $this->db = $db ?? Database::getInstance();
  }

  /**
   * Submits an attempt atomically.
   *
   * Returns 'submitted'         — new successful first-time submission.
   * Returns 'already_submitted' — idempotent repeat (already submitted/graded).
   * Throws \RuntimeException    — failure (rolled back, attempt still in_progress).
   */
  public function submit(int $attemptId, int $studentId, array $postData): string
  {
    $db = $this->db;
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
        return 'already_submitted';
      }

      if ($status !== 'in_progress') {
        $db->rollback();
        throw new \RuntimeException('Tentativa inválida ou já enviada.');
      }

      if (empty($attempt['turma_id'])) {
        $db->rollback();
        throw new \RuntimeException('Tentativa sem contexto de publicação válido.');
      }

      // Revalidate all access and moderation boundaries inside the transaction.
      $pubOpen = $db->fetchOne(
        "SELECT et.turma_id
               FROM exercise_turmas et
               JOIN exercises e
                 ON e.id = et.exercise_id
                AND e.status = 'active'
                AND COALESCE(e.admin_review_status, 'approved') <> 'blocked'
               JOIN student_turma st
                 ON st.turma_id = et.turma_id
                AND st.student_id = ?
                AND st.status = 'active'
               WHERE et.exercise_id = ?
                 AND et.turma_id = ?
                 AND et.opens_at <= NOW()
                 AND et.closes_at >= NOW()
                 AND NOT EXISTS (
                   SELECT 1 FROM questions blocked_q
                   WHERE blocked_q.exercise_id = et.exercise_id
                     AND blocked_q.admin_review_status = 'blocked'
                 )
               FOR UPDATE",
        [$studentId, (int) $attempt['exercise_id'], (int) $attempt['turma_id']]
      );

      if (!$pubOpen) {
        $db->rollback();
        throw new \RuntimeException('A publicação, matrícula ou moderação não permite mais o envio desta tentativa.');
      }

      $questions = (new Question())->findActiveByExercise((int) $attempt['exercise_id']);
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
      return 'submitted';
    } catch (\Throwable $e) {
      if ($db->inTransaction()) {
        $db->rollback();
      }
      throw $e;
    }
  }
}
