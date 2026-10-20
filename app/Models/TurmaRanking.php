<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Ranking opcional da Turma, só com a Prática. Pontos = soma, por Questão do
 * banco, da melhor nota no período (escala 0–10). Os limites de período são
 * calculados no banco (CURDATE), para não depender do fuso do PHP.
 */
class TurmaRanking extends Model
{
  public const MODE_OFF     = 'off';
  public const MODE_WEEKLY  = 'weekly';
  public const MODE_MONTHLY = 'monthly';
  public const MODE_ALL     = 'all';
  public const MODES = [self::MODE_OFF, self::MODE_WEEKLY, self::MODE_MONTHLY, self::MODE_ALL];
  public const TOP = 5;

  protected string $table = 'turmas';

  public function setMode(int $turmaId, string $mode): bool
  {
    if (!in_array($mode, self::MODES, true)) {
      return false;
    }
    $this->db->execute("UPDATE turmas SET ranking_mode = ? WHERE id = ?", [$mode, $turmaId]);
    return true;
  }

  /**
   * Classificação dos Alunos ativos com pontos no período (posição com empates: 1, 2, 2, 4).
   *
   * @param bool $previous true = período anterior (semana/mês passado)
   * @return array<int, array{student_id: int, name: string, anonymous: bool, points: float, position: int}>
   */
  public function standings(array $turma, bool $previous = false): array
  {
    [$start, $end, $params] = $this->bounds($turma, $previous);
    if ($start === null) {
      return [];
    }

    $rows = $this->db->fetchAll(
      "SELECT u.id AS student_id, u.name, u.ranking_anonymous, SUM(b.best) AS points
             FROM (
               SELECT pa.student_id, pa.bank_question_id, MAX(pa.score / NULLIF(pa.max_score, 0) * 10) AS best
                 FROM practice_answers pa
                 JOIN student_turma st ON st.student_id = pa.student_id AND st.turma_id = ? AND st.status = 'active'
                WHERE pa.created_at >= {$start} AND pa.created_at < {$end}
                GROUP BY pa.student_id, pa.bank_question_id
             ) b
             JOIN users u ON u.id = b.student_id AND u.status = 'active'
             GROUP BY u.id, u.name, u.ranking_anonymous
             HAVING points > 0
             ORDER BY points DESC, u.name, u.id",
      array_merge([(int) $turma['id']], $params)
    );

    $result = [];
    $previousPoints = null;
    $position = 0;
    foreach ($rows as $i => $row) {
      $points = round((float) $row['points'], 1);
      if ($points !== $previousPoints) {
        $position = $i + 1;
        $previousPoints = $points;
      }
      $result[] = [
        'student_id' => (int) $row['student_id'],
        'name'       => (string) $row['name'],
        'anonymous'  => (int) $row['ranking_anonymous'] === 1,
        'points'     => $points,
        'position'   => $position,
      ];
    }
    return $result;
  }

  public function activeStudentCount(int $turmaId): int
  {
    $row = $this->db->fetchOne(
      "SELECT COUNT(*) AS c FROM student_turma st JOIN users u ON u.id = st.student_id AND u.status = 'active'
             WHERE st.turma_id = ? AND st.status = 'active'",
      [$turmaId]
    );
    return (int) ($row['c'] ?? 0);
  }

  /** @return array{0: ?string, 1: ?string, 2: array} expressões SQL de início/fim e parâmetros */
  private function bounds(array $turma, bool $previous): array
  {
    $week = "DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)";
    $month = "DATE_FORMAT(CURDATE(), '%Y-%m-01')";

    return match ((string) ($turma['ranking_mode'] ?? self::MODE_OFF)) {
      self::MODE_WEEKLY => $previous
        ? ["DATE_SUB({$week}, INTERVAL 7 DAY)", $week, []]
        : [$week, "DATE_ADD({$week}, INTERVAL 7 DAY)", []],
      self::MODE_MONTHLY => $previous
        ? ["DATE_SUB({$month}, INTERVAL 1 MONTH)", $month, []]
        : [$month, "DATE_ADD({$month}, INTERVAL 1 MONTH)", []],
      self::MODE_ALL => $previous ? [null, null, []] : ['?', "'9999-12-31'", [(string) $turma['created_at']]],
      default => [null, null, []],
    };
  }

  public static function modeLabel(string $mode): string
  {
    return match ($mode) {
      self::MODE_WEEKLY  => 'Semanal',
      self::MODE_MONTHLY => 'Mensal',
      self::MODE_ALL     => 'Desde o início da turma',
      default            => 'Desligado',
    };
  }
}
