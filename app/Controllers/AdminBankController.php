<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BankExercise;
use App\Models\Category;
use App\Models\SystemSetting;
use App\Services\BankSimilarityService;
use App\Services\AuditService;
use Core\Auth;
use Core\Request;
use Core\View;

/** Curadoria do Banco de exercícios: Sugestões, itens ativos, recusados e arquivados. */
class AdminBankController extends AdminBaseController
{
  private const TABS = [
    BankExercise::STATUS_SUGGESTED => 'Sugestões',
    BankExercise::STATUS_ACTIVE    => 'No banco',
    BankExercise::STATUS_REJECTED  => 'Recusados',
    BankExercise::STATUS_ARCHIVED  => 'Arquivados',
  ];

  private BankExercise $bank;

  public function __construct()
  {
    parent::__construct();
    $this->bank = new BankExercise();
  }

  public function index(): void
  {
    $status = (string) Request::get('status', BankExercise::STATUS_SUGGESTED);
    if (!isset(self::TABS[$status])) {
      $status = BankExercise::STATUS_SUGGESTED;
    }

    $counts = [];
    foreach (array_keys(self::TABS) as $tabStatus) {
      $counts[$tabStatus] = $this->bank->countByStatus($tabStatus);
    }

    $pagination = $this->buildPagination('/admin/bank', ['status' => $status], $counts[$status]);
    $items = $this->bank->listByStatus($status, $pagination['perPage'], $pagination['offset']);

    // Sugestões: similaridade de cada Questão com o banco, para decidir a aprovação
    if ($status === BankExercise::STATUS_SUGGESTED) {
      $similarity = new BankSimilarityService();
      foreach ($items as &$item) {
        $item['similarity'] = $similarity->report($item['questions'], (int) $item['id'], true);
      }
      unset($item);
    }

    View::render('admin/bank/index', [
      'status'     => $status,
      'tabs'       => self::TABS,
      'counts'     => $counts,
      'items'      => $items,
      'categoryTree' => (new Category())->tree(true),
      'pagination' => $pagination,
    ]);
  }

  public function approve(string $id): void
  {
    Request::validateCsrf();
    [$categoryId, $themeIds] = $this->selection(BankExercise::STATUS_SUGGESTED);

    try {
      $approved = $this->bank->approve((int) $id, (int) Auth::id(), $categoryId, $themeIds);
    } catch (\RuntimeException $e) {
      $this->done('error', $e->getMessage() . ' Recuse a Sugestão.', BankExercise::STATUS_SUGGESTED);
    }

    if ($approved) {
      $result = $this->bank->lastIncorporation();
      AuditService::record('admin.bank.approve', 'bank_exercise', (int) $id, [
        'category_id' => $categoryId, 'theme_ids' => $themeIds,
      ] + self::similarityAudit($result));
      $this->done('success', 'Sugestão aprovada e incluída no banco.' . self::similarityMessage($result), BankExercise::STATUS_SUGGESTED);
    }
    $this->done('error', 'Sugestão não encontrada ou já revisada.', BankExercise::STATUS_SUGGESTED);
  }

  public function reject(string $id): void
  {
    Request::validateCsrf();
    $note = mb_substr(Request::text('review_note'), 0, 1000);

    if ($note === '') {
      $this->done('error', 'Informe o motivo da recusa: ele é mostrado ao Docente.', BankExercise::STATUS_SUGGESTED);
    }

    if ($this->bank->reject((int) $id, (int) Auth::id(), $note)) {
      AuditService::record('admin.bank.reject', 'bank_exercise', (int) $id, ['review_note' => $note]);
      $this->done('success', 'Sugestão recusada.', BankExercise::STATUS_SUGGESTED);
    }
    $this->done('error', 'Sugestão não encontrada ou já revisada.', BankExercise::STATUS_SUGGESTED);
  }

  public function archive(string $id): void
  {
    Request::validateCsrf();

    if ($this->bank->archive((int) $id, (int) Auth::id())) {
      AuditService::record('admin.bank.archive', 'bank_exercise', (int) $id, []);
      $this->done('success', 'Item arquivado: ele não aparece mais no banco.', BankExercise::STATUS_ACTIVE);
    }
    $this->done('error', 'Item ativo não encontrado.', BankExercise::STATUS_ACTIVE);
  }

  public function themes(string $id): void
  {
    Request::validateCsrf();
    $item = $this->bank->find((int) $id);

    if (!$item || $item['status'] !== BankExercise::STATUS_ACTIVE) {
      $this->done('error', 'Item ativo não encontrado.', BankExercise::STATUS_ACTIVE);
    }

    [$categoryId, $themeIds] = $this->selection(BankExercise::STATUS_ACTIVE);
    (new Category())->classify('bank', (int) $id, $categoryId, $themeIds);
    AuditService::record('admin.bank.themes', 'bank_exercise', (int) $id, ['category_id' => $categoryId, 'theme_ids' => $themeIds]);
    $this->done('success', 'Classificação atualizada.', BankExercise::STATUS_ACTIVE);
  }

  /** Categoria + subcategorias do formulário, validadas; em erro, volta para a aba informada. */
  private function selection(string $backStatus, ?string $backPath = null): array
  {
    try {
      return (new Category())->validateSelection(Request::post('category_id'), Request::post('theme_ids', []));
    } catch (\InvalidArgumentException $e) {
      global $session;
      $session->flash('error', $e->getMessage());
      View::redirect($backPath ?? '/admin/bank?status=' . $backStatus);
    }
  }

  /** POST /admin/exercises/{id}/add-to-bank — inclusão direta, já ativa. */
  public function addFromExercise(string $id): void
  {
    Request::validateCsrf();
    global $session;
    [$categoryId, $themeIds] = $this->selection(BankExercise::STATUS_ACTIVE, "/admin/exercises/{$id}#banco");

    try {
      $bankId = $this->bank->createFromExercise((int) $id, BankExercise::STATUS_ACTIVE, (int) Auth::id());
    } catch (\RuntimeException $e) {
      $session->flash('error', $e->getMessage());
      View::redirect("/admin/exercises/{$id}#banco");
    }

    (new Category())->classify('bank', $bankId, $categoryId, $themeIds);
    $result = $this->bank->lastIncorporation();
    AuditService::record('admin.bank.add', 'bank_exercise', $bankId, [
      'exercise_id' => (int) $id, 'category_id' => $categoryId, 'theme_ids' => $themeIds,
    ] + self::similarityAudit($result));
    $session->flash('success', 'Exercício incluído no banco.' . self::similarityMessage($result));
    View::redirect("/admin/exercises/{$id}");
  }

  /** POST /admin/configuracoes/banco — limites de similaridade (system_settings, não .env). */
  public function updateSettings(): void
  {
    Request::validateCsrf();
    global $session;
    $back = '/admin/configuracoes#banco';

    $values = [];
    foreach (BankSimilarityService::RANGES as $key => [$min, $max]) {
      $raw = str_replace(',', '.', trim((string) Request::post($key, '')));
      if (!is_numeric($raw) || (float) $raw < $min || (float) $raw > $max) {
        $session->flash('error', 'Informe percentuais válidos: limite de bloqueio entre 50 e 100; exibição entre 0 e 100.');
        View::redirect($back);
      }
      $values[$key] = (float) $raw;
    }

    $settings = new SystemSetting();
    foreach ($values as $key => $value) {
      $settings->set($key, rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.'), (int) Auth::id());
    }
    // Caixa desmarcada não é enviada: ausente = juiz desligado
    $judge = (string) Request::post(BankSimilarityService::SETTING_JUDGE, '') === '1';
    $settings->set(BankSimilarityService::SETTING_JUDGE, $judge ? '1' : '0', (int) Auth::id());
    $values[BankSimilarityService::SETTING_JUDGE] = $judge;
    AuditService::record('admin.bank_settings.update', 'system_settings', null, $values);
    $session->flash('success', 'Limites de similaridade do banco atualizados.');
    View::redirect($back);
  }

  /** @param array{total: int, kept: int, block: float, excluded: list<array<string, mixed>>} $result */
  private static function similarityAudit(array $result): array
  {
    if ($result['excluded'] === []) {
      return [];
    }

    return ['similarity_block_pct' => $result['block'], 'similarity_excluded' => array_map(
      static fn(array $q): array => [
        'position' => $q['position'], 'pct' => $q['pct'],
        'bank_question_id' => $q['bank_question_id'], 'bank_exercise_id' => $q['bank_exercise_id'],
      ],
      $result['excluded']
    )];
  }

  /** @param array{total: int, kept: int, block: float, excluded: list<array<string, mixed>>} $result */
  private static function similarityMessage(array $result): string
  {
    $skipped = count($result['excluded']);
    if ($skipped === 0) {
      return '';
    }
    $limit = rtrim(rtrim(number_format($result['block'], 1, ',', ''), '0'), ',');

    return " {$result['kept']} de {$result['total']} Questões incorporadas; {$skipped} não "
      . ($skipped === 1 ? 'entrou' : 'entraram') . " por similaridade ≥ {$limit}% com o banco.";
  }

  private function done(string $type, string $message, string $status): never
  {
    global $session;
    $session->flash($type, $message);
    View::redirect('/admin/bank?status=' . $status);
  }
}
