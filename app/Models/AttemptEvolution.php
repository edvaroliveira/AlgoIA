<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Histórico de evolução: para cada Publicação (Exercício + Turma) com duas ou
 * mais Tentativas Corrigidas do mesmo Aluno, a nota da primeira e a da última.
 */
class AttemptEvolution extends Model
{
  public const TREND_UP   = 'up';
  public const TREND_SAME = 'same';
  public const TREND_DOWN = 'down';

  protected string $table = 'attempts';

  /** Evolução do Aluno em todas as Publicações. */
  public function forStudent(int $studentId): array
  {
    return $this->summarize($this->db->fetchAll(
      "SELECT a.student_id, a.exercise_id, a.turma_id, a.total_score,
              e.title, t.name AS turma_name, u.name AS student_name,
              (SELECT COALESCE(SUM(q.max_score), 0) FROM questions q WHERE q.exercise_id = a.exercise_id) AS max_total
             FROM attempts a
             JOIN exercises e ON e.id = a.exercise_id
             JOIN users u ON u.id = a.student_id
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE a.student_id = ? AND a.status = 'graded'
             ORDER BY COALESCE(a.submitted_at, a.started_at), a.id",
      [$studentId]
    ));
  }

  /** Evolução dos Alunos ativos da Turma num Exercício. */
  public function forTurmaExercise(int $turmaId, int $exerciseId): array
  {
    return $this->summarize($this->db->fetchAll(
      "SELECT a.student_id, a.exercise_id, a.turma_id, a.total_score,
              e.title, t.name AS turma_name, u.name AS student_name,
              (SELECT COALESCE(SUM(q.max_score), 0) FROM questions q WHERE q.exercise_id = a.exercise_id) AS max_total
             FROM attempts a
             JOIN exercises e ON e.id = a.exercise_id
             JOIN users u ON u.id = a.student_id
             JOIN turmas t ON t.id = a.turma_id
             JOIN student_turma st ON st.student_id = a.student_id AND st.turma_id = a.turma_id AND st.status = 'active'
             WHERE a.turma_id = ? AND a.exercise_id = ? AND a.status = 'graded'
             ORDER BY COALESCE(a.submitted_at, a.started_at), a.id",
      [$turmaId, $exerciseId]
    ));
  }

  /**
   * @param array $attempts Tentativas Corrigidas em ordem cronológica.
   * @return array<int, array{student_name: string, title: string, turma_name: ?string, attempts: int, first: float, last: float, delta: float, max_total: float, trend: string}>
   */
  private function summarize(array $attempts): array
  {
    $groups = [];
    foreach ($attempts as $attempt) {
      $key = $attempt['student_id'] . ':' . $attempt['exercise_id'] . ':' . ($attempt['turma_id'] ?? '0');
      $groups[$key][] = $attempt;
    }

    $result = [];
    foreach ($groups as $rows) {
      if (count($rows) < 2) {
        continue;
      }
      $first = (float) $rows[0]['total_score'];
      $last = (float) $rows[count($rows) - 1]['total_score'];
      $delta = round($last - $first, 1);
      $result[] = [
        'student_name' => (string) $rows[0]['student_name'],
        'title'        => (string) $rows[0]['title'],
        'turma_name'   => $rows[0]['turma_name'] !== null ? (string) $rows[0]['turma_name'] : null,
        'attempts'     => count($rows),
        'first'        => $first,
        'last'         => $last,
        'delta'        => $delta,
        'max_total'    => (float) $rows[0]['max_total'],
        'trend'        => $delta > 0 ? self::TREND_UP : ($delta < 0 ? self::TREND_DOWN : self::TREND_SAME),
      ];
    }

    usort($result, static fn(array $a, array $b): int => [$a['student_name'], $a['title']] <=> [$b['student_name'], $b['title']]);
    return $result;
  }

  public static function deltaText(float $delta): string
  {
    return ($delta > 0 ? '+' : '') . number_format($delta, 1, ',', '');
  }

  public static function trendBadge(string $trend): array
  {
    return match ($trend) {
      self::TREND_UP   => ['Melhorou', 'badge--success'],
      self::TREND_DOWN => ['Caiu', 'badge--error'],
      default          => ['Manteve', 'badge--neutral'],
    };
  }
}
