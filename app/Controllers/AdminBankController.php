<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BankExercise;
use App\Models\Category;
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

    View::render('admin/bank/index', [
      'status'     => $status,
      'tabs'       => self::TABS,
      'counts'     => $counts,
      'items'      => $this->bank->listByStatus($status, $pagination['perPage'], $pagination['offset']),
      'categoryTree' => (new Category())->tree(true),
      'pagination' => $pagination,
    ]);
  }

  public function approve(string $id): void
  {
    Request::validateCsrf();
    [$categoryId, $themeIds] = $this->selection(BankExercise::STATUS_SUGGESTED);

    if ($this->bank->approve((int) $id, (int) Auth::id(), $categoryId, $themeIds)) {
      AuditService::record('admin.bank.approve', 'bank_exercise', (int) $id, ['category_id' => $categoryId, 'theme_ids' => $themeIds]);
      $this->done('success', 'Sugestão aprovada e incluída no banco.', BankExercise::STATUS_SUGGESTED);
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
    AuditService::record('admin.bank.add', 'bank_exercise', $bankId, ['exercise_id' => (int) $id, 'category_id' => $categoryId, 'theme_ids' => $themeIds]);
    $session->flash('success', 'Exercício incluído no banco.');
    View::redirect("/admin/exercises/{$id}");
  }

  private function done(string $type, string $message, string $status): never
  {
    global $session;
    $session->flash($type, $message);
    View::redirect('/admin/bank?status=' . $status);
  }
}
