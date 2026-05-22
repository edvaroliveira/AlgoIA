<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use Core\Auth;
use Core\View;

class AdminController
{
  public function dashboard(): void
  {
    Auth::requireAdmin();

    $users = (new User())->getAllForAdmin();

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

    $users = (new User())->getAllForAdmin();

    View::render('admin/users/index', [
      'users' => $users,
    ]);
  }
}
