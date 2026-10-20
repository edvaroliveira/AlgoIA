<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ThemeProgress;
use Core\Auth;
use Core\View;

/** Progresso do Aluno por Tema (Prática + Tentativas de Questões vindas do banco). */
class ProgressController
{
  /** GET /student/progresso */
  public function student(): void
  {
    $achievements = [];
    try {
      $achievements = (new \App\Services\AchievementService())->sync((int) Auth::id());
    } catch (\Throwable $e) {
      error_log('achievements failed: ' . $e->getMessage());
    }

    View::render('student/progress', [
      'achievements' => $achievements,
      'themes'    => (new ThemeProgress())->forStudent((int) Auth::id()),
      'evolution' => (new \App\Models\AttemptEvolution())->forStudent((int) Auth::id()),
    ]);
  }
}
