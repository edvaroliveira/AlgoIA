<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Exercise;
use App\Models\Turma;
use App\Models\User;
use App\Services\AuditService;
use Core\Auth;
use Core\Request;
use Core\View;

class AdminController
{
  private User $users;
  private Turma $turmas;
  private Exercise $exercises;

  public function __construct()
  {
    $this->users = new User();
    $this->turmas = new Turma();
    $this->exercises = new Exercise();
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

    $users = $this->users->getAllForAdmin();

    View::render('admin/users/index', [
      'users' => $users,
    ]);
  }

  public function turmas(): void
  {
    Auth::requireAdmin();

    $turmas = $this->turmas->getAllForAdmin();

    View::render('admin/turmas/index', [
      'turmas' => $turmas,
    ]);
  }

  public function exercises(): void
  {
    Auth::requireAdmin();

    $exercises = $this->exercises->getAllForAdmin();

    View::render('admin/exercises/index', [
      'exercises' => $exercises,
    ]);
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
}
