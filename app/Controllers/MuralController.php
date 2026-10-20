<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\TurmaPost;
use Core\Auth;
use Core\View;

/** Mural do Aluno: recados vigentes de todas as suas Turmas. */
class MuralController
{
  /** GET /student/mural */
  public function student(): void
  {
    View::render('student/mural', [
      'posts' => (new TurmaPost())->forStudent((int) Auth::id()),
    ]);
  }
}
