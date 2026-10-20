<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttemptEvolution;
use App\Models\Notification;
use App\Models\ThemeProgress;
use Core\Database;

/**
 * Conquistas do Aluno, visíveis só para ele. Calculadas a partir da atividade
 * (Prática, Tentativas, Progresso por Tema, Evolução) e gravadas uma única vez;
 * a primeira vez gera Aviso. Nunca derruba a ação que chamou (syncSafely).
 */
class AchievementService
{
  private const STATIC = [
    'first_steps'      => ['Primeiros passos', 'Tenha sua primeira resposta corrigida, na Prática ou numa Tentativa.'],
    'streak_3'         => ['Sequência de 3 dias', 'Estude 3 dias seguidos (pratique ou envie uma Tentativa).'],
    'streak_7'         => ['Sequência de 7 dias', 'Estude 7 dias seguidos (pratique ou envie uma Tentativa).'],
    'practice_10'      => ['Praticante', 'Tenha 10 correções na Prática.'],
    'practice_50'      => ['Praticante dedicado', 'Tenha 50 correções na Prática.'],
    'perfect_exercise' => ['Nota máxima', 'Tire a nota máxima em um Exercício.'],
    'strong_theme'     => ['Ponto forte', 'Chegue a 70% ou mais em um Tema, com pelo menos 3 respostas corrigidas.'],
    'comeback'         => ['Volta por cima', 'Melhore sua nota ao refazer um Exercício.'],
  ];

  private Database $db;

  public function __construct(?Database $db = null)
  {
    $this->db = $db ?? Database::getInstance();
  }

  /** Grava as conquistas novas (com Aviso) sem propagar erro. */
  public function syncSafely(int $studentId): void
  {
    try {
      $this->sync($studentId);
    } catch (\Throwable $e) {
      error_log("achievements sync failed ({$studentId}): " . $e->getMessage());
    }
  }

  /**
   * Grava as conquistas novas e devolve o catálogo do Aluno.
   *
   * @return array<int, array{code: string, label: string, hint: string, earned_at: ?string}>
   */
  public function sync(int $studentId): array
  {
    $themes = (new ThemeProgress($this->db))->forStudent($studentId);
    $catalog = self::STATIC;
    foreach ($themes as $theme) {
      if ($theme['practicable'] || $theme['answered'] > 0) {
        $catalog['theme_first:' . $theme['id']] = ['Explorador: ' . $theme['name'], 'Tenha uma resposta corrigida em ' . $theme['name'] . '.'];
      }
    }

    $existing = [];
    foreach ($this->db->fetchAll("SELECT code, earned_at FROM student_achievements WHERE student_id = ?", [$studentId]) as $row) {
      $existing[(string) $row['code']] = (string) $row['earned_at'];
    }

    $notifications = new Notification($this->db);
    foreach ($this->earnedCodes($studentId, $themes) as $code) {
      if (isset($existing[$code]) || !isset($catalog[$code])) {
        continue;
      }
      $inserted = $this->db->execute(
        "INSERT IGNORE INTO student_achievements (student_id, code) VALUES (?, ?)",
        [$studentId, $code]
      );
      $existing[$code] = date('Y-m-d H:i:s');
      if ($inserted > 0) {
        $notifications->notifySafely(
          $studentId,
          Notification::TYPE_ACHIEVEMENT,
          'Conquista desbloqueada: ' . $catalog[$code][0],
          '/student/progresso',
          $catalog[$code][1],
          'achievement:' . $code
        );
      }
    }

    $result = [];
    foreach ($catalog as $code => [$label, $hint]) {
      $result[] = ['code' => $code, 'label' => $label, 'hint' => $hint, 'earned_at' => $existing[$code] ?? null];
    }
    return $result;
  }

  /** @return string[] */
  private function earnedCodes(int $studentId, array $themes): array
  {
    $codes = [];

    $practiceCount = (int) ($this->db->fetchOne(
      "SELECT COUNT(*) AS c FROM practice_answers WHERE student_id = ?",
      [$studentId]
    )['c'] ?? 0);
    $gradedAnswers = (int) ($this->db->fetchOne(
      "SELECT COUNT(*) AS c FROM answers ans
             JOIN attempts att ON att.id = ans.attempt_id AND att.status = 'graded' AND att.student_id = ?
             WHERE ans.ai_score IS NOT NULL",
      [$studentId]
    )['c'] ?? 0);

    if ($practiceCount + $gradedAnswers > 0) {
      $codes[] = 'first_steps';
    }
    if ($practiceCount >= 10) {
      $codes[] = 'practice_10';
    }
    if ($practiceCount >= 50) {
      $codes[] = 'practice_50';
    }

    $streak = $this->longestStreak($studentId);
    if ($streak >= 3) {
      $codes[] = 'streak_3';
    }
    if ($streak >= 7) {
      $codes[] = 'streak_7';
    }

    foreach ($themes as $theme) {
      if ($theme['answered'] > 0) {
        $codes[] = 'theme_first:' . $theme['id'];
      }
      if ($theme['level'] === ThemeProgress::LEVEL_STRONG) {
        $codes[] = 'strong_theme';
      }
    }

    $perfect = $this->db->fetchOne(
      "SELECT 1 AS ok FROM attempts a
             WHERE a.student_id = ? AND a.status = 'graded'
               AND (SELECT COALESCE(SUM(q.max_score), 0) FROM questions q WHERE q.exercise_id = a.exercise_id) > 0
               AND a.total_score >= (SELECT SUM(q.max_score) FROM questions q WHERE q.exercise_id = a.exercise_id)
             LIMIT 1",
      [$studentId]
    );
    if ($perfect) {
      $codes[] = 'perfect_exercise';
    }

    foreach ((new AttemptEvolution($this->db))->forStudent($studentId) as $evolution) {
      if ($evolution['trend'] === AttemptEvolution::TREND_UP) {
        $codes[] = 'comeback';
        break;
      }
    }

    return array_values(array_unique($codes));
  }

  /** Maior sequência de dias seguidos com Prática corrigida ou Tentativa enviada. */
  private function longestStreak(int $studentId): int
  {
    $days = array_column($this->db->fetchAll(
      "SELECT DATE(created_at) AS d FROM practice_answers WHERE student_id = ?
       UNION
       SELECT DATE(submitted_at) AS d FROM attempts WHERE student_id = ? AND submitted_at IS NOT NULL
       ORDER BY d",
      [$studentId, $studentId]
    ), 'd');

    $best = 0;
    $run = 0;
    $previous = null;
    foreach ($days as $day) {
      $current = new \DateTimeImmutable((string) $day);
      $run = ($previous !== null && $previous->modify('+1 day') == $current) ? $run + 1 : 1;
      $best = max($best, $run);
      $previous = $current;
    }
    return $best;
  }
}
