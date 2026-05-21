<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Exercise;
use App\Models\Question;
use App\Services\AuditService;
use Core\Auth;
use Core\Request;
use Core\View;

class QuestionController
{
  private Question $questions;
  private Exercise $exercises;

  public function __construct()
  {
    $this->questions = new Question();
    $this->exercises = new Exercise();
  }

  public function create(string $exerciseId): void
  {
    Auth::requireTeacher();
    $exercise = $this->getOwnedExercise((int) $exerciseId);
    $this->ensureDraftExercise($exercise);

    View::render('teacher/questions/create', [
      'exercise'   => $exercise,
      'questions'  => $this->questions->findByExercise((int) $exerciseId),
      'nextOrder'  => $this->questions->countByExercise((int) $exerciseId) + 1,
    ]);
  }

  public function store(string $exerciseId): void
  {
    Auth::requireTeacher();
    Request::validateCsrf();
    $exercise = $this->getOwnedExercise((int) $exerciseId);
    $this->ensureDraftExercise($exercise);

    $text         = Request::text('text');
    $hint         = Request::text('expected_answer_hint');
    $maxScore     = min(10.0, max(0.0, Request::float('max_score', 10.0)));
    $orderIndex   = Request::int('order_index', 0);

    $errors = [];
    if (mb_strlen($text) < 5) {
      $errors[] = 'Enunciado da questão muito curto (mínimo 5 caracteres).';
    }
    if (mb_strlen($hint) < 5) {
      $errors[] = 'Gabarito/conceito esperado muito curto (mínimo 5 caracteres).';
    }

    if ($errors) {
      View::render('teacher/questions/create', [
        'exercise'  => $exercise,
        'questions' => $this->questions->findByExercise((int) $exerciseId),
        'errors'    => $errors,
        'old'       => compact('text', 'hint', 'maxScore', 'orderIndex'),
        'nextOrder' => $this->questions->countByExercise((int) $exerciseId) + 1,
      ]);
      return;
    }

    $this->questions->create((int) $exerciseId, $text, $hint, $maxScore, $orderIndex ?: ($this->questions->countByExercise((int) $exerciseId) + 1));
    AuditService::record('teacher.question.create', 'exercise', (int) $exerciseId, [
      'max_score' => $maxScore,
    ]);

    global $session;
    $session->flash('success', 'Questão adicionada com sucesso.');
    View::redirect("/teacher/exercises/{$exerciseId}/questions/create");
  }

  public function destroy(string $id): void
  {
    Auth::requireTeacher();
    Request::validateCsrf();

    Auth::ensure($this->questions->belongsToTeacher((int) $id, Auth::id()));

    $question   = $this->questions->find((int) $id);
    $exerciseId = $question['exercise_id'] ?? 0;
    $exercise = $this->getOwnedExercise((int) $exerciseId);
    $this->ensureDraftExercise($exercise);

    $this->questions->delete((int) $id);
    AuditService::record('teacher.question.delete', 'question', (int) $id, ['exercise_id' => (int) $exerciseId]);
    View::redirect("/teacher/exercises/{$exerciseId}/questions/create");
  }

  private function getOwnedExercise(int $id): array
  {
    $ex = $this->exercises->getWithTurma($id);
    Auth::ensure($ex && (int) $ex['teacher_id'] === Auth::id());
    return $ex;
  }

  private function ensureDraftExercise(array $exercise): void
  {
    if ($this->exercises->canEdit($exercise)) {
      return;
    }

    global $session;
    $session->flash('error', 'Este exercício já foi concluído ou publicado e não aceita mais alterações nas questões.');
    View::redirect('/teacher/exercises/' . $exercise['id']);
  }
}
