<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\Exercise;
use App\Models\Question;
use App\Models\Turma;
use App\Models\User;
use App\Services\AuditService;
use Core\Auth;
use Core\Request;
use Core\View;

class AdminController
{
  private const ADMIN_PER_PAGE = 20;

  private User $users;
  private Turma $turmas;
  private Exercise $exercises;
  private AuditLog $auditLogs;
  private Question $questions;

  public function __construct()
  {
    $this->users = new User();
    $this->turmas = new Turma();
    $this->exercises = new Exercise();
    $this->auditLogs = new AuditLog();
    $this->questions = new Question();
  }

  public function dashboard(): void
  {
    Auth::requireAdmin();

    $users = $this->users->getAllForAdmin();
    $pendingTurmas = $this->turmas->getPendingTurmasForAdmin();
    $closingExercises = $this->exercises->getClosingSoonForAdmin();
    $pendingUsers = $this->users->getRecentPendingForAdmin();
    $recentAdminEvents = $this->auditLogs->getRecentAdminEvents();

    View::render('admin/dashboard', [
      'totalUsers' => count($users),
      'adminCount' => count(array_filter($users, static fn(array $user): bool => ($user['role'] ?? '') === 'admin')),
      'teacherCount' => count(array_filter($users, static fn(array $user): bool => ($user['role'] ?? '') === 'teacher')),
      'studentCount' => count(array_filter($users, static fn(array $user): bool => ($user['role'] ?? '') === 'student')),
      'turmaCount' => $this->turmas->countForAdmin(),
      'exerciseCount' => $this->exercises->countForAdmin(),
      'auditCount' => $this->auditLogs->countForAdmin(),
      'pendingUserCount' => $this->users->countPendingForAdmin(),
      'pendingEnrollmentCount' => $this->turmas->countPendingEnrollmentsForAdmin(),
      'closingSoonCount' => $this->exercises->countClosingSoonForAdmin(),
      'pendingTurmas' => $pendingTurmas,
      'closingExercises' => $closingExercises,
      'pendingUsers' => $pendingUsers,
      'recentAdminEvents' => $recentAdminEvents,
    ]);
  }

  public function users(): void
  {
    Auth::requireAdmin();

    $filters = $this->getUserFiltersFromRequest();

    $pagination = $this->buildPagination('/admin/users', $filters, $this->users->countForAdmin($filters));
    $users = $this->users->getAllForAdmin($filters, $pagination['perPage'], $pagination['offset']);

    View::render('admin/users/index', [
      'users' => $users,
      'filters' => $filters,
      'pagination' => $pagination,
    ]);
  }

  public function exportUsers(): void
  {
    Auth::requireAdmin();

    $filters = $this->getUserFiltersFromRequest();
    $users = $this->users->getAllForAdmin($filters, null, null);

    $this->streamCsvDownload(
      'users-' . date('Ymd-His') . '.csv',
      ['nome', 'email', 'perfil', 'status', 'contexto', 'criado_em'],
      $users,
      function (array $user): array {
        return [
          (string) ($user['name'] ?? ''),
          (string) ($user['email'] ?? ''),
          (string) ($user['role'] ?? ''),
          (string) ($user['status'] ?? ''),
          $this->buildAdminUserContextText($user),
          (string) ($user['created_at'] ?? ''),
        ];
      }
    );
  }

  public function exportUsersJson(): void
  {
    Auth::requireAdmin();

    $filters = $this->getUserFiltersFromRequest();
    $users = $this->users->getAllForAdmin($filters, null, null);

    $this->streamJsonDownload(
      'users-' . date('Ymd-His') . '.json',
      [
        'filters' => $filters,
        'exported_at' => date('c'),
        'items' => array_map(function (array $user): array {
          return [
            'id' => (int) ($user['id'] ?? 0),
            'name' => (string) ($user['name'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'role' => (string) ($user['role'] ?? ''),
            'status' => (string) ($user['status'] ?? ''),
            'context' => $this->buildAdminUserContextText($user),
            'created_at' => (string) ($user['created_at'] ?? ''),
          ];
        }, $users),
      ]
    );
  }

  public function turmas(): void
  {
    Auth::requireAdmin();

    /** @var Turma $turmasModel */
    $turmasModel = $this->turmas;

    $filters = $this->getTurmaFiltersFromRequest();

    $pagination = $this->buildPagination('/admin/turmas', $filters, $turmasModel->countForAdmin($filters));
    $turmas = $turmasModel->getAllForAdmin($filters, $pagination['perPage'], $pagination['offset']);

    View::render('admin/turmas/index', [
      'turmas' => $turmas,
      'filters' => $filters,
      'pagination' => $pagination,
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
          (string) ($turma['name'] ?? ''),
          (string) ($turma['teacher_name'] ?? ''),
          (string) ($turma['access_key'] ?? ''),
          (string) ((int) ($turma['active_count'] ?? 0)),
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
    $turmas = $this->turmas->getAllForAdmin($filters, null, null);

    $this->streamJsonDownload(
      'turmas-' . date('Ymd-His') . '.json',
      [
        'filters' => $filters,
        'exported_at' => date('c'),
        'items' => array_map(function (array $turma): array {
          return [
            'id' => (int) ($turma['id'] ?? 0),
            'name' => (string) ($turma['name'] ?? ''),
            'teacher_name' => (string) ($turma['teacher_name'] ?? ''),
            'access_key' => (string) ($turma['access_key'] ?? ''),
            'active_count' => (int) ($turma['active_count'] ?? 0),
            'pending_count' => (int) ($turma['pending_count'] ?? 0),
            'exercise_count' => (int) ($turma['exercise_count'] ?? 0),
            'situation' => $this->buildAdminTurmaSituationText($turma),
            'active' => (bool) ($turma['active'] ?? false),
          ];
        }, $turmas),
      ]
    );
  }

  public function exercises(): void
  {
    Auth::requireAdmin();

    $filters = $this->getExerciseFiltersFromRequest();

    $pagination = $this->buildPagination('/admin/exercises', $filters, $this->exercises->countForAdmin($filters));
    $exercises = $this->exercises->getAllForAdmin($filters, $pagination['perPage'], $pagination['offset']);

    View::render('admin/exercises/index', [
      'exercises' => $exercises,
      'filters' => $filters,
      'pagination' => $pagination,
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
          (string) ($exercise['title'] ?? ''),
          (string) ($exercise['teacher_name'] ?? ''),
          (string) ($exercise['turma_label'] ?? ''),
          (string) ($exercise['opens_at'] ?? ''),
          (string) ($exercise['closes_at'] ?? ''),
          (string) ((int) ($exercise['attempt_count'] ?? 0)),
          $this->buildAdminExerciseStatusText($exercise),
        ];
      }
    );
  }

  public function updateExercisePublication(string $id, string $turmaId): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $exerciseId = (int) $id;
    $targetTurmaId = (int) $turmaId;
    $exercise = $this->exercises->findForAdmin($exerciseId);
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

    $opensAt = trim((string) Request::post('opens_at', ''));
    $closesAt = trim((string) Request::post('closes_at', ''));
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

    $formattedOpensAt = date('Y-m-d H:i:s', strtotime($opensAt));
    $formattedClosesAt = date('Y-m-d H:i:s', strtotime($closesAt));
    $this->exercises->updatePublication($exerciseId, $targetTurmaId, $formattedOpensAt, $formattedClosesAt, $maxAttempts);

    AuditService::record('admin.exercise.update_publication', 'exercise', $exerciseId, [
      'exercise_title' => $exercise['title'] ?? null,
      'teacher_name' => $exercise['teacher_name'] ?? null,
      'turma_id' => $targetTurmaId,
      'turma_name' => $publication['turma_name'] ?? null,
      'access_key' => $publication['access_key'] ?? null,
      'opens_at' => $formattedOpensAt,
      'closes_at' => $formattedClosesAt,
      'max_attempts' => $maxAttempts,
    ]);

    $session->flash('success', 'Janela da publicação atualizada com sucesso.');
    View::redirect('/admin/exercises/' . $exerciseId);
  }

  public function closeExercisePublicationsBatch(string $id): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $exerciseId = (int) $id;
    $exercise = $this->exercises->findForAdmin($exerciseId);
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
      'exercise_title' => $exercise['title'] ?? null,
      'teacher_name' => $exercise['teacher_name'] ?? null,
      'turmas' => array_map(static function (array $publication): array {
        return [
          'turma_id' => (int) ($publication['turma_id'] ?? 0),
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
    $exercise = $this->exercises->findForAdmin($exerciseId);
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
      'exercise_title' => $exercise['title'] ?? null,
      'teacher_name' => $exercise['teacher_name'] ?? null,
      'new_closes_at' => $formattedClosesAt,
      'turmas' => array_map(static function (array $publication): array {
        return [
          'turma_id' => (int) ($publication['turma_id'] ?? 0),
          'turma_name' => $publication['turma_name'] ?? null,
          'access_key' => $publication['access_key'] ?? null,
        ];
      }, $selectedPublications),
    ]);

    $session->flash('success', 'Publicações selecionadas reabertas até ' . date('d/m/Y H:i', $reopenTimestamp) . '.');
    View::redirect('/admin/exercises/' . $exerciseId);
  }

  public function exportExercisesJson(): void
  {
    Auth::requireAdmin();

    $filters = $this->getExerciseFiltersFromRequest();
    $exercises = $this->exercises->getAllForAdmin($filters, null, null);

    $this->streamJsonDownload(
      'exercises-' . date('Ymd-His') . '.json',
      [
        'filters' => $filters,
        'exported_at' => date('c'),
        'items' => array_map(function (array $exercise): array {
          return [
            'id' => (int) ($exercise['id'] ?? 0),
            'title' => (string) ($exercise['title'] ?? ''),
            'teacher_name' => (string) ($exercise['teacher_name'] ?? ''),
            'turma_label' => (string) ($exercise['turma_label'] ?? ''),
            'opens_at' => (string) ($exercise['opens_at'] ?? ''),
            'closes_at' => (string) ($exercise['closes_at'] ?? ''),
            'attempt_count' => (int) ($exercise['attempt_count'] ?? 0),
            'status' => $this->buildAdminExerciseStatusText($exercise),
            'raw_status' => (string) ($exercise['status'] ?? ''),
          ];
        }, $exercises),
      ]
    );
  }

  public function audit(): void
  {
    Auth::requireAdmin();

    $filters = $this->getAuditFiltersFromRequest();

    $pagination = $this->buildPagination('/admin/audit', $filters, $this->auditLogs->countForAdmin($filters));
    $logs = $this->auditLogs->getAllForAdmin($filters, $pagination['perPage'], $pagination['offset']);

    View::render('admin/audit/index', [
      'logs' => $logs,
      'filters' => $filters,
      'pagination' => $pagination,
    ]);
  }

  public function exportAudit(): void
  {
    Auth::requireAdmin();

    $filters = $this->getAuditFiltersFromRequest();
    $logs = $this->auditLogs->getAllForAdmin($filters, null, null);

    $filename = 'audit-log-' . date('Ymd-His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $output = fopen('php://output', 'wb');
    if ($output === false) {
      http_response_code(500);
      exit('Não foi possível gerar a exportação.');
    }

    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['quando', 'ator_nome', 'ator_email', 'ator_role', 'acao', 'entidade', 'entidade_id', 'contexto', 'ip'], ';');

    foreach ($logs as $log) {
      fputcsv($output, [
        (string) ($log['created_at'] ?? ''),
        (string) ($log['actor_name'] ?? 'Sistema'),
        (string) ($log['actor_email'] ?? ''),
        (string) ($log['actor_role'] ?? ''),
        (string) ($log['action'] ?? ''),
        (string) ($log['entity_type'] ?? ''),
        (string) ($log['entity_id'] ?? ''),
        $this->buildAuditContextText($log),
        (string) ($log['ip_address'] ?? ''),
      ], ';');
    }

    fclose($output);
    exit;
  }

  public function exportAuditJson(): void
  {
    Auth::requireAdmin();

    $filters = $this->getAuditFiltersFromRequest();
    $logs = $this->auditLogs->getAllForAdmin($filters, null, null);

    $this->streamJsonDownload(
      'audit-log-' . date('Ymd-His') . '.json',
      [
        'filters' => $filters,
        'exported_at' => date('c'),
        'items' => array_map(function (array $log): array {
          $metadata = json_decode((string) ($log['metadata_json'] ?? ''), true);

          return [
            'id' => (int) ($log['id'] ?? 0),
            'created_at' => (string) ($log['created_at'] ?? ''),
            'actor_name' => (string) ($log['actor_name'] ?? 'Sistema'),
            'actor_email' => (string) ($log['actor_email'] ?? ''),
            'actor_role' => (string) ($log['actor_role'] ?? ''),
            'action' => (string) ($log['action'] ?? ''),
            'entity_type' => (string) ($log['entity_type'] ?? ''),
            'entity_id' => $log['entity_id'] ?? null,
            'context' => $this->buildAuditContextText($log),
            'metadata' => is_array($metadata) ? $metadata : [],
            'ip_address' => (string) ($log['ip_address'] ?? ''),
          ];
        }, $logs),
      ]
    );
  }

  public function showTurma(string $id): void
  {
    Auth::requireAdmin();

    /** @var Turma $turmasModel */
    $turmasModel = $this->turmas;

    $turma = $turmasModel->findForAdmin((int) $id);
    global $session;

    if (!$turma) {
      $session->flash('error', 'Turma não encontrada.');
      View::redirect('/admin/turmas');
    }

    View::render('admin/turmas/show', [
      'turma' => $turma,
      'pending' => $turmasModel->getPendingStudents((int) $id),
      'students' => $turmasModel->getActiveStudents((int) $id),
      'publications' => $turmasModel->getExercisePublicationsForAdmin((int) $id),
      'returnPath' => $this->buildReturnPathFromRequest('/admin/turmas'),
    ]);
  }

  public function deactivateTurma(string $id): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    /** @var Turma $turmasModel */
    $turmasModel = $this->turmas;

    $turmaId = (int) $id;
    $turma = $turmasModel->findForAdmin($turmaId);
    global $session;

    if (!$turma) {
      $session->flash('error', 'Turma não encontrada.');
      View::redirect('/admin/turmas');
    }

    if (!(bool) ($turma['active'] ?? true)) {
      $session->flash('error', 'A turma já está inativa.');
      View::redirect('/admin/turmas/' . $turmaId);
    }

    $turmasModel->deactivate($turmaId);
    AuditService::record('admin.turma.deactivate', 'turma', $turmaId, [
      'turma_name' => $turma['name'] ?? null,
      'teacher_name' => $turma['teacher_name'] ?? null,
    ]);

    $session->flash('success', 'Turma inativada. A chave deixa de aceitar novas entradas.');
    View::redirect('/admin/turmas/' . $turmaId);
  }

  public function reactivateTurma(string $id): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    /** @var Turma $turmasModel */
    $turmasModel = $this->turmas;

    $turmaId = (int) $id;
    $turma = $turmasModel->findForAdmin($turmaId);
    global $session;

    if (!$turma) {
      $session->flash('error', 'Turma não encontrada.');
      View::redirect('/admin/turmas');
    }

    if ((bool) ($turma['active'] ?? false)) {
      $session->flash('error', 'A turma já está ativa.');
      View::redirect('/admin/turmas/' . $turmaId);
    }

    $turmasModel->reactivate($turmaId);
    AuditService::record('admin.turma.reactivate', 'turma', $turmaId, [
      'turma_name' => $turma['name'] ?? null,
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
    $redirectPath = $this->buildBatchReturnPath('/admin/turmas');
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
          'turma_id' => (int) ($turma['id'] ?? 0),
          'turma_name' => $turma['name'] ?? null,
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
    $redirectPath = $this->buildBatchReturnPath('/admin/turmas');
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
          'turma_id' => (int) ($turma['id'] ?? 0),
          'turma_name' => $turma['name'] ?? null,
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
    $turma = $this->turmas->findForAdmin($turmaId);
    global $session;

    if (!$turma) {
      $session->flash('error', 'Turma não encontrada.');
      View::redirect('/admin/turmas');
    }

    $publications = $this->turmas->getExercisePublicationsForAdmin($turmaId);
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
      'turma_name' => $turma['name'] ?? null,
      'teacher_name' => $turma['teacher_name'] ?? null,
      'exercises' => array_map(static function (array $publication): array {
        return [
          'exercise_id' => (int) ($publication['id'] ?? 0),
          'title' => $publication['title'] ?? null,
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
    $turma = $this->turmas->findForAdmin($turmaId);
    global $session;

    if (!$turma) {
      $session->flash('error', 'Turma não encontrada.');
      View::redirect('/admin/turmas');
    }

    $publications = $this->turmas->getExercisePublicationsForAdmin($turmaId);
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
      'turma_name' => $turma['name'] ?? null,
      'teacher_name' => $turma['teacher_name'] ?? null,
      'new_closes_at' => $formattedClosesAt,
      'exercises' => array_map(static function (array $publication): array {
        return [
          'exercise_id' => (int) ($publication['id'] ?? 0),
          'title' => $publication['title'] ?? null,
          'teacher_name' => $publication['teacher_name'] ?? null,
        ];
      }, $selectedPublications),
      'exercise_ids' => array_map(static fn(array $publication): int => (int) ($publication['id'] ?? 0), $selectedPublications),
    ]);

    $session->flash('success', 'Publicações selecionadas da turma foram reabertas até ' . date('d/m/Y H:i', $reopenTimestamp) . '.');
    View::redirect('/admin/turmas/' . $turmaId);
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
      'exercise' => $exercise,
      'questions' => $this->questions->findByExercise((int) $id),
      'results' => $this->exercises->getResultsForTeacher((int) $id),
      'maxScore' => $this->questions->getTotalMaxScore((int) $id),
      'returnPath' => $this->buildReturnPathFromRequest('/admin/exercises'),
    ]);
  }

  public function closeExercise(string $id): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $exerciseId = (int) $id;
    $exercise = $this->exercises->findForAdmin($exerciseId);
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
      'exercise_title' => $exercise['title'] ?? null,
      'teacher_name' => $exercise['teacher_name'] ?? null,
    ]);

    $session->flash('success', 'Publicações do exercício encerradas administrativamente.');
    View::redirect('/admin/exercises/' . $exerciseId);
  }

  public function reopenExercise(string $id): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $exerciseId = (int) $id;
    $exercise = $this->exercises->findForAdmin($exerciseId);
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
      'exercise_title' => $exercise['title'] ?? null,
      'teacher_name' => $exercise['teacher_name'] ?? null,
      'new_closes_at' => $formattedClosesAt,
    ]);

    $session->flash('success', 'Publicações do exercício reabertas até ' . date('d/m/Y H:i', $reopenTimestamp) . '.');
    View::redirect('/admin/exercises/' . $exerciseId);
  }

  public function closeExercisesBatch(): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $selectedExerciseIds = $this->extractSelectedIdsFromRequest('exercise_ids');
    $redirectPath = $this->buildBatchReturnPath('/admin/exercises');
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
          'exercise_id' => (int) ($exercise['id'] ?? 0),
          'title' => $exercise['title'] ?? null,
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
    $redirectPath = $this->buildBatchReturnPath('/admin/exercises');
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
      'exercises' => array_map(static function (array $exercise): array {
        return [
          'exercise_id' => (int) ($exercise['id'] ?? 0),
          'title' => $exercise['title'] ?? null,
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

    $exerciseId = (int) $id;
    $targetTurmaId = (int) $turmaId;
    $exercise = $this->exercises->findForAdmin($exerciseId);
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
      'exercise_title' => $exercise['title'] ?? null,
      'teacher_name' => $exercise['teacher_name'] ?? null,
      'turma_id' => $targetTurmaId,
      'turma_name' => $publication['turma_name'] ?? null,
      'access_key' => $publication['access_key'] ?? null,
    ]);

    $session->flash('success', 'Publicação da turma encerrada administrativamente.');
    View::redirect('/admin/exercises/' . $exerciseId);
  }

  public function reopenExercisePublication(string $id, string $turmaId): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $exerciseId = (int) $id;
    $targetTurmaId = (int) $turmaId;
    $exercise = $this->exercises->findForAdmin($exerciseId);
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
      'exercise_title' => $exercise['title'] ?? null,
      'teacher_name' => $exercise['teacher_name'] ?? null,
      'turma_id' => $targetTurmaId,
      'turma_name' => $publication['turma_name'] ?? null,
      'access_key' => $publication['access_key'] ?? null,
      'new_closes_at' => $formattedClosesAt,
    ]);

    $session->flash('success', 'Publicação da turma reaberta até ' . date('d/m/Y H:i', $reopenTimestamp) . '.');
    View::redirect('/admin/exercises/' . $exerciseId);
  }

  public function editUser(string $id): void
  {
    Auth::requireAdmin();

    $user = $this->users->find((int) $id);

    global $session;
    if (!$user) {
      $session->flash('error', 'Usuário não encontrado.');
      View::redirect('/admin/users');
    }

    View::render('admin/users/edit', [
      'user' => $user,
    ]);
  }

  public function showUser(string $id): void
  {
    Auth::requireAdmin();

    $userId = (int) $id;
    $user = $this->users->findForAdmin($userId);

    global $session;
    if (!$user) {
      $session->flash('error', 'Usuário não encontrado.');
      View::redirect('/admin/users');
    }

    $teacherTurmas = [];
    $teacherExercises = [];
    $studentTurmas = [];
    $studentAttempts = [];

    if (($user['role'] ?? '') === 'teacher') {
      $teacherTurmas = $this->users->getTeacherTurmasForAdmin($userId);
      $teacherExercises = $this->users->getTeacherExercisesForAdmin($userId);
    }

    if (($user['role'] ?? '') === 'student') {
      $studentTurmas = $this->users->getStudentTurmasForAdmin($userId);
      $studentAttempts = $this->users->getStudentAttemptsForAdmin($userId);
    }

    View::render('admin/users/show', [
      'user' => $user,
      'teacherTurmas' => $teacherTurmas,
      'teacherExercises' => $teacherExercises,
      'studentTurmas' => $studentTurmas,
      'studentAttempts' => $studentAttempts,
      'auditLogs' => $this->auditLogs->getRecentForUserContext($userId),
    ]);
  }

  public function updateUser(string $id): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $userId = (int) $id;
    $user = $this->users->find($userId);

    global $session;
    if (!$user) {
      $session->flash('error', 'Usuário não encontrado.');
      View::redirect('/admin/users');
    }

    $name = Request::str('name');
    $email = Request::email('email');
    $role = Request::str('role');
    $status = Request::str('status');

    $errors = [];
    if (mb_strlen($name) < 3) {
      $errors[] = 'Nome deve ter pelo menos 3 caracteres.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $errors[] = 'E-mail inválido.';
    }
    if (!in_array($role, ['admin', 'teacher', 'student'], true)) {
      $errors[] = 'Perfil inválido.';
    }
    if (!in_array($status, ['active', 'pending', 'inactive'], true)) {
      $errors[] = 'Status inválido.';
    }
    if ($this->users->emailExistsForOther($email, $userId)) {
      $errors[] = 'Já existe outro usuário com este e-mail.';
    }

    $isSelf = $userId === (int) Auth::id();
    if ($isSelf && ($role !== 'admin' || $status !== 'active')) {
      $errors[] = 'Você não pode remover o próprio acesso administrativo ativo.';
    }

    $isCurrentlyLastActiveAdmin = ($user['role'] ?? '') === 'admin'
      && ($user['status'] ?? '') === 'active'
      && $this->users->countActiveAdmins() <= 1;
    $willRemainActiveAdmin = $role === 'admin' && $status === 'active';

    if ($isCurrentlyLastActiveAdmin && !$willRemainActiveAdmin) {
      $errors[] = 'Não é possível alterar o último administrador ativo para um estado sem acesso administrativo ativo.';
    }

    if ($errors) {
      View::render('admin/users/edit', [
        'user' => array_merge($user, [
          'name' => $name,
          'email' => $email,
          'role' => $role,
          'status' => $status,
        ]),
        'errors' => $errors,
      ]);
      return;
    }

    $this->users->updateAdminManagedProfile($userId, $name, $email, $role, $status);
    AuditService::record('admin.user.profile_update', 'user', $userId, [
      'before' => [
        'name' => $user['name'] ?? null,
        'email' => $user['email'] ?? null,
        'role' => $user['role'] ?? null,
        'status' => $user['status'] ?? null,
      ],
      'after' => [
        'name' => $name,
        'email' => $email,
        'role' => $role,
        'status' => $status,
      ],
    ]);

    $session->flash('success', 'Perfil do usuário atualizado com sucesso.');
    View::redirect('/admin/users');
  }

  public function updateUserStatus(string $id): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $userId = (int) $id;
    $targetStatus = Request::str('status');
    $user = $this->users->find($userId);

    global $session;

    if (!$user) {
      $session->flash('error', 'Usuário não encontrado.');
      View::redirect('/admin/users');
    }

    if (!in_array($targetStatus, ['active', 'inactive'], true)) {
      $session->flash('error', 'Status solicitado é inválido.');
      View::redirect('/admin/users');
    }

    if ($targetStatus === 'inactive' && (int) ($user['id'] ?? 0) === (int) Auth::id()) {
      $session->flash('error', 'Você não pode inativar a própria conta administrativa.');
      View::redirect('/admin/users');
    }

    if (
      $targetStatus === 'inactive'
      && ($user['role'] ?? '') === 'admin'
      && ($user['status'] ?? '') === 'active'
      && $this->users->countActiveAdmins() <= 1
    ) {
      $session->flash('error', 'Não é possível inativar o último administrador ativo do sistema.');
      View::redirect('/admin/users');
    }

    $this->users->updateStatus($userId, $targetStatus);
    AuditService::record('admin.user.status_update', 'user', $userId, [
      'target_email' => $user['email'] ?? null,
      'target_role' => $user['role'] ?? null,
      'previous_status' => $user['status'] ?? null,
      'new_status' => $targetStatus,
    ]);

    $session->flash('success', $targetStatus === 'active'
      ? 'Usuário ativado com sucesso.'
      : 'Usuário inativado com sucesso.');
    View::redirect('/admin/users');
  }

  public function resetUserPassword(string $id): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $userId = (int) $id;
    $user = $this->users->find($userId);

    global $session;

    if (!$user) {
      $session->flash('error', 'Usuário não encontrado.');
      View::redirect('/admin/users');
    }

    $temporaryPassword = $this->generateTemporaryPassword();
    $this->users->updatePassword($userId, $temporaryPassword);
    AuditService::record('admin.user.password_reset', 'user', $userId, [
      'target_email' => $user['email'] ?? null,
      'target_role' => $user['role'] ?? null,
    ]);

    $session->flash('success', 'Senha temporária gerada para ' . ($user['email'] ?? 'usuário') . ': ' . $temporaryPassword);
    View::redirect('/admin/users');
  }

  private function generateTemporaryPassword(): string
  {
    return 'AlgoIA' . strtoupper(bin2hex(random_bytes(3))) . '9a';
  }

  private function getUserFiltersFromRequest(): array
  {
    return [
      'search' => trim((string) Request::get('search', '')),
      'role' => trim((string) Request::get('role', '')),
      'status' => trim((string) Request::get('status', '')),
    ];
  }

  private function getTurmaFiltersFromRequest(): array
  {
    return [
      'search' => trim((string) Request::get('search', '')),
      'status' => trim((string) Request::get('status', '')),
      'attention' => trim((string) Request::get('attention', '')),
    ];
  }

  private function getExerciseFiltersFromRequest(): array
  {
    return [
      'search' => trim((string) Request::get('search', '')),
      'status' => trim((string) Request::get('status', '')),
      'timing' => trim((string) Request::get('timing', '')),
    ];
  }

  private function getAuditFiltersFromRequest(): array
  {
    return [
      'search' => trim((string) Request::get('search', '')),
      'action' => trim((string) Request::get('action', '')),
      'entity_type' => trim((string) Request::get('entity_type', '')),
      'from_date' => trim((string) Request::get('from_date', '')),
      'to_date' => trim((string) Request::get('to_date', '')),
    ];
  }

  private function streamCsvDownload(string $filename, array $headers, array $rows, callable $mapper): void
  {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $output = fopen('php://output', 'wb');
    if ($output === false) {
      http_response_code(500);
      exit('Não foi possível gerar a exportação.');
    }

    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, $headers, ';');

    foreach ($rows as $row) {
      fputcsv($output, $mapper($row), ';');
    }

    fclose($output);
    exit;
  }

  private function streamJsonDownload(string $filename, array $payload): void
  {
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
  }

  private function findExercisePublicationByTurmaId(array $exercise, int $turmaId): ?array
  {
    $publications = $exercise['publication_settings'] ?? [];
    if (!is_array($publications)) {
      return null;
    }

    foreach ($publications as $publication) {
      if ((int) ($publication['turma_id'] ?? 0) === $turmaId) {
        return $publication;
      }
    }

    return null;
  }

  private function extractPublicationTurmaIdsFromRequest(): array
  {
    $rawTurmaIds = Request::post('turma_ids', []);
    if (!is_array($rawTurmaIds)) {
      return [];
    }

    $turmaIds = array_values(array_unique(array_filter(array_map('intval', $rawTurmaIds), static fn(int $value): bool => $value > 0)));
    sort($turmaIds);

    return $turmaIds;
  }

  private function extractSelectedIdsFromRequest(string $field): array
  {
    $rawIds = Request::post($field, []);
    if (!is_array($rawIds)) {
      return [];
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', $rawIds), static fn(int $value): bool => $value > 0)));
    sort($ids);

    return $ids;
  }

  private function buildBatchReturnPath(string $basePath): string
  {
    $returnQuery = trim((string) Request::post('return_query', ''));
    if ($returnQuery === '') {
      return $basePath;
    }

    return $basePath . '?' . ltrim($returnQuery, '?');
  }

  private function buildReturnPathFromRequest(string $basePath): string
  {
    $returnTo = trim((string) Request::get('return_to', ''));
    if ($returnTo !== '' && str_starts_with($returnTo, '/') && strpos($returnTo, '://') === false) {
      return $returnTo;
    }

    $returnQuery = trim((string) Request::get('return_query', ''));
    if ($returnQuery === '') {
      return $basePath;
    }

    return $basePath . '?' . ltrim($returnQuery, '?');
  }

  private function extractPublicationExerciseIdsFromRequest(): array
  {
    return $this->extractSelectedIdsFromRequest('exercise_ids');
  }

  private function getSelectedExercisePublications(array $exercise, array $selectedTurmaIds): array
  {
    $publications = $exercise['publication_settings'] ?? [];
    if (!is_array($publications)) {
      return [];
    }

    return array_values(array_filter($publications, static function (array $publication) use ($selectedTurmaIds): bool {
      return in_array((int) ($publication['turma_id'] ?? 0), $selectedTurmaIds, true);
    }));
  }

  private function getSelectedTurmaPublications(array $publications, array $selectedExerciseIds): array
  {
    return array_values(array_filter($publications, static function (array $publication) use ($selectedExerciseIds): bool {
      return in_array((int) ($publication['id'] ?? 0), $selectedExerciseIds, true);
    }));
  }

  private function buildAdminUserContextText(array $user): string
  {
    $role = (string) ($user['role'] ?? 'student');

    if ($role === 'teacher') {
      return (int) ($user['owned_turma_count'] ?? 0) . ' turma(s) · ' . (int) ($user['exercise_count'] ?? 0) . ' exercício(s)';
    }

    if ($role === 'student') {
      return !empty($user['turma_names'])
        ? (string) $user['turma_names']
        : 'Sem turma';
    }

    if ($role === 'admin') {
      return 'Acesso global';
    }

    return '—';
  }

  private function buildAdminTurmaSituationText(array $turma): string
  {
    if (!(bool) ($turma['active'] ?? true)) {
      return 'Inativa';
    }

    if ((int) ($turma['pending_count'] ?? 0) > 0) {
      return 'Com pendências';
    }

    return 'Operação normal';
  }

  private function buildAdminExerciseStatusText(array $exercise): string
  {
    $status = (string) ($exercise['status'] ?? '');
    if ($status === Exercise::STATUS_DRAFT) {
      return 'Rascunho';
    }

    if ($status === Exercise::STATUS_READY) {
      return 'Pronto';
    }

    if ($status !== Exercise::STATUS_ACTIVE) {
      return $status;
    }

    $now = time();
    $opensAt = !empty($exercise['opens_at']) ? strtotime((string) $exercise['opens_at']) : false;
    $closesAt = !empty($exercise['closes_at']) ? strtotime((string) $exercise['closes_at']) : false;

    if ($closesAt !== false && $closesAt < $now) {
      return 'Encerrado';
    }

    if ($opensAt !== false && $closesAt !== false && $opensAt <= $now && $closesAt >= $now) {
      return 'Aberto';
    }

    return 'Agendado';
  }

  private function buildAuditContextText(array $log): string
  {
    $metadata = json_decode((string) ($log['metadata_json'] ?? ''), true);
    if (!is_array($metadata)) {
      return 'Sem metadados adicionais';
    }

    $contextParts = [];
    foreach ($metadata as $key => $value) {
      if (is_array($value)) {
        $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      }

      if ($value === null || $value === '') {
        continue;
      }

      $contextParts[] = $key . ': ' . (string) $value;
    }

    return $contextParts ? implode(' | ', $contextParts) : 'Sem metadados adicionais';
  }

  private function buildPagination(string $path, array $filters, int $totalItems): array
  {
    $requestedPage = max(1, (int) Request::get('page', 1));
    $totalPages = max(1, (int) ceil($totalItems / self::ADMIN_PER_PAGE));
    $currentPage = min($requestedPage, $totalPages);

    return [
      'path' => $path,
      'query' => $filters,
      'perPage' => self::ADMIN_PER_PAGE,
      'totalItems' => $totalItems,
      'totalPages' => $totalPages,
      'currentPage' => $currentPage,
      'offset' => ($currentPage - 1) * self::ADMIN_PER_PAGE,
    ];
  }
}
