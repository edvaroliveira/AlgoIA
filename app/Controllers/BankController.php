<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BankExercise;
use App\Models\Exercise;
use App\Services\AuditService;
use App\Services\BankImportService;
use Core\Auth;
use Core\Request;
use Core\View;

/** Banco de exercícios do lado do Docente: escolher e Importar Questões para um Rascunho. */
class BankController
{
  private Exercise $exercises;
  private BankExercise $bank;

  public function __construct()
  {
    $this->exercises = new Exercise();
    $this->bank      = new BankExercise();
  }

  /**
   * GET /teacher/bank — abas "Banco institucional" e "Meus exercícios".
   * Com ?exercise={id}, o destino é um Rascunho existente do Docente.
   */
  public function index(): void
  {
    $teacherId = (int) Auth::id();
    $tab       = (string) Request::get('tab', 'bank') === 'own' ? 'own' : 'bank';
    $search    = mb_substr(trim((string) Request::get('q', '')), 0, 100);
    $theme     = mb_substr(trim((string) Request::get('theme', '')), 0, 60);
    $target    = null;

    $targetId = (int) Request::get('exercise', 0);
    if ($targetId > 0) {
      $target = $this->exercises->getWithTurma($targetId);
      Auth::ensure($target && (int) $target['teacher_id'] === $teacherId);
      if (!$this->exercises->canEdit($target)) {
        global $session;
        $session->flash('error', 'Só é possível importar questões para um exercício em Rascunho.');
        View::redirect("/teacher/exercises/{$targetId}");
      }
    }

    $total = $tab === 'own'
      ? $this->bank->countOwnSources($teacherId, $search)
      : $this->bank->countActive($search, $theme);
    $pagination = $this->pagination($total, [
      'tab' => $tab, 'q' => $search, 'theme' => $tab === 'bank' ? $theme : '', 'exercise' => $target ? $targetId : '',
    ]);

    $items = $tab === 'own'
      ? $this->bank->listOwnSources($teacherId, $search, BankExercise::PER_PAGE, $pagination['offset'])
      : $this->bank->listActive($search, $theme, BankExercise::PER_PAGE, $pagination['offset']);

    View::render('teacher/bank/index', [
      'tab'        => $tab,
      'search'     => $search,
      'theme'      => $theme,
      'themes'     => $tab === 'bank' ? $this->bank->activeThemes() : [],
      'items'      => $items,
      'target'     => $target ?: null,
      'pagination' => $pagination,
    ]);
  }

  /** POST /teacher/exercises/{id}/suggest-bank — Sugestão ao banco, para a curadoria do Admin. */
  public function suggest(string $id): void
  {
    Request::validateCsrf();
    global $session;

    $exercise = $this->exercises->getWithTurma((int) $id);
    Auth::ensure($exercise && (int) $exercise['teacher_id'] === (int) Auth::id());

    try {
      $bankId = $this->bank->createFromExercise((int) $id, BankExercise::STATUS_SUGGESTED);
    } catch (\RuntimeException $e) {
      $session->flash('error', $e->getMessage());
      View::redirect("/teacher/exercises/{$id}");
    }

    AuditService::record('teacher.bank.suggest', 'bank_exercise', $bankId, ['exercise_id' => (int) $id]);
    $session->flash('success', 'Sugestão enviada. O exercício entra no banco depois da aprovação da administração.');
    View::redirect("/teacher/exercises/{$id}");
  }

  /** POST /teacher/bank/import — cria um Rascunho novo ou acrescenta a um Rascunho existente. */
  public function import(): void
  {
    Request::validateCsrf();
    global $session;

    $teacherId   = (int) Auth::id();
    $source      = Request::str('source');
    $sourceId    = Request::int('source_id');
    $exerciseId  = Request::int('exercise_id');
    $rawIds      = Request::post('question_ids', []);
    $questionIds = is_array($rawIds) ? $rawIds : [];
    $backPath    = '/teacher/bank' . ($exerciseId > 0 ? '?exercise=' . $exerciseId : '');

    if ($exerciseId > 0) {
      // Rascunho de outro Docente: 403, como nas demais mutações de Exercício.
      $target = $this->exercises->getWithTurma($exerciseId);
      Auth::ensure($target && (int) $target['teacher_id'] === $teacherId);
    }

    $service = new BankImportService();

    try {
      if ($exerciseId > 0) {
        $count = $service->importToDraft($teacherId, $exerciseId, $source, $sourceId, $questionIds);
        $draftId = $exerciseId;
        $mode = 'existing';
      } else {
        $draftId = $service->importToNewDraft($teacherId, $source, $sourceId, $questionIds, Request::str('title'));
        $count = null;
        $mode = 'new';
      }
    } catch (\RuntimeException $e) {
      AuditService::record('teacher.bank.import_denied', 'exercise', $exerciseId > 0 ? $exerciseId : null, [
        'source' => $source,
        'source_id' => $sourceId,
        'reason' => $e->getMessage(),
      ]);
      $session->flash('error', $e->getMessage());
      View::redirect($backPath);
    }

    AuditService::record('teacher.bank.import', 'exercise', $draftId, [
      'mode' => $mode,
      'source' => $source,
      'source_id' => $sourceId,
      'question_ids' => array_values(array_map('intval', $questionIds)),
    ]);

    $session->flash('success', $mode === 'new'
      ? 'Rascunho criado com as questões selecionadas. Revise, exclua ou acrescente questões novas.'
      : "{$count} questão(ões) adicionada(s) ao Rascunho.");
    View::redirect("/teacher/exercises/{$draftId}/questions/create");
  }

  private function pagination(int $total, array $query): array
  {
    $totalPages  = max(1, (int) ceil($total / BankExercise::PER_PAGE));
    $currentPage = min(max(1, (int) Request::get('page', 1)), $totalPages);

    return [
      'path'        => '/teacher/bank',
      'query'       => $query,
      'totalItems'  => $total,
      'totalPages'  => $totalPages,
      'currentPage' => $currentPage,
      'offset'      => ($currentPage - 1) * BankExercise::PER_PAGE,
    ];
  }
}
