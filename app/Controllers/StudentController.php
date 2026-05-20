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
      $deleted = $users->deleteStudentWithRelations((int) $id, (int) Auth::id());
    } catch (\Throwable $e) {
      error_log('delete student failed: ' . $e->getMessage());
      $deleted = false;
    }

    global $session;
    if ($deleted) {
      AuditService::record('teacher.student.delete', 'student', (int) $id, [
        'student_name' => $student['name'] ?? null,
        'student_email' => $student['email'] ?? null,
      ]);
      $session->flash('success', 'Aluno removido com todos os registros relacionados.');
    } else {
      $session->flash('error', 'Não foi possível excluir o aluno solicitado.');
    }

    View::redirect('/teacher/students');
  }
}
