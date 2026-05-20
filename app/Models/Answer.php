<?php

declare(strict_types=1);

namespace App\Models;

class Answer extends Model
{
  protected string $table = 'answers';

  public function saveOrUpdate(int $attemptId, int $questionId, string $studentAnswer): int
  {
    $existing = $this->db->fetchOne(
      "SELECT id FROM answers WHERE attempt_id = ? AND question_id = ?",
      [$attemptId, $questionId]
    );

    if ($existing) {
      $this->db->execute(
        "UPDATE answers SET student_answer = ? WHERE id = ?",
        [$studentAnswer, $existing['id']]
      );
      return $existing['id'];
    }

    return $this->db->insert(
      "INSERT INTO answers (attempt_id, question_id, student_answer) VALUES (?, ?, ?)",
      [$attemptId, $questionId, $studentAnswer]
    );
  }

  public function updateAiResult(int $answerId, float $score, string $feedback): void
  {
    $this->db->execute(
      "UPDATE answers SET ai_score = ?, ai_feedback = ?, evaluated_at = NOW() WHERE id = ?",
      [$score, $feedback, $answerId]
    );
  }

  public function findByAttempt(int $attemptId): array
  {
    return $this->db->fetchAll(
      "SELECT ans.*, q.text AS question_text, q.max_score, q.expected_answer_hint, q.order_index
             FROM answers ans
             JOIN questions q ON q.id = ans.question_id
             WHERE ans.attempt_id = ?
             ORDER BY q.order_index, q.id",
      [$attemptId]
    );
  }

  public function findByAttemptAndQuestion(int $attemptId, int $questionId): array|false
  {
    return $this->db->fetchOne(
      "SELECT * FROM answers WHERE attempt_id = ? AND question_id = ?",
      [$attemptId, $questionId]
    );
  }
}
