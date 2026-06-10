<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

class AttemptStartService
{
  public function start(int $studentId, int $exerciseId, int $turmaId, int $maxAttempts): int
  {
    $db = Database::getInstance();
    $db->beginTransaction();

    try {
      $inProgress = $db->fetchOne(
        "SELECT id FROM attempts
               WHERE student_id = ? AND exercise_id = ? AND turma_id = ? AND status = 'in_progress'
               FOR UPDATE",
        [$studentId, $exerciseId, $turmaId]
      );

      if ($inProgress) {
        $db->commit();
        return (int) $inProgress['id'];
      }

      // FOR UPDATE here locks visited rows; primary protection against phantom inserts
      // comes from the gap lock acquired by the in-progress SELECT above (requires
      // idx_attempts_student_exercise_turma from migration 018).
      $row = $db->fetchOne(
        "SELECT COUNT(*) AS c FROM attempts
               WHERE student_id = ? AND exercise_id = ? AND turma_id = ? AND status IN ('submitted', 'graded')
               FOR UPDATE",
        [$studentId, $exerciseId, $turmaId]
      );
      $used = (int) ($row['c'] ?? 0);

      if ($maxAttempts > 0 && $used >= $maxAttempts) {
        $db->rollback();
        throw new \RuntimeException('Número máximo de tentativas atingido.');
      }

      $attemptId = $db->insert(
        "INSERT INTO attempts (student_id, exercise_id, turma_id, status) VALUES (?, ?, ?, 'in_progress')",
        [$studentId, $exerciseId, $turmaId]
      );

      $db->commit();
      return $attemptId;
    } catch (\Throwable $e) {
      if ($db->inTransaction()) {
        $db->rollback();
      }
      throw $e;
    }
  }
}
