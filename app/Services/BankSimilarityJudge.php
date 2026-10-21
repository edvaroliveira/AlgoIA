<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

/**
 * Passo do cron: manda à IA os pares duvidosos de Sugestões pendentes (entre o
 * limite de exibição e o de bloqueio) que ainda não têm veredito. Só roda com o
 * juiz ligado nas Configurações. Cada par é julgado uma vez.
 */
final class BankSimilarityJudge
{
  /** Pares por execução do cron: limita custo e tempo. */
  public const PAIRS_PER_RUN = 30;

  private Database $db;
  private BankSimilarityService $similarity;
  private ?OpenAIService $ai;

  public function __construct(?Database $db = null, ?OpenAIService $ai = null)
  {
    $this->db = $db ?? Database::getInstance();
    $this->similarity = new BankSimilarityService($this->db);
    $this->ai = $ai;
  }

  /** @return array{enabled: bool, judged: int, failed: int} */
  public function run(int $limit = self::PAIRS_PER_RUN): array
  {
    if (!$this->similarity->settings()['judge']) {
      return ['enabled' => false, 'judged' => 0, 'failed' => 0];
    }

    $ai = $this->ai ?? new OpenAIService($this->db);
    $model = (string) ((require ROOT_PATH . '/config/openai.php')['model'] ?? '');
    $judged = 0;
    $failed = 0;
    foreach ($this->similarity->pendingJudgments($limit) as $pair) {
      try {
        $verdict = $ai->judgeSameQuestion($pair['text'], $pair['match_text']);
      } catch (\RuntimeException $e) {
        // Serviço fora do ar ou resposta inválida: para aqui e tenta no próximo cron
        error_log('similarity judge failed: ' . $e->getMessage());
        $failed++;
        break;
      }
      $this->similarity->saveJudgment(
        $pair['bank_question_id'],
        $pair['match_bank_question_id'],
        $pair['pct'],
        $verdict['same'],
        $verdict['reason'],
        $model
      );
      $judged++;
    }

    return ['enabled' => true, 'judged' => $judged, 'failed' => $failed];
  }
}
