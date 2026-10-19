<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BankExercise;
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
      'pagination' => $pagination,
    ]);
  }

  public function approve(string $id): void
  {
    Request::validateCsrf();
    $themes = BankExercise::normalizeThemeNames((string) Request::post('themes', ''));

    if ($this->bank->approve((int) $id, (int) Auth::id(), $themes)) {
      AuditService::record('admin.bank.approve', 'bank_exercise', (int) $id, ['themes' => $themes]);
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

    $themes = BankExercise::normalizeThemeNames((string) Request::post('themes', ''));
    $this->bank->setThemes((int) $id, $themes);
    AuditService::record('admin.bank.themes', 'bank_exercise', (int) $id, ['themes' => $themes]);
    $this->done('success', 'Temas atualizados.', BankExercise::STATUS_ACTIVE);
  }

  /** POST /admin/exercises/{id}/add-to-bank — inclusão direta, já ativa. */
  public function addFromExercise(string $id): void
  {
    Request::validateCsrf();
    global $session;

    try {
      $bankId = $this->bank->createFromExercise((int) $id, BankExercise::STATUS_ACTIVE, (int) Auth::id());
    } catch (\RuntimeException $e) {
      $session->flash('error', $e->getMessage());
      View::redirect("/admin/exercises/{$id}");
    }

    $themes = BankExercise::normalizeThemeNames((string) Request::post('themes', ''));
    $this->bank->setThemes($bankId, $themes);
    AuditService::record('admin.bank.add', 'bank_exercise', $bankId, ['exercise_id' => (int) $id, 'themes' => $themes]);
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
