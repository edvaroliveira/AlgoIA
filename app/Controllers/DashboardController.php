<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Exercise;
use App\Models\Turma;
use App\Models\Attempt;
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

    $myTurmas    = $turmas->findByTeacher($teacherId);
    $myExercises = $exercises->findByTeacher($teacherId);

    $pendingTotal = array_sum(array_column($myTurmas, 'pending_count'));
    $activeTotal  = array_sum(array_column($myTurmas, 'active_count'));

    $now      = time();
    $openExs  = array_filter($myExercises, fn($e) => strtotime($e['opens_at']) <= $now && strtotime($e['closes_at']) >= $now);
    $recentExs = array_slice($myExercises, 0, 5);

    View::render('teacher/dashboard', [
      'turmas'        => $myTurmas,
      'exercises'     => $recentExs,
      'pendingTotal'  => $pendingTotal,
      'activeTotal'   => $activeTotal,
      'openCount'     => count($openExs),
      'totalExs'      => count($myExercises),
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
      $ex['best_score']     = $attModel->getBestScore($studentId, (int) $ex['id']);
      $ex['attempt_count']  = $attModel->countSubmitted($studentId, (int) $ex['id']);
    }
    unset($ex);

    View::render('student/dashboard', [
      'available' => $available,
      'all'       => $all,
      'turmas'    => $myTurmas,
    ]);
  }
}
