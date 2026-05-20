<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use Core\Auth;
use Core\View;

class StudentController
{
  public function index(): void
  {
    Auth::requireTeacher();
    $users = new User();
    View::render('teacher/students/index', [
      'students' => $users->getAllStudents(),
    ]);
  }
}
