<?php

declare(strict_types=1);

namespace App\Models;

class Question extends Model
{
  protected string $table = 'questions';

  public function findByExercise(int $exerciseId): array
  {
    return $this->db->fetchAll(
      "SELECT * FROM questions WHERE exercise_id = ? ORDER BY order_index, id",
      [$exerciseId]
    );
  }

  public function create(
    int    $exerciseId,
    string $text,
    string $expectedAnswerHint,
    float  $maxScore,
    int    $orderIndex
  ): int {
    return $this->db->insert(
      "INSERT INTO questions (exercise_id, text, expected_answer_hint, max_score, order_index)
             VALUES (?, ?, ?, ?, ?)",
      [$exerciseId, $text, $expectedAnswerHint, $maxScore, $orderIndex]
    );
  }

  public function countByExercise(int $exerciseId): int
  {
    $row = $this->db->fetchOne(
      "SELECT COUNT(*) AS c FROM questions WHERE exercise_id = ?",
      [$exerciseId]
    );
    return (int) ($row['c'] ?? 0);
  }

  public function getTotalMaxScore(int $exerciseId): float
  {
    $row = $this->db->fetchOne(
      "SELECT SUM(max_score) AS total FROM questions WHERE exercise_id = ?",
      [$exerciseId]
    );
    return (float) ($row['total'] ?? 0);
  }

  public function updateAdminReview(int $id, string $status, ?string $note, ?int $reviewedBy): void
  {
    $this->db->execute(
      "UPDATE questions
             SET admin_review_status = ?, admin_review_note = ?, admin_reviewed_at = NOW(), admin_reviewed_by = ?
             WHERE id = ?",
      [$status, $note, $reviewedBy, $id]
    );
  }

  public function belongsToTeacher(int $questionId, int $teacherId): bool
  {
    $row = $this->db->fetchOne(
      "SELECT q.id FROM questions q
             JOIN exercises e ON e.id = q.exercise_id
             WHERE q.id = ? AND e.teacher_id = ?",
      [$questionId, $teacherId]
    );
    return $row !== false;
  }

  public function belongsToExercise(int $questionId, int $exerciseId): bool
  {
    $row = $this->db->fetchOne(
      "SELECT id FROM questions WHERE id = ? AND exercise_id = ?",
      [$questionId, $exerciseId]
    );
    return $row !== false;
  }
}
