<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Turma;
use App\Models\TurmaRanking;
use Core\Auth;
use Core\View;

/** Ranking do Aluno: top 5 e a própria posição em cada Turma com ranking ligado. */
class RankingController
{
  /** GET /student/ranking */
  public function student(): void
  {
    $studentId = (int) Auth::id();
    $ranking = new TurmaRanking();
    $boards = [];

    foreach ((new Turma())->getStudentTurmas($studentId) as $turma) {
      if (($turma['enrollment_status'] ?? '') !== 'active' || !(bool) $turma['active']) {
        continue;
      }
      $standings = $ranking->standings($turma);
      $mine = null;
      foreach ($standings as $row) {
        if ($row['student_id'] === $studentId) {
          $mine = $row;
        }
      }
      $champion = $ranking->standings($turma, true)[0] ?? null;

      $boards[] = [
        'turma'    => $turma,
        'top'      => array_slice($standings, 0, TurmaRanking::TOP),
        'mine'     => $mine,
        'total'    => $ranking->activeStudentCount((int) $turma['id']),
        'champion' => $champion,
      ];
    }

    View::render('student/ranking', ['boards' => $boards, 'studentId' => $studentId]);
  }
}
