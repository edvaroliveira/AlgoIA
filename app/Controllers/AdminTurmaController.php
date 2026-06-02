<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuditService;
use Core\Auth;
use Core\Request;
use Core\View;

class AdminTurmaController extends AdminBaseController
{
  public function turmas(): void
  {
    Auth::requireAdmin();

    $filters    = $this->getTurmaFiltersFromRequest();
    $pagination = $this->buildPagination('/admin/turmas', $filters, $this->turmas->countForAdmin($filters));
    $turmas     = $this->turmas->getAllForAdmin($filters, $pagination['perPage'], $pagination['offset']);

    View::render('admin/turmas/index', [
      'turmas'        => $turmas,
      'filters'       => $filters,
      'filterPresets' => $this->getFilterPresets('turmas'),
      'pagination'    => $pagination,
    ]);
  }

  public function exportTurmas(): void
  {
    Auth::requireAdmin();

    $turmas = $this->turmas->getAllForAdmin($this->getTurmaFiltersFromRequest(), null, null);

    $this->streamCsvDownload(
      'turmas-' . date('Ymd-His') . '.csv',
      ['turma', 'docente', 'chave', 'alunos_ativos', 'pendencias', 'exercicios', 'situacao'],
      $turmas,
      function (array $turma): array {
        return [
          (string) ($turma['name']          ?? ''),
          (string) ($turma['teacher_name']  ?? ''),
          (string) ($turma['access_key']    ?? ''),
          (string) ((int) ($turma['active_count']  ?? 0)),
          (string) ((int) ($turma['pending_count'] ?? 0)),
          (string) ((int) ($turma['exercise_count'] ?? 0)),
          $this->buildAdminTurmaSituationText($turma),
        ];
      }
    );
  }

  public function exportTurmasJson(): void
  {
    Auth::requireAdmin();

    $filters = $this->getTurmaFiltersFromRequest();
    $turmas  = $this->turmas->getAllForAdmin($filters, null, null);

    $this->streamJsonDownload(
      'turmas-' . date('Ymd-His') . '.json',
      [
        'filters'     => $filters,
        'exported_at' => date('c'),
        'items'       => array_map(function (array $turma): array {
          return [
            'id'             => (int) ($turma['id']             ?? 0),
            'name'           => (string) ($turma['name']          ?? ''),
            'teacher_name'   => (string) ($turma['teacher_name']  ?? ''),
            'access_key'     => (string) ($turma['access_key']    ?? ''),
            'active_count'   => (int) ($turma['active_count']   ?? 0),
            'pending_count'  => (int) ($turma['pending_count']  ?? 0),
            'exercise_count' => (int) ($turma['exercise_count'] ?? 0),
            'situation'      => $this->buildAdminTurmaSituationText($turma),
            'active'         => (bool) ($turma['active'] ?? false),
          ];
        }, $turmas),
      ]
    );
  }

  public function showTurma(string $id): void
  {
    Auth::requireAdmin();

    $turma = $this->turmas->findForAdmin((int) $id);
    global $session;

    if (!$turma) {
      $session->flash('error', 'Turma não encontrada.');
      View::redirect('/admin/turmas');
    }

    View::render('admin/turmas/show', [
      'turma'        => $turma,
      'pending'      => $this->turmas->getPendingStudents((int) $id),
      'students'     => $this->turmas->getActiveStudents((int) $id),
      'publications' => $this->turmas->getExercisePublicationsForAdmin((int) $id),
      'returnPath'   => $this->buildReturnPathFromRequest('/admin/turmas'),
    ]);
  }

  public function deactivateTurma(string $id): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $turmaId = (int) $id;
    $turma   = $this->turmas->findForAdmin($turmaId);
    global $session;

    if (!$turma) {
      $session->flash('error', 'Turma não encontrada.');
      View::redirect('/admin/turmas');
    }

    if (!(bool) ($turma['active'] ?? true)) {
      $session->flash('error', 'A turma já está inativa.');
      View::redirect('/admin/turmas/' . $turmaId);
    }

    $this->turmas->deactivate($turmaId);
    AuditService::record('admin.turma.deactivate', 'turma', $turmaId, [
      'turma_name'   => $turma['name']         ?? null,
      'teacher_name' => $turma['teacher_name'] ?? null,
    ]);

    $session->flash('success', 'Turma inativada. A chave deixa de aceitar novas entradas.');
    View::redirect('/admin/turmas/' . $turmaId);
  }

  public function reactivateTurma(string $id): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $turmaId = (int) $id;
    $turma   = $this->turmas->findForAdmin($turmaId);
    global $session;

    if (!$turma) {
      $session->flash('error', 'Turma não encontrada.');
      View::redirect('/admin/turmas');
    }

    if ((bool) ($turma['active'] ?? false)) {
      $session->flash('error', 'A turma já está ativa.');
      View::redirect('/admin/turmas/' . $turmaId);
    }

    $this->turmas->reactivate($turmaId);
    AuditService::record('admin.turma.reactivate', 'turma', $turmaId, [
      'turma_name'   => $turma['name']         ?? null,
      'teacher_name' => $turma['teacher_name'] ?? null,
    ]);

    $session->flash('success', 'Turma reativada. A chave voltou a aceitar novas entradas.');
    View::redirect('/admin/turmas/' . $turmaId);
  }

  public function deactivateTurmasBatch(): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $selectedTurmaIds = $this->extractSelectedIdsFromRequest('turma_ids');
    $redirectPath     = $this->buildBatchReturnPath('/admin/turmas');
    global $session;

    if ($selectedTurmaIds === []) {
      $session->flash('error', 'Selecione pelo menos uma turma para inativar.');
      View::redirect($redirectPath);
    }

    $selectedTurmas = [];
    foreach ($selectedTurmaIds as $turmaId) {
      $turma = $this->turmas->findForAdmin($turmaId);
      if (!$turma || !(bool) ($turma['active'] ?? false)) {
        continue;
      }

      $this->turmas->deactivate($turmaId);
      $selectedTurmas[] = $turma;
    }

    if ($selectedTurmas === []) {
      $session->flash('error', 'Nenhuma turma ativa válida foi selecionada.');
      View::redirect($redirectPath);
    }

    AuditService::record('admin.turma.deactivate_batch', 'turma', null, [
      'turmas' => array_map(static function (array $turma): array {
        return [
          'turma_id'     => (int) ($turma['id']           ?? 0),
          'turma_name'   => $turma['name']         ?? null,
          'teacher_name' => $turma['teacher_name'] ?? null,
        ];
      }, $selectedTurmas),
    ]);

    $session->flash('success', count($selectedTurmas) . ' turma(s) inativada(s) com sucesso.');
    View::redirect($redirectPath);
  }

  public function reactivateTurmasBatch(): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $selectedTurmaIds = $this->extractSelectedIdsFromRequest('turma_ids');
    $redirectPath     = $this->buildBatchReturnPath('/admin/turmas');
    global $session;

    if ($selectedTurmaIds === []) {
      $session->flash('error', 'Selecione pelo menos uma turma para reativar.');
      View::redirect($redirectPath);
    }

    $selectedTurmas = [];
    foreach ($selectedTurmaIds as $turmaId) {
      $turma = $this->turmas->findForAdmin($turmaId);
      if (!$turma || (bool) ($turma['active'] ?? false)) {
        continue;
      }

      $this->turmas->reactivate($turmaId);
      $selectedTurmas[] = $turma;
    }

    if ($selectedTurmas === []) {
      $session->flash('error', 'Nenhuma turma inativa válida foi selecionada.');
      View::redirect($redirectPath);
    }

    AuditService::record('admin.turma.reactivate_batch', 'turma', null, [
      'turmas' => array_map(static function (array $turma): array {
        return [
          'turma_id'     => (int) ($turma['id']           ?? 0),
          'turma_name'   => $turma['name']         ?? null,
          'teacher_name' => $turma['teacher_name'] ?? null,
        ];
      }, $selectedTurmas),
    ]);

    $session->flash('success', count($selectedTurmas) . ' turma(s) reativada(s) com sucesso.');
    View::redirect($redirectPath);
  }

  public function closeTurmaPublicationsBatch(string $id): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $turmaId = (int) $id;
    $turma   = $this->turmas->findForAdmin($turmaId);
    global $session;

    if (!$turma) {
      $session->flash('error', 'Turma não encontrada.');
      View::redirect('/admin/turmas');
    }

    $publications       = $this->turmas->getExercisePublicationsForAdmin($turmaId);
    $selectedExerciseIds = $this->extractPublicationExerciseIdsFromRequest();

    if ($selectedExerciseIds === []) {
      $session->flash('error', 'Selecione pelo menos um exercício para encerrar em lote.');
      View::redirect('/admin/turmas/' . $turmaId);
    }

    $selectedPublications = $this->getSelectedTurmaPublications($publications, $selectedExerciseIds);
    if ($selectedPublications === []) {
      $session->flash('error', 'Nenhuma publicação válida foi selecionada.');
      View::redirect('/admin/turmas/' . $turmaId);
    }

    foreach ($selectedPublications as $publication) {
      $this->exercises->closePublication((int) ($publication['id'] ?? 0), $turmaId);
    }

    AuditService::record('admin.turma.close_publications_batch', 'turma', $turmaId, [
      'turma_name'   => $turma['name']         ?? null,
      'teacher_name' => $turma['teacher_name'] ?? null,
      'exercises'    => array_map(static function (array $publication): array {
        return [
          'exercise_id'  => (int) ($publication['id']           ?? 0),
          'title'        => $publication['title']        ?? null,
          'teacher_name' => $publication['teacher_name'] ?? null,
        ];
      }, $selectedPublications),
      'exercise_ids' => array_map(static fn(array $publication): int => (int) ($publication['id'] ?? 0), $selectedPublications),
    ]);

    $session->flash('success', 'Publicações selecionadas da turma foram encerradas.');
    View::redirect('/admin/turmas/' . $turmaId);
  }

  public function reopenTurmaPublicationsBatch(string $id): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $turmaId = (int) $id;
    $turma   = $this->turmas->findForAdmin($turmaId);
    global $session;

    if (!$turma) {
      $session->flash('error', 'Turma não encontrada.');
      View::redirect('/admin/turmas');
    }

    $publications        = $this->turmas->getExercisePublicationsForAdmin($turmaId);
    $selectedExerciseIds = $this->extractPublicationExerciseIdsFromRequest();

    if ($selectedExerciseIds === []) {
      $session->flash('error', 'Selecione pelo menos um exercício para reabrir em lote.');
      View::redirect('/admin/turmas/' . $turmaId);
    }

    $selectedPublications = $this->getSelectedTurmaPublications($publications, $selectedExerciseIds);
    if ($selectedPublications === []) {
      $session->flash('error', 'Nenhuma publicação válida foi selecionada.');
      View::redirect('/admin/turmas/' . $turmaId);
    }

    $reopenUntil = trim((string) Request::post('reopen_until', ''));
    if ($reopenUntil === '') {
      $session->flash('error', 'Informe uma nova data de encerramento para as publicações selecionadas.');
      View::redirect('/admin/turmas/' . $turmaId);
    }

    $reopenTimestamp = strtotime($reopenUntil);
    if ($reopenTimestamp === false || $reopenTimestamp <= time()) {
      $session->flash('error', 'A nova data de encerramento deve estar no futuro.');
      View::redirect('/admin/turmas/' . $turmaId);
    }

    $formattedClosesAt = date('Y-m-d H:i:s', $reopenTimestamp);
    foreach ($selectedPublications as $publication) {
      $this->exercises->reopenPublication((int) ($publication['id'] ?? 0), $turmaId, $formattedClosesAt);
    }

    AuditService::record('admin.turma.reopen_publications_batch', 'turma', $turmaId, [
      'turma_name'    => $turma['name']         ?? null,
      'teacher_name'  => $turma['teacher_name'] ?? null,
      'new_closes_at' => $formattedClosesAt,
      'exercises'     => array_map(static function (array $publication): array {
        return [
          'exercise_id'  => (int) ($publication['id']           ?? 0),
          'title'        => $publication['title']        ?? null,
          'teacher_name' => $publication['teacher_name'] ?? null,
        ];
      }, $selectedPublications),
      'exercise_ids'  => array_map(static fn(array $publication): int => (int) ($publication['id'] ?? 0), $selectedPublications),
    ]);

    $session->flash('success', 'Publicações selecionadas da turma foram reabertas até ' . date('d/m/Y H:i', $reopenTimestamp) . '.');
    View::redirect('/admin/turmas/' . $turmaId);
  }
}
