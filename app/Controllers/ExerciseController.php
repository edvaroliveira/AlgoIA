<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Exercise;
use App\Models\Question;
use App\Models\Turma;
use App\Models\Attempt;
use Core\Auth;
use Core\Request;
use Core\View;

class ExerciseController
{
  private Exercise $exercises;
  private Question $questions;
  private Turma    $turmas;

  public function __construct()
  {
    $this->exercises = new Exercise();
    $this->questions = new Question();
    $this->turmas    = new Turma();
  }

  // ── Teacher ──────────────────────────────────────────────────────────────

  public function index(): void
  {
    Auth::requireTeacher();
    View::render('teacher/exercises/index', [
      'exercises' => $this->exercises->findByTeacher(Auth::id()),
    ]);
  }

  public function create(): void
  {
    Auth::requireTeacher();
    View::render('teacher/exercises/create', [
      'turmas' => $this->turmas->findByTeacher(Auth::id()),
    ]);
  }

  public function store(): void
  {
    Auth::requireTeacher();
    Request::validateCsrf();

    $title       = Request::str('title');
    $description = Request::str('description') ?: null;
    $turmaId     = Request::int('turma_id');
    $opensAt     = Request::str('opens_at');
    $closesAt    = Request::str('closes_at');
    $maxAttempts = Request::int('max_attempts', 1);

    $errors = $this->validateExercise($title, $turmaId, $opensAt, $closesAt, $maxAttempts);

    if ($errors) {
      View::render('teacher/exercises/create', [
        'errors' => $errors,
        'turmas' => $this->turmas->findByTeacher(Auth::id()),
        'old'    => compact('title', 'description', 'turmaId', 'opensAt', 'closesAt', 'maxAttempts'),
      ]);
      return;
    }

    $id = $this->exercises->create(Auth::id(), $turmaId, $title, $description, $opensAt, $closesAt, $maxAttempts);
    View::redirect("/teacher/exercises/{$id}");
  }

  public function show(string $id): void
  {
    Auth::requireTeacher();
    $exercise = $this->getOwnedExercise((int) $id);

    View::render('teacher/exercises/show', [
      'exercise'  => $exercise,
      'questions' => $this->questions->findByExercise((int) $id),
      'results'   => $this->exercises->getResultsForTeacher((int) $id),
      'maxScore'  => $this->questions->getTotalMaxScore((int) $id),
    ]);
  }

  public function edit(string $id): void
  {
    Auth::requireTeacher();
    $exercise = $this->getOwnedExercise((int) $id);

    View::render('teacher/exercises/edit', [
      'exercise' => $exercise,
      'turmas'   => $this->turmas->findByTeacher(Auth::id()),
    ]);
  }

  public function update(string $id): void
  {
    Auth::requireTeacher();
    Request::validateCsrf();
    $this->getOwnedExercise((int) $id);

    $title       = Request::str('title');
    $description = Request::str('description') ?: null;
    $turmaId     = Request::int('turma_id');
    $opensAt     = Request::str('opens_at');
    $closesAt    = Request::str('closes_at');
    $maxAttempts = Request::int('max_attempts', 1);

    $errors = $this->validateExercise($title, $turmaId, $opensAt, $closesAt, $maxAttempts);

    if ($errors) {
      View::render('teacher/exercises/edit', [
        'errors'   => $errors,
        'exercise' => $this->exercises->getWithTurma((int) $id),
        'turmas'   => $this->turmas->findByTeacher(Auth::id()),
      ]);
      return;
    }

    $this->exercises->update((int) $id, $title, $description, $opensAt, $closesAt, $maxAttempts);
    View::redirect("/teacher/exercises/{$id}");
  }

  public function destroy(string $id): void
  {
    Auth::requireTeacher();
    Request::validateCsrf();
    $this->getOwnedExercise((int) $id);

    $this->exercises->delete((int) $id);
    View::redirect('/teacher/exercises');
  }

  // ── Student ───────────────────────────────────────────────────────────────

  public function studentIndex(): void
  {
    Auth::requireStudent();
    $studentId = Auth::id();
    $attModel  = new Attempt();

    $exercises = $this->exercises->findAllForStudent($studentId);
    foreach ($exercises as &$ex) {
      $ex['best_score']    = $attModel->getBestScore($studentId, (int) $ex['id']);
      $ex['attempt_count'] = $attModel->countSubmitted($studentId, (int) $ex['id']);
      $ex['is_open']       = $this->exercises->isOpen($ex);
      $ex['is_closed']     = $this->exercises->isClosed($ex);
    }
    unset($ex);

    View::render('student/exercises/index', ['exercises' => $exercises]);
  }

  public function studentShow(string $id): void
  {
    Auth::requireStudent();
    $studentId = Auth::id();
    $exercise  = $this->getStudentExercise((int) $id, $studentId);
    $attModel  = new Attempt();

    $attempts     = $attModel->findByStudentAndExercise($studentId, (int) $id);
    $attCount     = $attModel->countSubmitted($studentId, (int) $id);
    $inProgress   = $attModel->getInProgress($studentId, (int) $id);
    $bestScore    = $attModel->getBestScore($studentId, (int) $id);
    $canAttempt   = $exercise['max_attempts'] === 0 || $attCount < $exercise['max_attempts'];
    $isOpen       = $this->exercises->isOpen($exercise);

    View::render('student/exercises/show', compact(
      'exercise',
      'attempts',
      'attCount',
      'inProgress',
      'bestScore',
      'canAttempt',
      'isOpen'
    ));
  }

  // ── Private helpers ───────────────────────────────────────────────────────

  private function getOwnedExercise(int $id): array
  {
    $ex = $this->exercises->getWithTurma($id);
    if (!$ex || (int) $ex['teacher_id'] !== Auth::id()) {
      http_response_code(403);
      exit('Acesso negado.');
    }
    return $ex;
  }

  private function getStudentExercise(int $id, int $studentId): array
  {
    $ex = $this->exercises->getWithTurma($id);
    if (!$ex) {
      http_response_code(404);
      exit('Exercício não encontrado.');
    }

    $turmaModel = new Turma();
    if (!$turmaModel->isStudentActive($studentId, (int) $ex['turma_id'])) {
      http_response_code(403);
      exit('Você não tem acesso a este exercício.');
    }

    return $ex;
  }

  private function validateExercise(
    string $title,
    int    $turmaId,
    string $opensAt,
    string $closesAt,
    int    $maxAttempts
  ): array {
    $errors = [];

    if (mb_strlen($title) < 3) {
      $errors[] = 'Título deve ter pelo menos 3 caracteres.';
    }
    if ($turmaId <= 0 || !$this->turmas->belongsToTeacher($turmaId, Auth::id())) {
      $errors[] = 'Turma inválida.';
    }
    if (!strtotime($opensAt)) {
      $errors[] = 'Data de abertura inválida.';
    }
    if (!strtotime($closesAt)) {
      $errors[] = 'Data de fechamento inválida.';
    }
    if ($opensAt && $closesAt && strtotime($opensAt) >= strtotime($closesAt)) {
      $errors[] = 'A data de fechamento deve ser posterior à data de abertura.';
    }
    if ($maxAttempts < 0) {
      $errors[] = 'Número de tentativas inválido (use 0 para ilimitado).';
    }

    return $errors;
  }
}
