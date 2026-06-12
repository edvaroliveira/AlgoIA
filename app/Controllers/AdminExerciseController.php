<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Exercise;
use App\Services\AuditService;
use Core\Auth;
use Core\Request;
use Core\View;

class AdminExerciseController extends AdminBaseController
{
  public function exercises(): void
  {
    Auth::requireAdmin();

    $filters    = $this->getExerciseFiltersFromRequest();
    $pagination = $this->buildPagination('/admin/exercises', $filters, $this->exercises->countForAdmin($filters));
    $exercises  = $this->exercises->getAllForAdmin($filters, $pagination['perPage'], $pagination['offset']);

    View::render('admin/exercises/index', [
      'exercises'     => $exercises,
      'filters'       => $filters,
      'filterPresets' => $this->getFilterPresets('exercises'),
      'pagination'    => $pagination,
    ]);
  }

  public function exportExercises(): void
  {
    Auth::requireAdmin();

    $exercises = $this->exercises->getAllForAdmin($this->getExerciseFiltersFromRequest(), null, null);

    $this->streamCsvDownload(
      'exercises-' . date('Ymd-His') . '.csv',
      ['titulo', 'docente', 'turmas', 'abre_em', 'fecha_em', 'tentativas', 'status'],
      $exercises,
      function (array $exercise): array {
        return [
          (string) ($exercise['title']        ?? ''),
          (string) ($exercise['teacher_name'] ?? ''),
          (string) ($exercise['turma_label']  ?? ''),
          (string) ($exercise['opens_at']     ?? ''),
          (string) ($exercise['closes_at']    ?? ''),
          (string) ((int) ($exercise['attempt_count'] ?? 0)),
          $this->buildAdminExerciseStatusText($exercise),
        ];
      }
    );
  }

  public function exportExercisesJson(): void
  {
    Auth::requireAdmin();

    $filters   = $this->getExerciseFiltersFromRequest();
    $exercises = $this->exercises->getAllForAdmin($filters, null, null);

    $this->streamJsonDownload(
      'exercises-' . date('Ymd-His') . '.json',
      [
        'filters'     => $filters,
        'exported_at' => date('c'),
        'items'       => array_map(function (array $exercise): array {
          return [
            'id'            => (int) ($exercise['id']            ?? 0),
            'title'         => (string) ($exercise['title']        ?? ''),
            'teacher_name'  => (string) ($exercise['teacher_name'] ?? ''),
            'turma_label'   => (string) ($exercise['turma_label']  ?? ''),
            'opens_at'      => (string) ($exercise['opens_at']     ?? ''),
            'closes_at'     => (string) ($exercise['closes_at']    ?? ''),
            'attempt_count' => (int) ($exercise['attempt_count'] ?? 0),
            'status'        => $this->buildAdminExerciseStatusText($exercise),
            'raw_status'    => (string) ($exercise['status']       ?? ''),
          ];
        }, $exercises),
      ]
    );
  }

  public function showExercise(string $id): void
  {
    Auth::requireAdmin();

    $exercise = $this->exercises->findForAdmin((int) $id);
    global $session;

    if (!$exercise) {
      $session->flash('error', 'Exercício não encontrado.');
      View::redirect('/admin/exercises');
    }

    View::render('admin/exercises/show', [
      'exercise'   => $exercise,
      'questions'  => $this->questions->findByExercise((int) $id),
      'results'    => $this->exercises->getResultsForTeacher((int) $id),
      'maxScore'   => $this->questions->getTotalMaxScore((int) $id),
      'returnPath' => $this->buildReturnPathFromRequest('/admin/exercises'),
    ]);
  }

  public function moderateExercise(string $id): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $exerciseId = (int) $id;
    $exercise   = $this->exercises->findForAdmin($exerciseId);
    global $session;

    if (!$exercise) {
      $session->flash('error', 'Exercício não encontrado.');
      View::redirect('/admin/exercises');
    }

    $status = $this->sanitizeReviewStatus(Request::str('admin_review_status'));
    $note   = $this->sanitizeReviewNote(Request::text('admin_review_note'));

    $this->exercises->updateAdminReview($exerciseId, $status, $note, Auth::id());

    if ($status === Exercise::REVIEW_BLOCKED && ($exercise['status'] ?? '') === Exercise::STATUS_ACTIVE) {
      $this->exercises->closePublications($exerciseId);
    }

    AuditService::record('admin.exercise.review_update', 'exercise', $exerciseId, [
      'exercise_title'          => $exercise['title']               ?? null,
      'teacher_name'            => $exercise['teacher_name']        ?? null,
      'previous_review_status'  => $exercise['admin_review_status'] ?? Exercise::REVIEW_APPROVED,
      'new_review_status'       => $status,
      'review_note'             => $note,
      'publications_closed'     => $status === Exercise::REVIEW_BLOCKED && ($exercise['status'] ?? '') === Exercise::STATUS_ACTIVE,
    ]);

    $session->flash('success', 'Moderação do exercício atualizada com sucesso.');
    View::redirect('/admin/exercises/' . $exerciseId);
  }

  public function moderateQuestion(string $id): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $questionId = (int) $id;
    $question   = $this->questions->find($questionId);
    global $session;

    if (!$question) {
      $session->flash('error', 'Questão não encontrada.');
      View::redirect('/admin/exercises');
    }

    $exerciseId = (int) ($question['exercise_id'] ?? 0);
    $exercise   = $this->exercises->findForAdmin($exerciseId);
    if (!$exercise) {
      $session->flash('error', 'Exercício da questão não encontrado.');
      View::redirect('/admin/exercises');
    }

    $status = $this->sanitizeReviewStatus(Request::str('admin_review_status'));
    $note   = $this->sanitizeReviewNote(Request::text('admin_review_note'));

    $updated = $this->questions->updateAdminReviewAndProtectExercise($questionId, $status, $note, Auth::id());
    if (!$updated) {
      $session->flash('error', 'Questão não encontrada durante a atualização.');
      View::redirect('/admin/exercises/' . $exerciseId);
    }

    $publicationsClosed = $status === Exercise::REVIEW_BLOCKED
      && ($exercise['status'] ?? '') === Exercise::STATUS_ACTIVE;
    AuditService::record('admin.question.review_update', 'exercise', $exerciseId, [
      'question_id'            => $questionId,
      'exercise_title'         => $exercise['title']               ?? null,
      'previous_review_status' => $question['admin_review_status'] ?? Exercise::REVIEW_APPROVED,
      'new_review_status'      => $status,
      'review_note'            => $note,
      'publications_closed'    => $publicationsClosed,
    ]);

    $message = 'Moderação da questão atualizada com sucesso.';
    if ($publicationsClosed) {
      $message .= ' As publicações do exercício foram encerradas.';
    }
    $session->flash('success', $message);
    View::redirect('/admin/exercises/' . $exerciseId);
  }

  public function closeExercise(string $id): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $exerciseId = (int) $id;
    $exercise   = $this->exercises->findForAdmin($exerciseId);
    global $session;

    if (!$exercise) {
      $session->flash('error', 'Exercício não encontrado.');
      View::redirect('/admin/exercises');
    }

    if (($exercise['status'] ?? '') !== Exercise::STATUS_ACTIVE || empty($exercise['publication_settings'])) {
      $session->flash('error', 'Este exercício não possui publicações ativas para encerramento administrativo.');
      View::redirect('/admin/exercises/' . $exerciseId);
    }

    $this->exercises->closePublications($exerciseId);
    AuditService::record('admin.exercise.close_publications', 'exercise', $exerciseId, [
      'exercise_title' => $exercise['title']        ?? null,
      'teacher_name'   => $exercise['teacher_name'] ?? null,
    ]);

    $session->flash('success', 'Publicações do exercício encerradas administrativamente.');
    View::redirect('/admin/exercises/' . $exerciseId);
  }

  public function reopenExercise(string $id): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $exerciseId = (int) $id;
    $exercise   = $this->exercises->findForAdmin($exerciseId);
    global $session;

    if (!$exercise) {
      $session->flash('error', 'Exercício não encontrado.');
      View::redirect('/admin/exercises');
    }

    if (($exercise['status'] ?? '') !== Exercise::STATUS_ACTIVE || empty($exercise['publication_settings'])) {
      $session->flash('error', 'Este exercício não possui publicações para reabertura administrativa.');
      View::redirect('/admin/exercises/' . $exerciseId);
    }

    $reopenUntil = trim((string) Request::post('reopen_until', ''));
    if ($reopenUntil === '') {
      $session->flash('error', 'Informe uma nova data de encerramento para reabrir as publicações.');
      View::redirect('/admin/exercises/' . $exerciseId);
    }

    $reopenTimestamp = strtotime($reopenUntil);
    if ($reopenTimestamp === false || $reopenTimestamp <= time()) {
      $session->flash('error', 'A nova data de encerramento deve estar no futuro.');
      View::redirect('/admin/exercises/' . $exerciseId);
    }

    $formattedClosesAt = date('Y-m-d H:i:s', $reopenTimestamp);
    $this->exercises->reopenPublications($exerciseId, $formattedClosesAt);
    AuditService::record('admin.exercise.reopen_publications', 'exercise', $exerciseId, [
      'exercise_title' => $exercise['title']        ?? null,
      'teacher_name'   => $exercise['teacher_name'] ?? null,
      'new_closes_at'  => $formattedClosesAt,
    ]);

    $session->flash('success', 'Publicações do exercício reabertas até ' . date('d/m/Y H:i', $reopenTimestamp) . '.');
    View::redirect('/admin/exercises/' . $exerciseId);
  }

  public function closeExercisesBatch(): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $selectedExerciseIds = $this->extractSelectedIdsFromRequest('exercise_ids');
    $redirectPath        = $this->buildBatchReturnPath('/admin/exercises');
    global $session;

    if ($selectedExerciseIds === []) {
      $session->flash('error', 'Selecione pelo menos um exercício para encerrar.');
      View::redirect($redirectPath);
    }

    $selectedExercises = [];
    foreach ($selectedExerciseIds as $exerciseId) {
      $exercise = $this->exercises->findForAdmin($exerciseId);
      if (!$exercise || ($exercise['status'] ?? '') !== Exercise::STATUS_ACTIVE || empty($exercise['publication_settings'])) {
        continue;
      }

      $this->exercises->closePublications($exerciseId);
      $selectedExercises[] = $exercise;
    }

    if ($selectedExercises === []) {
      $session->flash('error', 'Nenhum exercício ativo válido foi selecionado.');
      View::redirect($redirectPath);
    }

    AuditService::record('admin.exercise.close_batch', 'exercise', null, [
      'exercises' => array_map(static function (array $exercise): array {
        return [
          'exercise_id'  => (int) ($exercise['id']           ?? 0),
          'title'        => $exercise['title']        ?? null,
          'teacher_name' => $exercise['teacher_name'] ?? null,
        ];
      }, $selectedExercises),
    ]);

    $session->flash('success', count($selectedExercises) . ' exercício(s) encerrado(s) com sucesso.');
    View::redirect($redirectPath);
  }

  public function reopenExercisesBatch(): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $selectedExerciseIds = $this->extractSelectedIdsFromRequest('exercise_ids');
    $redirectPath        = $this->buildBatchReturnPath('/admin/exercises');
    global $session;

    if ($selectedExerciseIds === []) {
      $session->flash('error', 'Selecione pelo menos um exercício para reabrir.');
      View::redirect($redirectPath);
    }

    $reopenUntil = trim((string) Request::post('reopen_until', ''));
    if ($reopenUntil === '') {
      $session->flash('error', 'Informe uma nova data de encerramento para os exercícios selecionados.');
      View::redirect($redirectPath);
    }

    $reopenTimestamp = strtotime($reopenUntil);
    if ($reopenTimestamp === false || $reopenTimestamp <= time()) {
      $session->flash('error', 'A nova data de encerramento deve estar no futuro.');
      View::redirect($redirectPath);
    }

    $formattedClosesAt = date('Y-m-d H:i:s', $reopenTimestamp);
    $selectedExercises = [];
    foreach ($selectedExerciseIds as $exerciseId) {
      $exercise = $this->exercises->findForAdmin($exerciseId);
      if (!$exercise || ($exercise['status'] ?? '') !== Exercise::STATUS_ACTIVE || empty($exercise['publication_settings'])) {
        continue;
      }

      $this->exercises->reopenPublications($exerciseId, $formattedClosesAt);
      $selectedExercises[] = $exercise;
    }

    if ($selectedExercises === []) {
      $session->flash('error', 'Nenhum exercício publicado válido foi selecionado.');
      View::redirect($redirectPath);
    }

    AuditService::record('admin.exercise.reopen_batch', 'exercise', null, [
      'new_closes_at' => $formattedClosesAt,
      'exercises'     => array_map(static function (array $exercise): array {
        return [
          'exercise_id'  => (int) ($exercise['id']           ?? 0),
          'title'        => $exercise['title']        ?? null,
          'teacher_name' => $exercise['teacher_name'] ?? null,
        ];
      }, $selectedExercises),
    ]);

    $session->flash('success', count($selectedExercises) . ' exercício(s) reaberto(s) até ' . date('d/m/Y H:i', $reopenTimestamp) . '.');
    View::redirect($redirectPath);
  }

  public function closeExercisePublication(string $id, string $turmaId): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $exerciseId    = (int) $id;
    $targetTurmaId = (int) $turmaId;
    $exercise      = $this->exercises->findForAdmin($exerciseId);
    global $session;

    if (!$exercise) {
      $session->flash('error', 'Exercício não encontrado.');
      View::redirect('/admin/exercises');
    }

    $publication = $this->findExercisePublicationByTurmaId($exercise, $targetTurmaId);
    if (($exercise['status'] ?? '') !== Exercise::STATUS_ACTIVE || $publication === null) {
      $session->flash('error', 'Publicação da turma não encontrada para encerramento administrativo.');
      View::redirect('/admin/exercises/' . $exerciseId);
    }

    $this->exercises->closePublication($exerciseId, $targetTurmaId);
    AuditService::record('admin.exercise.close_publication', 'exercise', $exerciseId, [
      'exercise_title' => $exercise['title']        ?? null,
      'teacher_name'   => $exercise['teacher_name'] ?? null,
      'turma_id'       => $targetTurmaId,
      'turma_name'     => $publication['turma_name']  ?? null,
      'access_key'     => $publication['access_key']  ?? null,
    ]);

    $session->flash('success', 'Publicação da turma encerrada administrativamente.');
    View::redirect('/admin/exercises/' . $exerciseId);
  }

  public function reopenExercisePublication(string $id, string $turmaId): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $exerciseId    = (int) $id;
    $targetTurmaId = (int) $turmaId;
    $exercise      = $this->exercises->findForAdmin($exerciseId);
    global $session;

    if (!$exercise) {
      $session->flash('error', 'Exercício não encontrado.');
      View::redirect('/admin/exercises');
    }

    $publication = $this->findExercisePublicationByTurmaId($exercise, $targetTurmaId);
    if (($exercise['status'] ?? '') !== Exercise::STATUS_ACTIVE || $publication === null) {
      $session->flash('error', 'Publicação da turma não encontrada para reabertura administrativa.');
      View::redirect('/admin/exercises/' . $exerciseId);
    }

    $reopenUntil = trim((string) Request::post('reopen_until', ''));
    if ($reopenUntil === '') {
      $session->flash('error', 'Informe uma nova data de encerramento para a publicação selecionada.');
      View::redirect('/admin/exercises/' . $exerciseId);
    }

    $reopenTimestamp = strtotime($reopenUntil);
    if ($reopenTimestamp === false || $reopenTimestamp <= time()) {
      $session->flash('error', 'A nova data de encerramento deve estar no futuro.');
      View::redirect('/admin/exercises/' . $exerciseId);
    }

    $formattedClosesAt = date('Y-m-d H:i:s', $reopenTimestamp);
    $this->exercises->reopenPublication($exerciseId, $targetTurmaId, $formattedClosesAt);
    AuditService::record('admin.exercise.reopen_publication', 'exercise', $exerciseId, [
      'exercise_title' => $exercise['title']        ?? null,
      'teacher_name'   => $exercise['teacher_name'] ?? null,
      'turma_id'       => $targetTurmaId,
      'turma_name'     => $publication['turma_name']  ?? null,
      'access_key'     => $publication['access_key']  ?? null,
      'new_closes_at'  => $formattedClosesAt,
    ]);

    $session->flash('success', 'Publicação da turma reaberta até ' . date('d/m/Y H:i', $reopenTimestamp) . '.');
    View::redirect('/admin/exercises/' . $exerciseId);
  }

  public function closeExercisePublicationsBatch(string $id): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $exerciseId = (int) $id;
    $exercise   = $this->exercises->findForAdmin($exerciseId);
    global $session;

    if (!$exercise) {
      $session->flash('error', 'Exercício não encontrado.');
      View::redirect('/admin/exercises');
    }

    if (($exercise['status'] ?? '') !== Exercise::STATUS_ACTIVE || empty($exercise['publication_settings'])) {
      $session->flash('error', 'Este exercício não possui publicações para ação em lote.');
      View::redirect('/admin/exercises/' . $exerciseId);
    }

    $selectedTurmaIds = $this->extractPublicationTurmaIdsFromRequest();
    if ($selectedTurmaIds === []) {
      $session->flash('error', 'Selecione pelo menos uma turma para encerrar em lote.');
      View::redirect('/admin/exercises/' . $exerciseId);
    }

    $selectedPublications = $this->getSelectedExercisePublications($exercise, $selectedTurmaIds);
    if ($selectedPublications === []) {
      $session->flash('error', 'Nenhuma publicação válida foi selecionada.');
      View::redirect('/admin/exercises/' . $exerciseId);
    }

    foreach ($selectedPublications as $publication) {
      $this->exercises->closePublication($exerciseId, (int) ($publication['turma_id'] ?? 0));
    }

    AuditService::record('admin.exercise.close_publications_batch', 'exercise', $exerciseId, [
      'exercise_title' => $exercise['title']        ?? null,
      'teacher_name'   => $exercise['teacher_name'] ?? null,
      'turmas'         => array_map(static function (array $publication): array {
        return [
          'turma_id'   => (int) ($publication['turma_id']   ?? 0),
          'turma_name' => $publication['turma_name'] ?? null,
          'access_key' => $publication['access_key'] ?? null,
        ];
      }, $selectedPublications),
    ]);

    $session->flash('success', 'Publicações selecionadas encerradas com sucesso.');
    View::redirect('/admin/exercises/' . $exerciseId);
  }

  public function reopenExercisePublicationsBatch(string $id): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $exerciseId = (int) $id;
    $exercise   = $this->exercises->findForAdmin($exerciseId);
    global $session;

    if (!$exercise) {
      $session->flash('error', 'Exercício não encontrado.');
      View::redirect('/admin/exercises');
    }

    if (($exercise['status'] ?? '') !== Exercise::STATUS_ACTIVE || empty($exercise['publication_settings'])) {
      $session->flash('error', 'Este exercício não possui publicações para ação em lote.');
      View::redirect('/admin/exercises/' . $exerciseId);
    }

    $selectedTurmaIds = $this->extractPublicationTurmaIdsFromRequest();
    if ($selectedTurmaIds === []) {
      $session->flash('error', 'Selecione pelo menos uma turma para reabrir em lote.');
      View::redirect('/admin/exercises/' . $exerciseId);
    }

    $selectedPublications = $this->getSelectedExercisePublications($exercise, $selectedTurmaIds);
    if ($selectedPublications === []) {
      $session->flash('error', 'Nenhuma publicação válida foi selecionada.');
      View::redirect('/admin/exercises/' . $exerciseId);
    }

    $reopenUntil = trim((string) Request::post('reopen_until', ''));
    if ($reopenUntil === '') {
      $session->flash('error', 'Informe uma nova data de encerramento para as publicações selecionadas.');
      View::redirect('/admin/exercises/' . $exerciseId);
    }

    $reopenTimestamp = strtotime($reopenUntil);
    if ($reopenTimestamp === false || $reopenTimestamp <= time()) {
      $session->flash('error', 'A nova data de encerramento deve estar no futuro.');
      View::redirect('/admin/exercises/' . $exerciseId);
    }

    $formattedClosesAt = date('Y-m-d H:i:s', $reopenTimestamp);
    foreach ($selectedPublications as $publication) {
      $this->exercises->reopenPublication($exerciseId, (int) ($publication['turma_id'] ?? 0), $formattedClosesAt);
    }

    AuditService::record('admin.exercise.reopen_publications_batch', 'exercise', $exerciseId, [
      'exercise_title' => $exercise['title']        ?? null,
      'teacher_name'   => $exercise['teacher_name'] ?? null,
      'new_closes_at'  => $formattedClosesAt,
      'turmas'         => array_map(static function (array $publication): array {
        return [
          'turma_id'   => (int) ($publication['turma_id']   ?? 0),
          'turma_name' => $publication['turma_name'] ?? null,
          'access_key' => $publication['access_key'] ?? null,
        ];
      }, $selectedPublications),
    ]);

    $session->flash('success', 'Publicações selecionadas reabertas até ' . date('d/m/Y H:i', $reopenTimestamp) . '.');
    View::redirect('/admin/exercises/' . $exerciseId);
  }

  public function updateExercisePublication(string $id, string $turmaId): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $exerciseId    = (int) $id;
    $targetTurmaId = (int) $turmaId;
    $exercise      = $this->exercises->findForAdmin($exerciseId);
    global $session;

    if (!$exercise) {
      $session->flash('error', 'Exercício não encontrado.');
      View::redirect('/admin/exercises');
    }

    $publication = $this->findExercisePublicationByTurmaId($exercise, $targetTurmaId);
    if (($exercise['status'] ?? '') !== Exercise::STATUS_ACTIVE || $publication === null) {
      $session->flash('error', 'Publicação da turma não encontrada para edição administrativa.');
      View::redirect('/admin/exercises/' . $exerciseId);
    }

    $opensAt     = trim((string) Request::post('opens_at', ''));
    $closesAt    = trim((string) Request::post('closes_at', ''));
    $maxAttempts = max(0, (int) Request::post('max_attempts', 1));

    if (!strtotime($opensAt)) {
      $session->flash('error', 'Data de abertura inválida para a publicação selecionada.');
      View::redirect('/admin/exercises/' . $exerciseId);
    }

    if (!strtotime($closesAt)) {
      $session->flash('error', 'Data de fechamento inválida para a publicação selecionada.');
      View::redirect('/admin/exercises/' . $exerciseId);
    }

    if (strtotime($opensAt) >= strtotime($closesAt)) {
      $session->flash('error', 'A data de fechamento deve ser posterior à data de abertura para a publicação selecionada.');
      View::redirect('/admin/exercises/' . $exerciseId);
    }

    $formattedOpensAt  = date('Y-m-d H:i:s', strtotime($opensAt));
    $formattedClosesAt = date('Y-m-d H:i:s', strtotime($closesAt));
    $this->exercises->updatePublication($exerciseId, $targetTurmaId, $formattedOpensAt, $formattedClosesAt, $maxAttempts);

    AuditService::record('admin.exercise.update_publication', 'exercise', $exerciseId, [
      'exercise_title' => $exercise['title']        ?? null,
      'teacher_name'   => $exercise['teacher_name'] ?? null,
      'turma_id'       => $targetTurmaId,
      'turma_name'     => $publication['turma_name']  ?? null,
      'access_key'     => $publication['access_key']  ?? null,
      'opens_at'       => $formattedOpensAt,
      'closes_at'      => $formattedClosesAt,
      'max_attempts'   => $maxAttempts,
    ]);

    $session->flash('success', 'Janela da publicação atualizada com sucesso.');
    View::redirect('/admin/exercises/' . $exerciseId);
  }
}
