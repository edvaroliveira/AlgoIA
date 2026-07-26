<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuditService;
use Core\Auth;
use Core\Request;
use Core\View;

class AdminUserController extends AdminBaseController
{
  public function users(): void
  {

    $filters    = $this->getUserFiltersFromRequest();
    $pagination = $this->buildPagination('/admin/users', $filters, $this->users->countForAdmin($filters));
    $users      = $this->users->getAllForAdmin($filters, $pagination['perPage'], $pagination['offset']);

    View::render('admin/users/index', [
      'users'         => $users,
      'filters'       => $filters,
      'filterPresets' => $this->getFilterPresets('users'),
      'pagination'    => $pagination,
    ]);
  }

  public function exportUsers(): void
  {

    $filters = $this->getUserFiltersFromRequest();
    $users   = $this->users->getAllForAdmin($filters, null, null);

    $this->streamCsvDownload(
      'users-' . date('Ymd-His') . '.csv',
      ['nome', 'email', 'perfil', 'status', 'contexto', 'criado_em'],
      $users,
      function (array $user): array {
        return [
          (string) ($user['name']       ?? ''),
          (string) ($user['email']      ?? ''),
          (string) ($user['role']       ?? ''),
          (string) ($user['status']     ?? ''),
          $this->buildAdminUserContextText($user),
          (string) ($user['created_at'] ?? ''),
        ];
      }
    );
  }

  public function exportUsersJson(): void
  {

    $filters = $this->getUserFiltersFromRequest();
    $users   = $this->users->getAllForAdmin($filters, null, null);

    $this->streamJsonDownload(
      'users-' . date('Ymd-His') . '.json',
      [
        'filters'     => $filters,
        'exported_at' => date('c'),
        'items'       => array_map(function (array $user): array {
          return [
            'id'         => (int) ($user['id']         ?? 0),
            'name'       => (string) ($user['name']       ?? ''),
            'email'      => (string) ($user['email']      ?? ''),
            'role'       => (string) ($user['role']       ?? ''),
            'status'     => (string) ($user['status']     ?? ''),
            'context'    => $this->buildAdminUserContextText($user),
            'created_at' => (string) ($user['created_at'] ?? ''),
          ];
        }, $users),
      ]
    );
  }

  public function showUser(string $id): void
  {

    $userId = (int) $id;
    $user   = $this->users->findForAdmin($userId);

    global $session;
    if (!$user) {
      $session->flash('error', 'Usuário não encontrado.');
      View::redirect('/admin/users');
    }

    $teacherTurmas    = [];
    $teacherExercises = [];
    $studentTurmas    = [];
    $studentAttempts  = [];

    if (($user['role'] ?? '') === 'teacher') {
      $teacherTurmas    = $this->users->getTeacherTurmasForAdmin($userId);
      $teacherExercises = $this->users->getTeacherExercisesForAdmin($userId);
    }

    if (($user['role'] ?? '') === 'student') {
      $studentTurmas   = $this->users->getStudentTurmasForAdmin($userId);
      $studentAttempts = $this->users->getStudentAttemptsForAdmin($userId);
    }

    View::render('admin/users/show', [
      'user'             => $user,
      'teacherTurmas'    => $teacherTurmas,
      'teacherExercises' => $teacherExercises,
      'studentTurmas'    => $studentTurmas,
      'studentAttempts'  => $studentAttempts,
      'auditLogs'        => $this->auditLogs->getRecentForUserContext($userId),
    ]);
  }

  public function editUser(string $id): void
  {

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

  public function updateUser(string $id): void
  {
    Request::validateCsrf();

    $userId = (int) $id;
    $user   = $this->users->find($userId);

    global $session;
    if (!$user) {
      $session->flash('error', 'Usuário não encontrado.');
      View::redirect('/admin/users');
    }

    $name   = Request::str('name');
    $email  = Request::email('email');
    $role   = Request::str('role');
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

    $currentRole = (string) ($user['role'] ?? '');
    if ($currentRole !== $role) {
      if ($currentRole === 'teacher' && $role !== 'teacher'
          && $this->users->hasTeacherDependencies($userId)) {
        $errors[] = 'Não é possível alterar o perfil: este professor possui turmas ou exercícios vinculados.';
      }

      if ($currentRole === 'student' && $role !== 'student'
          && $this->users->hasStudentDependencies($userId)) {
        $errors[] = 'Não é possível alterar o perfil: este aluno possui matrículas ou tentativas registradas.';
      }
    }

    if ($errors) {
      View::render('admin/users/edit', [
        'user'   => array_merge($user, [
          'name'   => $name,
          'email'  => $email,
          'role'   => $role,
          'status' => $status,
        ]),
        'errors' => $errors,
      ]);
      return;
    }

    $this->users->updateAdminManagedProfile($userId, $name, $email, $role, $status);
    AuditService::record('admin.user.profile_update', 'user', $userId, [
      'before' => [
        'name'   => $user['name']   ?? null,
        'email'  => $user['email']  ?? null,
        'role'   => $user['role']   ?? null,
        'status' => $user['status'] ?? null,
      ],
      'after' => [
        'name'   => $name,
        'email'  => $email,
        'role'   => $role,
        'status' => $status,
      ],
    ]);

    $session->flash('success', 'Perfil do usuário atualizado com sucesso.');
    View::redirect('/admin/users');
  }

  public function updateUserStatus(string $id): void
  {
    Request::validateCsrf();

    $userId       = (int) $id;
    $targetStatus = Request::str('status');
    $user         = $this->users->find($userId);

    global $session;

    if (!$user) {
      $session->flash('error', 'Usuário não encontrado.');
      View::redirect('/admin/users');
    }

    if (!in_array($targetStatus, ['active', 'inactive'], true)) {
      $session->flash('error', 'Status solicitado é inválido.');
      View::redirect('/admin/users');
    }

    $blockReason = $this->getUserStatusTransitionBlockReason($user, $targetStatus, $this->users->countActiveAdmins());
    if ($blockReason !== null) {
      $session->flash('error', $blockReason);
      View::redirect('/admin/users');
    }

    $this->users->updateStatus($userId, $targetStatus);
    $this->recordUserStatusAudit($user, $targetStatus);

    $session->flash('success', $targetStatus === 'active'
      ? 'Usuário ativado com sucesso.'
      : 'Usuário inativado com sucesso.');
    View::redirect('/admin/users');
  }

  public function activateUsersBatch(): void
  {
    Request::validateCsrf();

    $selectedUserIds = $this->extractSelectedIdsFromRequest('user_ids');
    $redirectPath    = $this->buildBatchReturnPath('/admin/users');

    global $session;

    if ($selectedUserIds === []) {
      $session->flash('error', 'Selecione pelo menos um usuário para ativar.');
      View::redirect($redirectPath);
    }

    $selectedUsers    = array_values(array_filter(array_map(fn(int $id): array|false => $this->users->find($id), $selectedUserIds)));
    $activatableUsers = array_values(array_filter($selectedUsers, static function (array $user): bool {
      return in_array((string) ($user['status'] ?? ''), ['inactive', 'pending'], true);
    }));

    if ($activatableUsers === []) {
      $session->flash('error', 'Nenhum usuário pendente ou inativo válido foi selecionado.');
      View::redirect($redirectPath);
    }

    foreach ($activatableUsers as $user) {
      $this->users->updateStatus((int) ($user['id'] ?? 0), 'active');
      $this->recordUserStatusAudit($user, 'active', 'batch_activate');
    }

    $ignoredCount = count($selectedUserIds) - count($activatableUsers);
    $message      = count($activatableUsers) . ' usuário(s) ativado(s) com sucesso.';
    if ($ignoredCount > 0) {
      $message .= ' ' . $ignoredCount . ' item(ns) foram ignorado(s).';
    }

    $session->flash('success', $message);
    View::redirect($redirectPath);
  }

  public function deactivateUsersBatch(): void
  {
    Request::validateCsrf();

    $selectedUserIds = $this->extractSelectedIdsFromRequest('user_ids');
    $redirectPath    = $this->buildBatchReturnPath('/admin/users');

    global $session;

    if ($selectedUserIds === []) {
      $session->flash('error', 'Selecione pelo menos um usuário para inativar.');
      View::redirect($redirectPath);
    }

    $selectedUsers = array_values(array_filter(array_map(fn(int $id): array|false => $this->users->find($id), $selectedUserIds)));
    $activeUsers   = array_values(array_filter($selectedUsers, static function (array $user): bool {
      return (string) ($user['status'] ?? '') === 'active';
    }));

    if ($activeUsers === []) {
      $session->flash('error', 'Nenhum usuário ativo válido foi selecionado.');
      View::redirect($redirectPath);
    }

    $remainingActiveAdmins = $this->users->countActiveAdmins();
    $updatedCount          = 0;
    $blockedCount          = 0;
    $firstBlockReason      = null;

    foreach ($activeUsers as $user) {
      $blockReason = $this->getUserStatusTransitionBlockReason($user, 'inactive', $remainingActiveAdmins);
      if ($blockReason !== null) {
        $blockedCount++;
        $firstBlockReason ??= $blockReason;
        continue;
      }

      $this->users->updateStatus((int) ($user['id'] ?? 0), 'inactive');
      $this->recordUserStatusAudit($user, 'inactive', 'batch_deactivate');
      $updatedCount++;

      if (($user['role'] ?? '') === 'admin') {
        $remainingActiveAdmins--;
      }
    }

    $ignoredCount = count($selectedUserIds) - count($activeUsers);
    if ($updatedCount === 0) {
      $session->flash('error', $firstBlockReason ?? 'Nenhum usuário ativo válido pôde ser inativado.');
      View::redirect($redirectPath);
    }

    $message      = $updatedCount . ' usuário(s) inativado(s) com sucesso.';
    $totalIgnored = $ignoredCount + $blockedCount;
    if ($totalIgnored > 0) {
      $message .= ' ' . $totalIgnored . ' item(ns) foram ignorado(s).';
    }

    $session->flash('success', $message);
    View::redirect($redirectPath);
  }

  public function resetUserPassword(string $id): void
  {
    Request::validateCsrf();

    $userId = (int) $id;
    $user   = $this->users->find($userId);

    global $session;

    if (!$user) {
      $session->flash('error', 'Usuário não encontrado.');
      View::redirect('/admin/users');
    }

    $token    = bin2hex(random_bytes(32));
    $this->users->createPasswordResetToken($userId, $token, 60);
    $resetUrl = \Core\app_url('/password/reset?token=' . urlencode($token));

    AuditService::record('admin.user.password_reset', 'user', $userId, [
      'target_email'         => $user['email'] ?? null,
      'target_role'          => $user['role']  ?? null,
      'expires_in_minutes'   => 60,
      'must_change_password' => true,
    ]);

    $session->flash('success', 'Link de redefinição gerado para ' . ($user['email'] ?? 'usuário') . ' válido por 60 minutos: ' . $resetUrl);
    View::redirect('/admin/users');
  }

  private function getUserStatusTransitionBlockReason(array $user, string $targetStatus, int $remainingActiveAdmins): ?string
  {
    if ($targetStatus !== 'inactive') {
      return null;
    }

    if ((int) ($user['id'] ?? 0) === (int) Auth::id()) {
      return 'Você não pode inativar a própria conta administrativa.';
    }

    if (
      ($user['role']   ?? '') === 'admin'
      && ($user['status'] ?? '') === 'active'
      && $remainingActiveAdmins <= 1
    ) {
      return 'Não é possível inativar o último administrador ativo do sistema.';
    }

    return null;
  }

  private function recordUserStatusAudit(array $user, string $targetStatus, ?string $batchAction = null): void
  {
    $metadata = [
      'target_email'    => $user['email']  ?? null,
      'target_role'     => $user['role']   ?? null,
      'previous_status' => $user['status'] ?? null,
      'new_status'      => $targetStatus,
    ];

    if ($batchAction !== null) {
      $metadata['batch_action'] = $batchAction;
    }

    AuditService::record('admin.user.status_update', 'user', (int) ($user['id'] ?? 0), $metadata);
  }
}
