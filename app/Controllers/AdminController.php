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

    View::render('admin/dashboard', [
      'totalUsers' => count($users),
      'adminCount' => count(array_filter($users, static fn(array $user): bool => ($user['role'] ?? '') === 'admin')),
      'teacherCount' => count(array_filter($users, static fn(array $user): bool => ($user['role'] ?? '') === 'teacher')),
      'studentCount' => count(array_filter($users, static fn(array $user): bool => ($user['role'] ?? '') === 'student')),
    ]);
  }

  public function users(): void
  {
    Auth::requireAdmin();

    $filters = [
      'search' => trim((string) Request::get('search', '')),
      'role' => trim((string) Request::get('role', '')),
      'status' => trim((string) Request::get('status', '')),
    ];

    $pagination = $this->buildPagination('/admin/users', $filters, $this->users->countForAdmin($filters));
    $users = $this->users->getAllForAdmin($filters, $pagination['perPage'], $pagination['offset']);

    View::render('admin/users/index', [
      'users' => $users,
      'filters' => $filters,
      'pagination' => $pagination,
    ]);
  }

  public function turmas(): void
  {
    Auth::requireAdmin();

    /** @var Turma $turmasModel */
    $turmasModel = $this->turmas;

    $filters = [
      'search' => trim((string) Request::get('search', '')),
      'status' => trim((string) Request::get('status', '')),
    ];

    $pagination = $this->buildPagination('/admin/turmas', $filters, $turmasModel->countForAdmin($filters));
    $turmas = $turmasModel->getAllForAdmin($filters, $pagination['perPage'], $pagination['offset']);

    View::render('admin/turmas/index', [
      'turmas' => $turmas,
      'filters' => $filters,
      'pagination' => $pagination,
    ]);
  }

  public function exercises(): void
  {
    Auth::requireAdmin();

    $filters = [
      'search' => trim((string) Request::get('search', '')),
      'status' => trim((string) Request::get('status', '')),
    ];

    $pagination = $this->buildPagination('/admin/exercises', $filters, $this->exercises->countForAdmin($filters));
    $exercises = $this->exercises->getAllForAdmin($filters, $pagination['perPage'], $pagination['offset']);

    View::render('admin/exercises/index', [
      'exercises' => $exercises,
      'filters' => $filters,
      'pagination' => $pagination,
    ]);
  }

  public function audit(): void
  {
    Auth::requireAdmin();

    $filters = [
      'search' => trim((string) Request::get('search', '')),
      'action' => trim((string) Request::get('action', '')),
      'entity_type' => trim((string) Request::get('entity_type', '')),
      'from_date' => trim((string) Request::get('from_date', '')),
      'to_date' => trim((string) Request::get('to_date', '')),
    ];

    $pagination = $this->buildPagination('/admin/audit', $filters, $this->auditLogs->countForAdmin($filters));
    $logs = $this->auditLogs->getAllForAdmin($filters, $pagination['perPage'], $pagination['offset']);

    View::render('admin/audit/index', [
      'logs' => $logs,
      'filters' => $filters,
      'pagination' => $pagination,
    ]);
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
