<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Notification;
use App\Models\Turma;
use App\Models\User;
use App\Services\AuditService;
use Core\Auth;
use Core\Request;
use Core\View;

class StudentController
{
  public function index(): void
  {
    $users = new User();
    View::render('teacher/students/index', [
      'students' => $users->getStudentsByTeacher((int) Auth::id()),
    ]);
  }

  /** POST /teacher/students/{id}/activate — aprova as Matrículas pendentes nas Turmas ativas do Docente. */
  public function activate(string $id): void
  {
    Request::validateCsrf();

    $studentId = (int) $id;
    $teacherId = (int) Auth::id();
    $returnPath = Request::post('return', '') === 'dashboard' ? '/teacher/dashboard' : '/teacher/students';

    global $session;
    if (!(new User())->belongsToTeacher($studentId, $teacherId)) {
      AuditService::record('teacher.student.approve_denied', 'student', $studentId, ['reason' => 'not_teacher_student']);
      $session->flash('error', 'Aluno não encontrado nas suas turmas.');
      View::redirect($returnPath);
    }

    $approved = (new Turma())->approveStudentForTeacher($studentId, $teacherId);
    if ($approved === []) {
      $session->flash('error', 'Este aluno não tem matrícula pendente em turma ativa sua.');
      View::redirect($returnPath);
    }

    $notifications = new Notification();
    foreach ($approved as $turma) {
      $notifications->notifySafely(
        $studentId,
        Notification::TYPE_ENROLLMENT_APPROVED,
        'Matrícula aprovada na turma ' . $turma['name'],
        '/student/dashboard',
        null,
        'enrollment:' . $turma['id']
      );
    }
    AuditService::record('teacher.student.approve', 'student', $studentId, [
      'turma_ids' => array_column($approved, 'id'),
      'source' => 'students_list',
    ]);

    $session->flash('success', 'Aluno ativado em: ' . implode(', ', array_column($approved, 'name')) . '.');
    View::redirect($returnPath);
  }

  public function destroy(string $id): void
  {
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
