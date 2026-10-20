<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AiUsageLog;
use App\Models\SystemSetting;
use App\Services\AuditService;
use Core\Auth;
use Core\Request;
use Core\View;

/** Uso da IA (só Admin): consumo, custo, gráficos, preços, câmbio e orçamento. */
class AdminAiUsageController extends AdminBaseController
{
  private const PERIODS = ['7d', '30d', 'month', 'prev_month', 'custom'];

  /** GET /admin/ia */
  public function index(): void
  {
    $usage = new AiUsageLog();
    $filters = $this->filters();
    $settings = $usage->settings();
    $summary = $usage->summary($filters);
    $byDay = $usage->byDay($filters);
    $byTeacher = $usage->byTeacher($filters);
    $byTurma = $usage->byTurma($filters);
    $byKind = $usage->byKind($filters);

    View::render('admin/ai_usage', [
      'filters'         => $filters,
      'settings'        => $settings,
      'summary'         => $summary,
      'monthSpendUsd'   => $usage->monthSpendUsd(),
      'byTeacher'       => $byTeacher,
      'byTurma'         => $byTurma,
      'byExercise'      => $usage->byExercise($filters),
      'byModel'         => $usage->byModel($filters),
      'currentModel'    => (string) ((require ROOT_PATH . '/config/openai.php')['model'] ?? ''),
      'topStudents'     => $usage->topPracticeStudents($filters),
      'recentFailures'  => $usage->recentFailures($filters),
      'teacherOptions'  => $usage->teacherOptions(),
      'turmaOptions'    => $usage->turmaOptions(),
      'chartData'       => [
        'days'     => array_map(static fn(array $d): array => [
          'day'      => (string) $d['day'],
          'grading'  => (float) $d['grading_usd'],
          'practice' => (float) $d['practice_usd'],
          'calls'    => (int) $d['calls'],
          'errors'   => (int) $d['errors'],
        ], $byDay),
        'teachers' => array_map(static fn(array $r): array => ['label' => (string) $r['label'], 'cost' => (float) $r['cost_usd']], array_slice($byTeacher, 0, 10)),
        'turmas'   => array_map(static fn(array $r): array => ['label' => (string) $r['label'], 'cost' => (float) $r['cost_usd']], array_slice($byTurma, 0, 10)),
        'kinds'    => array_map(static fn(array $r): array => ['kind' => (string) $r['kind'], 'cost' => (float) $r['cost_usd'], 'calls' => (int) $r['calls']], $byKind),
        'usdBrl'   => $settings['usd_brl'],
      ],
    ]);
  }

  /** POST /admin/ia/configuracoes — preços (US$ por 1M tokens), câmbio e orçamento mensal (R$). */
  public function updateSettings(): void
  {
    Request::validateCsrf();
    global $session;

    $read = static fn(string $field): ?float => is_numeric(str_replace(',', '.', trim((string) Request::post($field, ''))))
      ? (float) str_replace(',', '.', trim((string) Request::post($field, '')))
      : null;
    $values = [
      AiUsageLog::SETTING_PRICE_INPUT  => $read('price_input'),
      AiUsageLog::SETTING_PRICE_OUTPUT => $read('price_output'),
      AiUsageLog::SETTING_USD_BRL      => $read('usd_brl_rate'),
      AiUsageLog::SETTING_BUDGET_BRL   => $read('monthly_budget_brl'),
    ];

    foreach ($values as $value) {
      if ($value === null || $value < 0 || $value > 1_000_000) {
        $session->flash('error', 'Informe números válidos (zero ou positivos) para preços, câmbio e orçamento.');
        View::redirect('/admin/ia');
      }
    }
    if ($values[AiUsageLog::SETTING_USD_BRL] <= 0) {
      $session->flash('error', 'A cotação do dólar deve ser maior que zero.');
      View::redirect('/admin/ia');
    }

    $settings = new SystemSetting();
    foreach ($values as $key => $value) {
      $settings->set($key, (string) $value, (int) Auth::id());
    }
    AuditService::record('admin.ai_settings.update', 'system_settings', null, $values);

    $session->flash('success', 'Configurações de custo da IA salvas. Valem para as próximas chamadas.');
    View::redirect('/admin/ia');
  }

  /** GET /admin/ia/export — uma linha por chamada, com os mesmos filtros da tela. */
  public function export(): void
  {
    $rows = (new AiUsageLog())->exportRows($this->filters());
    $this->streamCsvDownload(
      'uso-ia-' . date('Ymd-His') . '.csv',
      ['quando', 'tipo', 'docente', 'turma', 'exercicio', 'aluno', 'modelo', 'tokens_entrada', 'tokens_saida', 'latencia_ms', 'status', 'erro', 'custo_usd'],
      $rows,
      static fn(array $r): array => [
        $r['created_at'],
        $r['kind'] === AiUsageLog::KIND_PRACTICE ? 'pratica' : 'correcao',
        $r['teacher_name'] ?? '',
        $r['turma_name'] ?? '',
        $r['exercise_title'] ?? '',
        $r['student_name'] ?? '',
        $r['model'],
        $r['prompt_tokens'],
        $r['completion_tokens'],
        $r['latency_ms'],
        $r['status'],
        $r['error_category'] ?? '',
        number_format((float) $r['cost_usd'], 6, ',', ''),
      ]
    );
  }

  /** @return array{period: string, from: string, to: string, teacher_id: int, turma_id: int, kind: string} */
  private function filters(): array
  {
    $period = (string) Request::get('period', '30d');
    $date = static function (string $value): string {
      $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
      return $parsed !== false && $parsed->format('Y-m-d') === $value ? $value : '';
    };
    $kind = (string) Request::get('kind', '');

    return [
      'period'     => in_array($period, self::PERIODS, true) ? $period : '30d',
      'from'       => $date(trim((string) Request::get('from', ''))),
      'to'         => $date(trim((string) Request::get('to', ''))),
      'teacher_id' => max(0, (int) Request::get('teacher_id', 0)),
      'turma_id'   => max(0, (int) Request::get('turma_id', 0)),
      'kind'       => in_array($kind, [AiUsageLog::KIND_GRADING, AiUsageLog::KIND_PRACTICE], true) ? $kind : '',
    ];
  }
}
