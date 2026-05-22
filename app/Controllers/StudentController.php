<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Services\AuditService;
use Core\Auth;
use Core\Request;
use Core\View;

class StudentController
{
  public function index(): void
  {
    Auth::requireTeacher();
    $users = new User();
    View::render('teacher/students/index', [
      'students' => $users->getStudentsByTeacher((int) Auth::id()),
    ]);
  }

  public function destroy(string $id): void
  {
    Auth::requireTeacher();
    Request::validateCsrf();

    $users = new User();
    $student = $users->find((int) $id);

    try {
      $detachResult = $users->detachStudentFromTeacherTurmas((int) $id, (int) Auth::id());
    } catch (\Throwable $e) {
      error_log('detach student failed: ' . $e->getMessage());
      $detachResult = ['removed_count' => 0, 'turmas' => []];
    }

    global $session;
    if ((int) ($detachResult['removed_count'] ?? 0) > 0) {
      AuditService::record('teacher.student.detach', 'student', (int) $id, [
        'student_name' => $student['name'] ?? null,
        'student_email' => $student['email'] ?? null,
        'turmas' => $detachResult['turmas'] ?? [],
      ]);
      $session->flash('success', 'Aluno desvinculado das suas turmas. O cadastro e o histórico foram preservados.');
    } else {
      $session->flash('error', 'Não foi possível desvincular o aluno solicitado.');
    }

    View::redirect('/teacher/students');
  }
}
