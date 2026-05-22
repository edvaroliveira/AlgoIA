<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Exercise;
use App\Models\Turma;
use App\Models\User;
use Core\Auth;
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
}
