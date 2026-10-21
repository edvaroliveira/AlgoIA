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

  /** @return array{block: float, show: float} */
  public function settings(): array
  {
    $settings = new SystemSetting($this->db);
    $read = function (string $key) use ($settings): float {
      [$min, $max] = self::RANGES[$key];
      $raw = $settings->get($key, '');
      $value = is_numeric($raw) ? (float) $raw : self::DEFAULTS[$key];

      return min($max, max($min, $value));
    };

    return ['block' => $read(self::SETTING_BLOCK), 'show' => $read(self::SETTING_SHOW)];
  }

  /**
   * Relatório por Questão (mesma ordem de $questions).
   *
   * $excludeBankExerciseId: o próprio item, quando é uma Sugestão sendo avaliada;
   * nesse caso entram só as Sugestões anteriores a ela.
   *
   * @param list<array{text: string}> $questions
   * @return array{
   *   questions: list<array{pct: float, blocked: bool, matches: list<array<string, mixed>>}>,
   *   blocked: int, total: int, block: float, show: float
   * }
   */
  public function report(array $questions, ?int $excludeBankExerciseId = null): array
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

    return ['questions' => $rows, 'blocked' => $blocked, 'total' => count($questions), 'block' => $limits['block'], 'show' => $limits['show']];
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
