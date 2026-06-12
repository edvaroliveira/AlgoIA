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

  public function findActiveByExercise(int $exerciseId): array
  {
    return $this->db->fetchAll(
      "SELECT * FROM questions
             WHERE exercise_id = ? AND admin_review_status <> 'blocked'
             ORDER BY order_index, id",
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

  public function hasBlockedByExercise(int $exerciseId): bool
  {
    $row = $this->db->fetchOne(
      "SELECT id FROM questions
             WHERE exercise_id = ? AND admin_review_status = 'blocked'
             LIMIT 1",
      [$exerciseId]
    );
    return $row !== false;
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

  public function updateAdminReviewAndProtectExercise(int $id, string $status, ?string $note, ?int $reviewedBy): bool
  {
    $this->db->beginTransaction();

    try {
      $question = $this->db->fetchOne(
        "SELECT id, exercise_id FROM questions WHERE id = ? FOR UPDATE",
        [$id]
      );

      if (!$question) {
        $this->db->rollback();
        return false;
      }

      $this->db->execute(
        "UPDATE questions
               SET admin_review_status = ?, admin_review_note = ?, admin_reviewed_at = NOW(), admin_reviewed_by = ?
               WHERE id = ?",
        [$status, $note, $reviewedBy, $id]
      );

      if ($status === 'blocked') {
        $this->db->execute(
          "UPDATE exercise_turmas
                 SET closes_at = CASE WHEN closes_at > NOW() THEN NOW() ELSE closes_at END
                 WHERE exercise_id = ?",
          [(int) $question['exercise_id']]
        );
      }

      $this->db->commit();
      return true;
    } catch (\Throwable $e) {
      if ($this->db->inTransaction()) {
        $this->db->rollback();
      }
      throw $e;
    }
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
