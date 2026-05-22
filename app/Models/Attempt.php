<?php

declare(strict_types=1);

namespace App\Models;

class Attempt extends Model
{
  protected string $table = 'attempts';

  public function start(int $studentId, int $exerciseId, ?int $turmaId = null): int
  {
    return $this->db->insert(
      "INSERT INTO attempts (student_id, exercise_id, turma_id, status) VALUES (?, ?, ?, 'in_progress')",
      [$studentId, $exerciseId, $turmaId]
    );
  }

  public function submit(int $attemptId, float $totalScore): void
  {
    $this->db->execute(
      "UPDATE attempts SET status = 'graded', submitted_at = NOW(), total_score = ? WHERE id = ?",
      [$totalScore, $attemptId]
    );
  }

  public function getInProgress(int $studentId, int $exerciseId, ?int $turmaId = null): array|false
  {
    $turmaFilter = $turmaId !== null ? "AND (turma_id = ? OR turma_id IS NULL)" : '';
    $params = $turmaId !== null ? [$studentId, $exerciseId, $turmaId] : [$studentId, $exerciseId];

    return $this->db->fetchOne(
      "SELECT * FROM attempts
             WHERE student_id = ? AND exercise_id = ? AND status = 'in_progress' {$turmaFilter}
             ORDER BY started_at DESC LIMIT 1",
      $params
    );
  }

  public function countSubmitted(int $studentId, int $exerciseId, ?int $turmaId = null): int
  {
    $turmaFilter = $turmaId !== null ? "AND (turma_id = ? OR turma_id IS NULL)" : '';
    $params = $turmaId !== null ? [$studentId, $exerciseId, $turmaId] : [$studentId, $exerciseId];

    $row = $this->db->fetchOne(
      "SELECT COUNT(*) AS c FROM attempts
             WHERE student_id = ? AND exercise_id = ? AND status = 'graded' {$turmaFilter}",
      $params
    );
    return (int) ($row['c'] ?? 0);
  }

  public function getBestScore(int $studentId, int $exerciseId, ?int $turmaId = null): ?float
  {
    $turmaFilter = $turmaId !== null ? "AND (turma_id = ? OR turma_id IS NULL)" : '';
    $params = $turmaId !== null ? [$studentId, $exerciseId, $turmaId] : [$studentId, $exerciseId];

    $row = $this->db->fetchOne(
      "SELECT MAX(total_score) AS best FROM attempts
             WHERE student_id = ? AND exercise_id = ? AND status = 'graded' {$turmaFilter}",
      $params
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
      "SELECT a.*, e.title AS exercise_title,
                    COALESCE(attempt_et.closes_at, MAX(CASE WHEN st.student_id IS NOT NULL THEN et.closes_at END)) AS closes_at,
                    COALESCE(attempt_et.max_attempts, MAX(CASE WHEN st.student_id IS NOT NULL THEN et.max_attempts END)) AS max_attempts,
                    COALESCE(attempt_t.name, GROUP_CONCAT(DISTINCT CASE WHEN st.student_id IS NOT NULL THEN t.name END ORDER BY t.name SEPARATOR ', ')) AS turma_name
             FROM attempts a
             JOIN exercises e ON e.id = a.exercise_id
             LEFT JOIN turmas attempt_t ON attempt_t.id = a.turma_id
             LEFT JOIN exercise_turmas attempt_et ON attempt_et.exercise_id = e.id AND attempt_et.turma_id = a.turma_id
             LEFT JOIN exercise_turmas et ON et.exercise_id = e.id
             LEFT JOIN student_turma st ON st.turma_id = et.turma_id AND st.student_id = a.student_id AND st.status = 'active'
             LEFT JOIN turmas t ON t.id = et.turma_id
             WHERE a.id = ?
             GROUP BY a.id",
      [$attemptId]
    );
  }
}
