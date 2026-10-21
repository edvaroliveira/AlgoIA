<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SystemSetting;
use Core\Database;

/**
 * Similaridade das Questões com o Banco de exercícios.
 *
 * Compara o enunciado com as Questões de itens No banco (active) e de Sugestões
 * pendentes (suggested). Entre Sugestões pendentes iguais, a mais antiga tem
 * precedência: uma Sugestão só é comparada com as anteriores a ela, para duas
 * iguais não se bloquearem mutuamente.
 *
 * A partir do Limite de similaridade a Questão não entra no banco; a partir do
 * limite de exibição, a semelhança aparece para o Admin.
 */
final class BankSimilarityService
{
  public const SETTING_BLOCK = 'bank_similarity_block_pct';
  public const SETTING_SHOW  = 'bank_similarity_show_pct';
  /** IA como juiz dos casos duvidosos (entre o limite de exibição e o de bloqueio). Desligada por padrão. */
  public const SETTING_JUDGE = 'bank_ai_judge_enabled';

  /** Valores padrão: 97% bloqueia (calibrado com as 300 Questões do seed); 85% passa a ser exibido. */
  public const DEFAULTS = [self::SETTING_BLOCK => 97.0, self::SETTING_SHOW => 85.0];

  /** Faixas aceitas na tela de Configurações. */
  public const RANGES = [self::SETTING_BLOCK => [50.0, 100.0], self::SETTING_SHOW => [0.0, 100.0]];

  private const MATCHES_PER_QUESTION = 3;

  private Database $db;

  /** @var list<array<string, mixed>>|null */
  private ?array $candidates = null;

  public function __construct(?Database $db = null)
  {
    $this->db = $db ?? Database::getInstance();
  }

  /** @return array{block: float, show: float, judge: bool} */
  public function settings(): array
  {
    $settings = new SystemSetting($this->db);
    $read = function (string $key) use ($settings): float {
      [$min, $max] = self::RANGES[$key];
      $raw = $settings->get($key, '');
      $value = is_numeric($raw) ? (float) $raw : self::DEFAULTS[$key];

      return min($max, max($min, $value));
    };

    return [
      'block' => $read(self::SETTING_BLOCK),
      'show'  => $read(self::SETTING_SHOW),
      'judge' => $settings->getBool(self::SETTING_JUDGE, false),
    ];
  }

  /**
   * Relatório por Questão (mesma ordem de $questions).
   *
   * $excludeBankExerciseId: o próprio item, quando é uma Sugestão sendo avaliada;
   * nesse caso entram só as Sugestões anteriores a ela.
   *
   * $withJudgments: anexa o veredito da IA (juiz) a cada Questão parecida da
   * faixa duvidosa, quando o juiz está ligado (só para Questões já no banco, com 'id').
   *
   * @param list<array{text: string}> $questions
   * @return array{
   *   questions: list<array{pct: float, blocked: bool, matches: list<array<string, mixed>>}>,
   *   blocked: int, total: int, block: float, show: float, judge: bool
   * }
   */
  public function report(array $questions, ?int $excludeBankExerciseId = null, bool $withJudgments = false): array
  {
    $limits = $this->settings();
    $min = min($limits['block'], $limits['show']);
    $candidates = $this->candidates($excludeBankExerciseId);

    $rows = [];
    $blocked = 0;
    foreach ($questions as $question) {
      $matches = QuestionSimilarity::bestMatches((string) $question['text'], $candidates, $min, self::MATCHES_PER_QUESTION);
      $pct = $matches[0]['pct'] ?? 0.0;
      $isBlocked = $matches !== [] && $pct >= $limits['block'];
      $blocked += $isBlocked ? 1 : 0;
      $rows[] = [
        'pct'     => $pct,
        'blocked' => $isBlocked,
        'matches' => array_values(array_filter($matches, static fn(array $m): bool => $m['pct'] >= $limits['show'] || $m['pct'] >= $limits['block'])),
      ];
    }

    $judge = $withJudgments && $limits['judge'];
    if ($judge) {
      $rows = $this->attachJudgments($questions, $rows, $limits);
    }

    return [
      'questions' => $rows, 'blocked' => $blocked, 'total' => count($questions),
      'block' => $limits['block'], 'show' => $limits['show'], 'judge' => $judge,
    ];
  }

  /** Par na faixa duvidosa: parecido o bastante para mostrar, abaixo do bloqueio. */
  public static function isDoubtful(float $pct, array $limits): bool
  {
    return $pct >= $limits['show'] && $pct < $limits['block'];
  }

  /**
   * Pares duvidosos de Sugestões pendentes ainda sem veredito da IA, da Sugestão
   * mais antiga para a mais nova (para o cron).
   *
   * @return list<array{bank_question_id: int, text: string, match_bank_question_id: int, match_text: string, pct: float}>
   */
  public function pendingJudgments(int $limit): array
  {
    $limits = $this->settings();
    $items = $this->db->fetchAll("SELECT id FROM bank_exercises WHERE status = 'suggested' ORDER BY id");
    $pairs = [];
    foreach ($items as $item) {
      $questions = $this->db->fetchAll(
        "SELECT id, text FROM bank_questions WHERE bank_exercise_id = ? ORDER BY order_index, id",
        [(int) $item['id']]
      );
      $report = $this->report($questions, (int) $item['id'], true);
      foreach ($report['questions'] as $i => $row) {
        foreach ($row['matches'] as $match) {
          if (!array_key_exists('judgment', $match) || $match['judgment'] !== null) {
            continue;
          }
          $pairs[] = [
            'bank_question_id'       => (int) $questions[$i]['id'],
            'text'                   => (string) $questions[$i]['text'],
            'match_bank_question_id' => (int) $match['bank_question_id'],
            'match_text'             => (string) $match['text'],
            'pct'                    => (float) $match['pct'],
          ];
          if (count($pairs) >= $limit) {
            return $pairs;
          }
        }
      }
    }

    return $pairs;
  }

  /** Grava o veredito (um por par; repetir não duplica). */
  public function saveJudgment(int $questionId, int $matchId, float $pct, bool $same, string $reason, string $model): void
  {
    $this->db->execute(
      "INSERT IGNORE INTO bank_similarity_judgments
             (bank_question_id, match_bank_question_id, lexical_pct, verdict, reason, model)
             VALUES (?, ?, ?, ?, ?, ?)",
      [$questionId, $matchId, $pct, $same ? 'same' : 'different', mb_substr($reason, 0, 300), mb_substr($model, 0, 60)]
    );
  }

  /**
   * Em cada Questão parecida da faixa duvidosa: 'judgment' => ['verdict', 'reason'] ou null (pendente).
   * Fora da faixa (bloqueada ou abaixo da exibição) a chave não existe.
   */
  private function attachJudgments(array $questions, array $rows, array $limits): array
  {
    $ids = array_values(array_filter(array_map(static fn(array $q): int => (int) ($q['id'] ?? 0), $questions)));
    $found = [];
    if ($ids !== []) {
      $placeholders = implode(',', array_fill(0, count($ids), '?'));
      foreach ($this->db->fetchAll(
        "SELECT bank_question_id, match_bank_question_id, verdict, reason FROM bank_similarity_judgments
               WHERE bank_question_id IN ({$placeholders})",
        $ids
      ) as $j) {
        $found[(int) $j['bank_question_id']][(int) $j['match_bank_question_id']] = ['verdict' => $j['verdict'], 'reason' => $j['reason']];
      }
    }

    foreach ($rows as $i => &$row) {
      $questionId = (int) ($questions[$i]['id'] ?? 0);
      if ($questionId === 0 || $row['blocked']) {
        continue;
      }
      foreach ($row['matches'] as &$match) {
        if (self::isDoubtful((float) $match['pct'], $limits)) {
          $match['judgment'] = $found[$questionId][(int) $match['bank_question_id']] ?? null;
        }
      }
      unset($match);
    }
    unset($row);

    return $rows;
  }

  /**
   * Questões do banco usadas na comparação, com os trigramas já calculados.
   *
   * @return list<array<string, mixed>>
   */
  private function candidates(?int $excludeBankExerciseId): array
  {
    if ($this->candidates === null) {
      $rows = $this->db->fetchAll(
        "SELECT bq.id AS bank_question_id, bq.text, be.id AS bank_exercise_id, be.title, be.status
               FROM bank_questions bq
               JOIN bank_exercises be ON be.id = bq.bank_exercise_id
               WHERE be.status IN ('active', 'suggested')"
      );
      $this->candidates = array_map(static function (array $row): array {
        $row['bank_exercise_id'] = (int) $row['bank_exercise_id'];
        $row['grams'] = QuestionSimilarity::trigrams((string) $row['text']);
        return $row;
      }, $rows);
    }

    if ($excludeBankExerciseId === null) {
      return $this->candidates;
    }

    // Sugestão sendo avaliada: nada do próprio item; das Sugestões, só as anteriores (precedência da mais antiga)
    return array_values(array_filter(
      $this->candidates,
      static fn(array $c): bool => $c['bank_exercise_id'] !== $excludeBankExerciseId
        && ($c['status'] === 'active' || $c['bank_exercise_id'] < $excludeBankExerciseId)
    ));
  }
}
