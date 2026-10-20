<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Progresso do Aluno por Tema do Banco de exercícios: junta as correções da
 * Prática e as Respostas corrigidas de Tentativas cujas Questões vieram do banco.
 * Questões criadas do zero não têm Tema e ficam fora.
 */
class ThemeProgress extends Model
{
  public const MIN_ANSWERS = 3;
  public const STRONG_FROM = 0.70;
  public const WEAK_BELOW  = 0.50;

  public const LEVEL_FEW_DATA = 'few_data';
  public const LEVEL_STRONG   = 'strong';
  public const LEVEL_MIDDLE   = 'middle';
  public const LEVEL_WEAK     = 'weak';

  protected string $table = 'bank_themes';

  /**
   * Temas com item ativo no banco ou com alguma correção do Aluno.
   *
   * @return array<int, array{id: int, name: string, answered: int, average: ?float, level: string, practicable: bool}>
   */
  public function forStudent(int $studentId): array
  {
    $rows = $this->db->fetchAll(
      "SELECT bt.id, bt.name,
              COUNT(x.pct) AS answered,
              AVG(x.pct) AS average,
              MAX(active_t.theme_id) IS NOT NULL AS practicable
             FROM bank_themes bt
             LEFT JOIN (
               SELECT DISTINCT bet.theme_id
                 FROM bank_exercise_themes bet
                 JOIN bank_exercises be ON be.id = bet.bank_exercise_id AND be.status = 'active'
             ) active_t ON active_t.theme_id = bt.id
             LEFT JOIN (
               SELECT bet.theme_id, pa.score / NULLIF(pa.max_score, 0) AS pct
                 FROM practice_answers pa
                 JOIN bank_questions bq ON bq.id = pa.bank_question_id
                 JOIN bank_exercise_themes bet ON bet.bank_exercise_id = bq.bank_exercise_id
                WHERE pa.student_id = ?
               UNION ALL
               SELECT bet.theme_id, ans.ai_score / NULLIF(q.max_score, 0) AS pct
                 FROM answers ans
                 JOIN attempts att ON att.id = ans.attempt_id AND att.status = 'graded' AND att.student_id = ?
                 JOIN questions q ON q.id = ans.question_id
                 JOIN bank_questions bq ON bq.id = q.bank_question_id
                 JOIN bank_exercise_themes bet ON bet.bank_exercise_id = bq.bank_exercise_id
                WHERE ans.ai_score IS NOT NULL
             ) x ON x.theme_id = bt.id
             GROUP BY bt.id, bt.name
             HAVING practicable OR answered > 0
             ORDER BY bt.name",
      [$studentId, $studentId]
    );

    return array_map(static function (array $row): array {
      $answered = (int) $row['answered'];
      $average = $row['average'] !== null ? (float) $row['average'] : null;
      return [
        'id'          => (int) $row['id'],
        'name'        => (string) $row['name'],
        'answered'    => $answered,
        'average'     => $average,
        'level'       => self::levelOf($answered, $average),
        'practicable' => (bool) $row['practicable'],
      ];
    }, $rows);
  }

  public static function levelOf(int $answered, ?float $average): string
  {
    if ($answered < self::MIN_ANSWERS || $average === null) {
      return self::LEVEL_FEW_DATA;
    }
    if ($average >= self::STRONG_FROM) {
      return self::LEVEL_STRONG;
    }
    return $average < self::WEAK_BELOW ? self::LEVEL_WEAK : self::LEVEL_MIDDLE;
  }
}
