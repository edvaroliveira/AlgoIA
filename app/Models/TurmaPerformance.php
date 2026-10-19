<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Desempenho de uma Turma para o Docente: grade Alunos × Exercícios publicados
 * (melhor nota de cada Aluno) e, por Exercício, a média de cada Questão e os
 * Motivos de desconto mais comuns. Só contam Alunos com Matrícula ativa e
 * Tentativas feitas nesta Turma.
 */
class TurmaPerformance extends Model
{
  public const CELL_GRADED      = 'graded';
  public const CELL_PENDING     = 'pending';
  public const CELL_IN_PROGRESS = 'in_progress';
  public const CELL_NONE        = 'none';

  protected string $table = 'turmas';

  /**
   * @return array{exercises: array, students: array, turmaAverage: ?float}
   *   students[i] = [id, name, email, cells => [exerciseId => [state, score]], average, neverTried, belowAverage]
   */
  public function grid(int $turmaId): array
  {
    $exercises = $this->publishedExercises($turmaId);
    $students = $this->db->fetchAll(
      "SELECT u.id, u.name, u.email
             FROM student_turma st
             JOIN users u ON u.id = st.student_id
             WHERE st.turma_id = ? AND st.status = 'active'
             ORDER BY u.name, u.id",
      [$turmaId]
    );

    $summary = [];
    foreach ($this->db->fetchAll(
      "SELECT student_id, exercise_id,
              MAX(CASE WHEN status = 'graded' THEN total_score END) AS best,
              SUM(status = 'submitted') AS submitted,
              SUM(status = 'in_progress') AS in_progress
             FROM attempts
             WHERE turma_id = ?
             GROUP BY student_id, exercise_id",
      [$turmaId]
    ) as $row) {
      $summary[(int) $row['student_id']][(int) $row['exercise_id']] = $row;
    }

    $averages = [];
    foreach ($students as &$student) {
      $cells = [];
      $ratios = [];
      foreach ($exercises as $exercise) {
        $row = $summary[(int) $student['id']][(int) $exercise['id']] ?? null;
        if ($row !== null && $row['best'] !== null) {
          $cells[(int) $exercise['id']] = [self::CELL_GRADED, (float) $row['best']];
          if ((float) $exercise['max_total'] > 0) {
            $ratios[] = (float) $row['best'] / (float) $exercise['max_total'];
          }
        } elseif ($row !== null && (int) $row['submitted'] > 0) {
          $cells[(int) $exercise['id']] = [self::CELL_PENDING, null];
        } elseif ($row !== null && (int) $row['in_progress'] > 0) {
          $cells[(int) $exercise['id']] = [self::CELL_IN_PROGRESS, null];
        } else {
          $cells[(int) $exercise['id']] = [self::CELL_NONE, null];
        }
      }
      $student['cells'] = $cells;
      $student['average'] = $ratios !== [] ? array_sum($ratios) / count($ratios) : null;
      $student['neverTried'] = !isset($summary[(int) $student['id']]);
      if ($student['average'] !== null) {
        $averages[] = $student['average'];
      }
    }
    unset($student);

    $turmaAverage = $averages !== [] ? array_sum($averages) / count($averages) : null;
    foreach ($students as &$student) {
      $student['belowAverage'] = $turmaAverage !== null && $student['average'] !== null && $student['average'] < $turmaAverage;
    }
    unset($student);

    return ['exercises' => $exercises, 'students' => $students, 'turmaAverage' => $turmaAverage];
  }

  /**
   * Por Questão do Exercício: média percentual e Motivos de desconto mais comuns,
   * a partir da melhor Tentativa Corrigida de cada Aluno ativo da Turma.
   *
   * @return array<int, array{text: string, max_score: float, answered: int, average: ?float, reasons: array<string, int>}>
   */
  public function byQuestion(int $turmaId, int $exerciseId): array
  {
    $attempts = $this->db->fetchAll(
      "SELECT a.id, a.student_id
             FROM attempts a
             JOIN student_turma st ON st.student_id = a.student_id AND st.turma_id = a.turma_id AND st.status = 'active'
             WHERE a.turma_id = ? AND a.exercise_id = ? AND a.status = 'graded'
             ORDER BY a.total_score DESC, a.id DESC",
      [$turmaId, $exerciseId]
    );
    $best = [];
    foreach ($attempts as $attempt) {
      $best[(int) $attempt['student_id']] ??= (int) $attempt['id'];
    }

    $questions = [];
    foreach ($this->db->fetchAll(
      "SELECT id, text, max_score FROM questions WHERE exercise_id = ? ORDER BY order_index, id",
      [$exerciseId]
    ) as $question) {
      $questions[(int) $question['id']] = [
        'text' => (string) $question['text'],
        'max_score' => (float) $question['max_score'],
        'ratios' => [],
        'reasons' => [],
      ];
    }

    if ($best !== []) {
      $placeholders = implode(',', array_fill(0, count($best), '?'));
      foreach ($this->db->fetchAll(
        "SELECT question_id, ai_score, deduction_reasons_json FROM answers
               WHERE attempt_id IN ({$placeholders}) AND ai_score IS NOT NULL",
        array_values($best)
      ) as $answer) {
        $qid = (int) $answer['question_id'];
        if (!isset($questions[$qid])) {
          continue;
        }
        if ($questions[$qid]['max_score'] > 0) {
          $questions[$qid]['ratios'][] = (float) $answer['ai_score'] / $questions[$qid]['max_score'];
        }
        foreach (DeductionReason::fromJson($answer['deduction_reasons_json']) as $code) {
          $questions[$qid]['reasons'][$code] = ($questions[$qid]['reasons'][$code] ?? 0) + 1;
        }
      }
    }

    return array_values(array_map(static function (array $q): array {
      arsort($q['reasons']);
      return [
        'text' => $q['text'],
        'max_score' => $q['max_score'],
        'answered' => count($q['ratios']),
        'average' => $q['ratios'] !== [] ? array_sum($q['ratios']) / count($q['ratios']) : null,
        'reasons' => array_slice($q['reasons'], 0, 3, true),
      ];
    }, $questions));
  }

  public function publishedExercises(int $turmaId): array
  {
    return $this->db->fetchAll(
      "SELECT e.id, e.title, et.opens_at, et.closes_at,
              (SELECT COALESCE(SUM(q.max_score), 0) FROM questions q WHERE q.exercise_id = e.id) AS max_total
             FROM exercise_turmas et
             JOIN exercises e ON e.id = et.exercise_id AND e.status = 'active'
             WHERE et.turma_id = ?
             ORDER BY et.closes_at, e.title",
      [$turmaId]
    );
  }

  /** Texto da célula, igual na tela e no CSV. */
  public static function cellText(array $cell): string
  {
    return match ($cell[0]) {
      self::CELL_GRADED      => number_format((float) $cell[1], 1, ',', ''),
      self::CELL_PENDING     => 'Pendente',
      self::CELL_IN_PROGRESS => 'Em andamento',
      default                => '',
    };
  }

  public static function percent(?float $ratio): string
  {
    return $ratio === null ? '' : (int) round($ratio * 100) . '%';
  }
}
