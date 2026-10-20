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
    View::render('student/progress', [
      'themes'    => (new ThemeProgress())->forStudent((int) Auth::id()),
      'evolution' => (new \App\Models\AttemptEvolution())->forStudent((int) Auth::id()),
    ]);
  }
}
