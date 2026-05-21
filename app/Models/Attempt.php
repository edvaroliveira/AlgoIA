<?php

declare(strict_types=1);

namespace App\Models;

class Attempt extends Model
{
  protected string $table = 'attempts';

  public function start(int $studentId, int $exerciseId): int
  {
    return $this->db->insert(
      "INSERT INTO attempts (student_id, exercise_id, status) VALUES (?, ?, 'in_progress')",
      [$studentId, $exerciseId]
    );
  }

  public function submit(int $attemptId, float $totalScore): void
  {
    $this->db->execute(
      "UPDATE attempts SET status = 'graded', submitted_at = NOW(), total_score = ? WHERE id = ?",
      [$totalScore, $attemptId]
    );
  }

  public function getInProgress(int $studentId, int $exerciseId): array|false
  {
    return $this->db->fetchOne(
      "SELECT * FROM attempts
             WHERE student_id = ? AND exercise_id = ? AND status = 'in_progress'
             ORDER BY started_at DESC LIMIT 1",
      [$studentId, $exerciseId]
    );
  }

  public function countSubmitted(int $studentId, int $exerciseId): int
  {
    $row = $this->db->fetchOne(
      "SELECT COUNT(*) AS c FROM attempts
             WHERE student_id = ? AND exercise_id = ? AND status = 'graded'",
      [$studentId, $exerciseId]
    );
    return (int) ($row['c'] ?? 0);
  }

  public function getBestScore(int $studentId, int $exerciseId): ?float
  {
    $row = $this->db->fetchOne(
      "SELECT MAX(total_score) AS best FROM attempts
             WHERE student_id = ? AND exercise_id = ? AND status = 'graded'",
      [$studentId, $exerciseId]
    );
    return $row['best'] !== null ? (float) $row['best'] : null;
  }

  public function findByStudentAndExercise(int $studentId, int $exerciseId): array
  {
    return $this->db->fetchAll(
      "SELECT * FROM attempts
             WHERE student_id = ? AND exercise_id = ?
             ORDER BY started_at DESC",
      [$studentId, $exerciseId]
    );
  }

  public function belongsToStudent(int $attemptId, int $studentId): bool
  {
    $row = $this->db->fetchOne(
      "SELECT id FROM attempts WHERE id = ? AND student_id = ?",
      [$attemptId, $studentId]
    );
    return $row !== false;
  }

  public function getWithExercise(int $attemptId): array|false
  {
    return $this->db->fetchOne(
      "SELECT a.*, e.title AS exercise_title, e.closes_at, e.max_attempts,
                    GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', ') AS turma_name
             FROM attempts a
             JOIN exercises e ON e.id = a.exercise_id
             LEFT JOIN exercise_turmas et ON et.exercise_id = e.id
             LEFT JOIN turmas t ON t.id = et.turma_id
             WHERE a.id = ?
             GROUP BY a.id",
      [$attemptId]
    );
  }
}
