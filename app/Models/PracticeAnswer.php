<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Modo prática: o Aluno responde Questões do Banco de exercícios, sem nota,
 * com Correção na hora. Só correções bem-sucedidas são gravadas, e são elas
 * que contam no limite diário (custo da IA).
 */
class PracticeAnswer extends Model
{
  public const DAILY_LIMIT = 10;

  protected string $table = 'practice_answers';

  /** Temas com Questões ativas no banco e quantas o Aluno já praticou em cada um. */
  public function themesForStudent(int $studentId): array
  {
    return $this->db->fetchAll(
      "SELECT bt.name, COALESCE(c.name, 'Sem categoria') AS category_name,
              COUNT(DISTINCT bq.id) AS question_count,
              COUNT(DISTINCT pa.bank_question_id) AS practiced_count
             FROM bank_themes bt
             LEFT JOIN categories c ON c.id = bt.category_id
             JOIN bank_exercise_themes bet ON bet.theme_id = bt.id
             JOIN bank_exercises be ON be.id = bet.bank_exercise_id AND be.status = ?
             JOIN bank_questions bq ON bq.bank_exercise_id = be.id
             LEFT JOIN practice_answers pa ON pa.bank_question_id = bq.id AND pa.student_id = ?
             GROUP BY bt.id, bt.name, c.name
             ORDER BY category_name, bt.name",
      [BankExercise::STATUS_ACTIVE, $studentId]
    );
  }

  /** Sorteia uma Questão ativa do Tema, preferindo as que o Aluno ainda não praticou. */
  public function drawQuestion(int $studentId, string $theme): int|false
  {
    $row = $this->db->fetchOne(
      "SELECT bq.id
             FROM bank_questions bq
             JOIN bank_exercises be ON be.id = bq.bank_exercise_id AND be.status = ?
             JOIN bank_exercise_themes bet ON bet.bank_exercise_id = be.id
             JOIN bank_themes bt ON bt.id = bet.theme_id AND bt.name = ?
             ORDER BY EXISTS (
                        SELECT 1 FROM practice_answers pa
                        WHERE pa.bank_question_id = bq.id AND pa.student_id = ?
                      ), RAND()
             LIMIT 1",
      [BankExercise::STATUS_ACTIVE, $theme, $studentId]
    );
    return $row ? (int) $row['id'] : false;
  }

  /** Questão de um item ativo do banco, com o título da lista e os Temas. */
  public function findActiveQuestion(int $questionId): array|false
  {
    return $this->db->fetchOne(
      "SELECT bq.id, bq.text, bq.expected_answer_hint, bq.max_score, be.title AS list_title,
              CASE WHEN bq.python_solution_status = 'approved' THEN bq.python_solution END AS python_solution,
              (SELECT GROUP_CONCAT(bt.name ORDER BY bt.name SEPARATOR ', ')
                 FROM bank_exercise_themes bet JOIN bank_themes bt ON bt.id = bet.theme_id
                WHERE bet.bank_exercise_id = be.id) AS themes
             FROM bank_questions bq
             JOIN bank_exercises be ON be.id = bq.bank_exercise_id AND be.status = ?
             WHERE bq.id = ?",
      [BankExercise::STATUS_ACTIVE, $questionId]
    );
  }

  public function countToday(int $studentId): int
  {
    $row = $this->db->fetchOne(
      "SELECT COUNT(*) AS c FROM practice_answers WHERE student_id = ? AND created_at >= CURDATE()",
      [$studentId]
    );
    return (int) ($row['c'] ?? 0);
  }

  /** Respostas do Aluno a esta Questão, da mais recente para a mais antiga. */
  public function historyFor(int $studentId, int $questionId): array
  {
    return $this->db->fetchAll(
      "SELECT id, student_answer, score, max_score, feedback, deduction_reasons_json, created_at
             FROM practice_answers
             WHERE student_id = ? AND bank_question_id = ?
             ORDER BY created_at DESC, id DESC",
      [$studentId, $questionId]
    );
  }

  public function record(int $studentId, int $questionId, string $answer, array $result, float $maxScore): int
  {
    return $this->db->insert(
      "INSERT INTO practice_answers (student_id, bank_question_id, student_answer, score, max_score, feedback, deduction_reasons_json)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
      [
        $studentId,
        $questionId,
        $answer,
        round((float) $result['score'], 1),
        $maxScore,
        (string) $result['feedback'],
        json_encode(array_values($result['deduction_reasons'] ?? [])),
      ]
    );
  }
}
