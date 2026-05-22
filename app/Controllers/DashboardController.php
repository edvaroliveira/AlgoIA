<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Exercise;
use App\Models\Turma;
use App\Models\Attempt;
use App\Models\User;
use Core\Auth;
use Core\View;

class DashboardController
{
  public function teacher(): void
  {
    Auth::requireTeacher();

    $teacherId = Auth::id();
    $exercises = new Exercise();
    $turmas    = new Turma();
    $users     = new User();
    $attempts  = new Attempt();

    $myTurmas    = $turmas->findByTeacher($teacherId);
    $myExercises = $exercises->findByTeacher($teacherId);

    $pendingTotal = array_sum(array_column($myTurmas, 'pending_count'));
    $activeTotal  = array_sum(array_column($myTurmas, 'active_count'));

    $openExs  = array_filter($myExercises, fn($e) => $exercises->isOpen($e));
    $recentExs = array_slice($myExercises, 0, 5);
    $recentStudents = $users->getRecentStudentsByTeacher((int) $teacherId, 6);
    $pendingGradingAttempts = $attempts->getPendingGradingForTeacher((int) $teacherId, 6);

    View::render('teacher/dashboard', [
      'turmas'        => $myTurmas,
      'exercises'     => $recentExs,
      'recentStudents' => $recentStudents,
      'pendingTotal'  => $pendingTotal,
      'activeTotal'   => $activeTotal,
      'openCount'     => count($openExs),
      'totalExs'      => count($myExercises),
      'pendingGradingCount' => $attempts->countPendingGradingForTeacher((int) $teacherId),
      'pendingGradingAttempts' => $pendingGradingAttempts,
    ]);
  }

  public function student(): void
  {
    Auth::requireStudent();

    $studentId = Auth::id();
    $exModel   = new Exercise();
    $attModel  = new Attempt();
    $turmas    = new Turma();

    $available = $exModel->findAvailableForStudent($studentId);
    $all       = $exModel->findAllForStudent($studentId);
    $myTurmas  = $turmas->getStudentTurmas($studentId);

    // Attach best score to each exercise
    foreach ($all as &$ex) {
      $publication = $exModel->findCurrentPublicationForStudent((int) $ex['id'], $studentId);
      $ex = $exModel->applyPublicationContext($ex, $publication);
      $turmaId = !empty($ex['turma_id']) ? (int) $ex['turma_id'] : null;
      $ex['best_score']     = $attModel->getBestScore($studentId, (int) $ex['id'], $turmaId);
      $ex['attempt_count']  = $attModel->countUsedAttempts($studentId, (int) $ex['id'], $turmaId);
    }
    unset($ex);

    foreach ($available as &$ex) {
      $publication = $exModel->findOpenPublicationForStudent((int) $ex['id'], $studentId);
      $ex = $exModel->applyPublicationContext($ex, $publication);
    }
    unset($ex);

    View::render('student/dashboard', [
      'available' => $available,
      'all'       => $all,
      'turmas'    => $myTurmas,
    ]);
  }
}
