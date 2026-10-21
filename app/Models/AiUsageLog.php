<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Uso da IA: uma linha por chamada à OpenAI, com contexto (Docente, Turma,
 * Exercício, Tentativa, Prática), tokens, latência e custo calculado com os
 * preços vigentes no momento (mudar o preço não reescreve o histórico).
 * Também verifica o orçamento mensal e avisa os Admins a 80% e 100%.
 */
class AiUsageLog extends Model
{
  public const KIND_GRADING  = 'grading';
  public const KIND_PRACTICE = 'practice';
  /** IA como juiz da similaridade no Banco de exercícios (mesmo modelo e preço da correção). */
  public const KIND_SIMILARITY = 'similarity';

  public const SETTING_PRICE_INPUT  = 'ai_price_input_per_1m_usd';
  public const SETTING_PRICE_OUTPUT = 'ai_price_output_per_1m_usd';
  public const SETTING_USD_BRL      = 'ai_usd_brl_rate';
  public const SETTING_BUDGET_BRL   = 'ai_monthly_budget_brl';

  /** Preço público do gpt-4o (US$ por 1 milhão de tokens) e câmbio inicial; editáveis no painel. */
  public const DEFAULTS = [
    self::SETTING_PRICE_INPUT  => '2.50',
    self::SETTING_PRICE_OUTPUT => '10.00',
    self::SETTING_USD_BRL      => '5.50',
    self::SETTING_BUDGET_BRL   => '0',
  ];

  public const BUDGET_THRESHOLDS = [80, 100];

  protected string $table = 'ai_usage_logs';

  /** @return array{price_input: float, price_output: float, usd_brl: float, budget_brl: float} */
  public function settings(): array
  {
    $settings = new SystemSetting($this->db);
    $value = fn(string $key): float => max(0.0, (float) $settings->get($key, self::DEFAULTS[$key]));
    return [
      'price_input'  => $value(self::SETTING_PRICE_INPUT),
      'price_output' => $value(self::SETTING_PRICE_OUTPUT),
      'usd_brl'      => $value(self::SETTING_USD_BRL),
      'budget_brl'   => $value(self::SETTING_BUDGET_BRL),
    ];
  }

  public static function costUsd(int $promptTokens, int $completionTokens, float $priceInput, float $priceOutput): float
  {
    return round(($promptTokens * $priceInput + $completionTokens * $priceOutput) / 1_000_000, 6);
  }

  /**
   * Registra uma chamada. Nunca lança: o registro de uso é secundário à correção.
   *
   * @param array<string, int|string|null> $context kind, teacher_id, turma_id, exercise_id, attempt_id, answer_id, student_id, bank_question_id
   */
  public function recordSafely(array $context, string $model, int $promptTokens, int $completionTokens, int $latencyMs, bool $ok, ?string $errorCategory = null): void
  {
    try {
      $kind = in_array($context['kind'] ?? '', [self::KIND_PRACTICE, self::KIND_SIMILARITY], true) ? (string) $context['kind'] : self::KIND_GRADING;
      $settings = $this->settings();
      $id = static fn(string $key): ?int => isset($context[$key]) && (int) $context[$key] > 0 ? (int) $context[$key] : null;

      $this->db->insert(
        "INSERT INTO ai_usage_logs
               (kind, teacher_id, turma_id, exercise_id, attempt_id, answer_id, student_id, bank_question_id,
                model, prompt_tokens, completion_tokens, latency_ms, status, error_category, cost_usd)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
          $kind, $id('teacher_id'), $id('turma_id'), $id('exercise_id'), $id('attempt_id'), $id('answer_id'),
          $id('student_id'), $id('bank_question_id'),
          mb_substr($model, 0, 60), max(0, $promptTokens), max(0, $completionTokens), max(0, $latencyMs),
          $ok ? 'ok' : 'error', $errorCategory !== null ? mb_substr($errorCategory, 0, 40) : null,
          self::costUsd(max(0, $promptTokens), max(0, $completionTokens), $settings['price_input'], $settings['price_output']),
        ]
      );

      $this->checkBudget($settings);
    } catch (\Throwable $e) {
      error_log('ai usage log failed: ' . $e->getMessage());
    }
  }

  /** Gasto do mês corrente (calendário do banco) em US$. */
  public function monthSpendUsd(): float
  {
    $row = $this->db->fetchOne(
      "SELECT COALESCE(SUM(cost_usd), 0) AS c FROM ai_usage_logs WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
    );
    return (float) ($row['c'] ?? 0);
  }

  /** Avisa os Admins ao cruzar 80% e 100% do orçamento, uma vez por mês e por limite. */
  private function checkBudget(array $settings): void
  {
    if ($settings['budget_brl'] <= 0) {
      return;
    }
    $percent = $this->monthSpendUsd() * $settings['usd_brl'] / $settings['budget_brl'] * 100;
    $month = (string) ($this->db->fetchOne("SELECT DATE_FORMAT(CURDATE(), '%Y-%m') AS m")['m'] ?? date('Y-m'));

    foreach (self::BUDGET_THRESHOLDS as $threshold) {
      if ($percent < $threshold) {
        continue;
      }
      $admins = $this->db->fetchAll("SELECT id FROM users WHERE role = 'admin' AND status = 'active'");
      foreach ($admins as $admin) {
        (new Notification($this->db))->notifySafely(
          (int) $admin['id'],
          Notification::TYPE_AI_BUDGET,
          "Uso da IA passou de {$threshold}% do orçamento do mês",
          '/admin/ia',
          'Gasto estimado: R$ ' . number_format($percent * $settings['budget_brl'] / 100, 2, ',', '.')
            . ' de R$ ' . number_format($settings['budget_brl'], 2, ',', '.') . '.',
          "ai_budget:{$month}:{$threshold}"
        );
      }
    }
  }

  // ── Consultas do painel ──────────────────────────────────────────────────

  /**
   * Filtros do painel → WHERE + parâmetros.
   *
   * @param array{period: string, from: string, to: string, teacher_id: int, turma_id: int, kind: string} $filters
   * @return array{0: string, 1: array}
   */
  public static function where(array $filters, string $alias = 'l'): array
  {
    $conditions = [];
    $params = [];
    switch ($filters['period']) {
      case '7d':
        $conditions[] = "{$alias}.created_at >= NOW() - INTERVAL 7 DAY";
        break;
      case 'month':
        $conditions[] = "{$alias}.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
        break;
      case 'prev_month':
        $conditions[] = "{$alias}.created_at >= DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01')";
        $conditions[] = "{$alias}.created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')";
        break;
      case 'custom':
        if ($filters['from'] !== '') {
          $conditions[] = "{$alias}.created_at >= ?";
          $params[] = $filters['from'] . ' 00:00:00';
        }
        if ($filters['to'] !== '') {
          $conditions[] = "{$alias}.created_at <= ?";
          $params[] = $filters['to'] . ' 23:59:59';
        }
        break;
      default: // 30d
        $conditions[] = "{$alias}.created_at >= NOW() - INTERVAL 30 DAY";
    }
    if ($filters['teacher_id'] > 0) {
      $conditions[] = "{$alias}.teacher_id = ?";
      $params[] = $filters['teacher_id'];
    }
    if ($filters['turma_id'] > 0) {
      $conditions[] = "{$alias}.turma_id = ?";
      $params[] = $filters['turma_id'];
    }
    if (in_array($filters['kind'], [self::KIND_GRADING, self::KIND_PRACTICE, self::KIND_SIMILARITY], true)) {
      $conditions[] = "{$alias}.kind = ?";
      $params[] = $filters['kind'];
    }
    return [$conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions), $params];
  }

  public function summary(array $filters): array
  {
    [$where, $params] = self::where($filters);
    $row = $this->db->fetchOne(
      "SELECT COUNT(*) AS calls,
              COALESCE(SUM(l.status = 'error'), 0) AS errors,
              COALESCE(SUM(l.status = 'ok'), 0) AS ok_calls,
              COALESCE(SUM(l.prompt_tokens), 0) AS prompt_tokens,
              COALESCE(SUM(l.completion_tokens), 0) AS completion_tokens,
              COALESCE(SUM(l.cost_usd), 0) AS cost_usd,
              COALESCE(AVG(l.latency_ms), 0) AS avg_latency
             FROM ai_usage_logs l {$where}",
      $params
    ) ?: [];
    $calls = (int) ($row['calls'] ?? 0);
    return [
      'calls'             => $calls,
      'errors'            => (int) ($row['errors'] ?? 0),
      'ok_calls'          => (int) ($row['ok_calls'] ?? 0),
      'prompt_tokens'     => (int) ($row['prompt_tokens'] ?? 0),
      'completion_tokens' => (int) ($row['completion_tokens'] ?? 0),
      'cost_usd'          => (float) ($row['cost_usd'] ?? 0),
      'avg_latency'       => (int) round((float) ($row['avg_latency'] ?? 0)),
      'p95_latency'       => $this->latencyPercentile($filters, $calls, 0.95),
    ];
  }

  /** Percentil de latência sem trazer todas as linhas: uma consulta com OFFSET. */
  private function latencyPercentile(array $filters, int $count, float $percentile): int
  {
    if ($count === 0) {
      return 0;
    }
    [$where, $params] = self::where($filters);
    $offset = (int) floor($percentile * ($count - 1));
    $row = $this->db->fetchOne(
      "SELECT l.latency_ms FROM ai_usage_logs l {$where} ORDER BY l.latency_ms LIMIT 1 OFFSET {$offset}",
      $params
    );
    return (int) ($row['latency_ms'] ?? 0);
  }

  /** Por dia: custo por tipo, chamadas e falhas. */
  public function byDay(array $filters): array
  {
    [$where, $params] = self::where($filters);
    return $this->db->fetchAll(
      "SELECT DATE(l.created_at) AS day,
              COALESCE(SUM(CASE WHEN l.kind = 'grading' THEN l.cost_usd END), 0) AS grading_usd,
              COALESCE(SUM(CASE WHEN l.kind = 'practice' THEN l.cost_usd END), 0) AS practice_usd,
              COALESCE(SUM(CASE WHEN l.kind = 'similarity' THEN l.cost_usd END), 0) AS similarity_usd,
              COUNT(*) AS calls,
              COALESCE(SUM(l.status = 'error'), 0) AS errors
             FROM ai_usage_logs l {$where}
             GROUP BY DATE(l.created_at)
             ORDER BY day",
      $params
    );
  }

  public function byKind(array $filters): array
  {
    [$where, $params] = self::where($filters);
    return $this->db->fetchAll(
      "SELECT l.kind, COUNT(*) AS calls, COALESCE(SUM(l.cost_usd), 0) AS cost_usd
             FROM ai_usage_logs l {$where} GROUP BY l.kind ORDER BY l.kind",
      $params
    );
  }

  public function byTeacher(array $filters, int $limit = 50): array
  {
    return $this->grouped($filters, 'l.teacher_id', "LEFT JOIN users u ON u.id = l.teacher_id", "COALESCE(u.name, 'Prática (sem Docente)')", $limit);
  }

  public function byTurma(array $filters, int $limit = 50): array
  {
    return $this->grouped($filters, 'l.turma_id', "LEFT JOIN turmas t ON t.id = l.turma_id", "COALESCE(t.name, 'Prática (sem Turma)')", $limit);
  }

  public function byModel(array $filters, int $limit = 20): array
  {
    return $this->grouped($filters, 'l.model', '', 'l.model', $limit);
  }

  public function byExercise(array $filters, int $limit = 20): array
  {
    return $this->grouped($filters, 'l.exercise_id', "JOIN exercises e ON e.id = l.exercise_id", 'e.title', $limit);
  }

  public function topPracticeStudents(array $filters, int $limit = 10): array
  {
    $filters['kind'] = self::KIND_PRACTICE;
    return $this->grouped($filters, 'l.student_id', "JOIN users s ON s.id = l.student_id", 's.name', $limit);
  }

  private function grouped(array $filters, string $key, string $join, string $label, int $limit): array
  {
    [$where, $params] = self::where($filters);
    $safeLimit = max(1, $limit);
    return $this->db->fetchAll(
      "SELECT {$key} AS id, {$label} AS label, COUNT(*) AS calls,
              COALESCE(SUM(l.status = 'ok'), 0) AS ok_calls,
              COALESCE(SUM(l.prompt_tokens + l.completion_tokens), 0) AS tokens,
              COALESCE(SUM(l.cost_usd), 0) AS cost_usd
             FROM ai_usage_logs l {$join} {$where}
             GROUP BY {$key}, label
             ORDER BY cost_usd DESC, calls DESC
             LIMIT {$safeLimit}",
      $params
    );
  }

  public function recentFailures(array $filters, int $limit = 10): array
  {
    $filters['kind'] = '';
    [$where, $params] = self::where($filters);
    $where .= ($where === '' ? 'WHERE ' : ' AND ') . "l.status = 'error'";
    $safeLimit = max(1, $limit);
    return $this->db->fetchAll(
      "SELECT l.created_at, l.kind, l.error_category, l.model, u.name AS teacher_name, s.name AS student_name
             FROM ai_usage_logs l
             LEFT JOIN users u ON u.id = l.teacher_id
             LEFT JOIN users s ON s.id = l.student_id
             {$where}
             ORDER BY l.created_at DESC, l.id DESC
             LIMIT {$safeLimit}",
      $params
    );
  }

  /** Linhas para o CSV (até 50 mil). */
  public function exportRows(array $filters): array
  {
    [$where, $params] = self::where($filters);
    return $this->db->fetchAll(
      "SELECT l.*, u.name AS teacher_name, t.name AS turma_name, e.title AS exercise_title, s.name AS student_name
             FROM ai_usage_logs l
             LEFT JOIN users u ON u.id = l.teacher_id
             LEFT JOIN turmas t ON t.id = l.turma_id
             LEFT JOIN exercises e ON e.id = l.exercise_id
             LEFT JOIN users s ON s.id = l.student_id
             {$where}
             ORDER BY l.id
             LIMIT 50000",
      $params
    );
  }

  /** Opções dos filtros do painel. */
  public function teacherOptions(): array
  {
    return $this->db->fetchAll("SELECT id, name FROM users WHERE role = 'teacher' ORDER BY name, id");
  }

  public function turmaOptions(): array
  {
    return $this->db->fetchAll("SELECT id, name FROM turmas ORDER BY name, id");
  }
}
