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
   * Returns 'submitted'         — new successful first-time submission.
   * Returns 'already_submitted' — idempotent repeat (already submitted/graded).
   * Throws \RuntimeException    — failure (rolled back, attempt still in_progress).
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
        return 'already_submitted';
      }

      if ($status !== 'in_progress') {
        $db->rollback();
        throw new \RuntimeException('Tentativa inválida ou já enviada.');
      }

      // Validate the publication window is still open inside the transaction.
      if (!empty($attempt['turma_id'])) {
        $pubOpen = $db->fetchOne(
          "SELECT et.turma_id
                 FROM exercise_turmas et
                 WHERE et.exercise_id = ?
                   AND et.turma_id = ?
                   AND et.closes_at >= NOW()
                 FOR UPDATE",
          [(int) $attempt['exercise_id'], (int) $attempt['turma_id']]
        );

        if (!$pubOpen) {
          $db->rollback();
          throw new \RuntimeException('O prazo desta publicação encerrou. Não é possível enviar a tentativa.');
        }
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
      return 'submitted';
    } catch (\Throwable $e) {
      if ($db->inTransaction()) {
        $db->rollback();
      }
      throw $e;
    }
  }
}
