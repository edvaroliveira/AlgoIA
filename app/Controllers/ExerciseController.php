<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Exercise;
use App\Models\Question;
use App\Models\Turma;
use App\Models\Attempt;
use App\Models\Answer;
use App\Services\AuditService;
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
    $description = Request::text('description') ?: null;
    $opensAt     = Request::str('opens_at');
    $closesAt    = Request::str('closes_at');
    $maxAttempts = Request::int('max_attempts', 1);

    $errors = $this->validateExercise($title, $opensAt, $closesAt, $maxAttempts);

    if ($errors) {
      View::render('teacher/exercises/create', [
        'errors' => $errors,
        'turmas' => $this->turmas->findByTeacher(Auth::id()),
        'old'    => compact('title', 'description', 'opensAt', 'closesAt', 'maxAttempts'),
      ]);
      return;
    }

    $id = $this->exercises->createDraft(Auth::id(), $title, $description, $opensAt, $closesAt, $maxAttempts);
    AuditService::record('teacher.exercise.create', 'exercise', $id, [
      'title' => $title,
      'status' => 'draft',
    ]);

    global $session;
    $session->flash('success', 'Exercício criado como pendente de finalização. Cadastre as questões antes de ativar para turmas.');
    View::redirect("/teacher/exercises/{$id}/questions/create");
  }

  public function show(string $id): void
  {
    Auth::requireTeacher();
    $exercise = $this->getOwnedExercise((int) $id);

    View::render('teacher/exercises/show', [
      'exercise'  => $exercise,
      'questions' => $this->questions->findByExercise((int) $id),
      'results'   => ($exercise['status'] ?? 'active') === 'active' ? $this->exercises->getResultsForTeacher((int) $id) : [],
      'maxScore'  => $this->questions->getTotalMaxScore((int) $id),
      'turmas'    => $this->turmas->findByTeacher(Auth::id()),
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
    $description = Request::text('description') ?: null;
    $opensAt     = Request::str('opens_at');
    $closesAt    = Request::str('closes_at');
    $maxAttempts = Request::int('max_attempts', 1);

    $errors = $this->validateExercise($title, $opensAt, $closesAt, $maxAttempts);

    if ($errors) {
      $exercise = $this->exercises->getWithTurma((int) $id) ?: [];
      $exercise['title'] = $title;
      $exercise['description'] = $description;
      $exercise['opens_at'] = $opensAt;
      $exercise['closes_at'] = $closesAt;
      $exercise['max_attempts'] = $maxAttempts;

      View::render('teacher/exercises/edit', [
        'errors'   => $errors,
        'exercise' => $exercise,
        'turmas'   => $this->turmas->findByTeacher(Auth::id()),
      ]);
      return;
    }

    $this->exercises->updateDraft((int) $id, $title, $description, $opensAt, $closesAt, $maxAttempts);
    AuditService::record('teacher.exercise.update', 'exercise', (int) $id, [
      'title' => $title,
      'status' => 'metadata-updated',
    ]);
    View::redirect("/teacher/exercises/{$id}");
  }

  public function activate(string $id): void
  {
    Auth::requireTeacher();
    Request::validateCsrf();

    $exercise = $this->getOwnedExercise((int) $id);
    $questions = $this->questions->countByExercise((int) $id);
    $turmaIds = array_values(array_filter(array_map('intval', (array) ($_POST['turma_ids'] ?? []))));

    $errors = $this->validateActivation($questions, $turmaIds);

    if ($errors) {
      View::render('teacher/exercises/show', [
        'exercise' => $exercise,
        'questions' => $this->questions->findByExercise((int) $id),
        'results' => ($exercise['status'] ?? 'active') === 'active' ? $this->exercises->getResultsForTeacher((int) $id) : [],
        'maxScore' => $this->questions->getTotalMaxScore((int) $id),
        'turmas' => $this->turmas->findByTeacher(Auth::id()),
        'activationErrors' => $errors,
        'activationTurmaIds' => $turmaIds,
      ]);
      return;
    }

    $this->exercises->activate((int) $id, $turmaIds);
    AuditService::record('teacher.exercise.activate', 'exercise', (int) $id, [
      'turma_ids' => $turmaIds,
      'question_count' => $questions,
    ]);

    global $session;
    $session->flash('success', 'Exercício finalizado e ativado para as turmas selecionadas.');
    View::redirect("/teacher/exercises/{$id}");
  }

  public function destroy(string $id): void
  {
    Auth::requireTeacher();
    Request::validateCsrf();
    $exercise = $this->getOwnedExercise((int) $id);

    $this->exercises->delete((int) $id);
    AuditService::record('teacher.exercise.delete', 'exercise', (int) $id, [
      'title' => $exercise['title'] ?? null,
    ]);
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
    $maxAttempts  = (int) $exercise['max_attempts'];
    $canAttempt   = $maxAttempts === 0 || $attCount < $maxAttempts;
    $isOpen       = $this->exercises->isOpen($exercise);
    $attemptId    = (int) Request::get('attempt', 0);
    $attemptQuestions = [];
    $savedAnswers = [];

    if ($attemptId > 0 && $inProgress && (int) $inProgress['id'] === $attemptId) {
      $answerModel = new Answer();
      $attemptQuestions = $this->questions->findByExercise((int) $exercise['id']);
      $savedAnswers = $answerModel->findMapByAttempt($attemptId);
    }

    View::render('student/exercises/show', compact(
      'exercise',
      'attempts',
      'attCount',
      'inProgress',
      'bestScore',
      'canAttempt',
      'isOpen',
      'attemptId',
      'attemptQuestions',
      'savedAnswers'
    ));
  }

  // ── Private helpers ───────────────────────────────────────────────────────

  private function getOwnedExercise(int $id): array
  {
    $ex = $this->exercises->getWithTurma($id);
    Auth::ensure($ex && (int) $ex['teacher_id'] === Auth::id());
    return $ex;
  }

  private function getStudentExercise(int $id, int $studentId): array
  {
    $ex = $this->exercises->getWithTurma($id);
    if (!$ex) {
      Auth::deny('Exercício não encontrado.', 404);
    }

    Auth::ensure($this->exercises->studentHasAccess($id, $studentId), 'Você não tem acesso a este exercício.');

    return $ex;
  }

  private function validateExercise(
    string $title,
    string $opensAt,
    string $closesAt,
    int    $maxAttempts
  ): array {
    $errors = [];

    if (mb_strlen($title) < 3) {
      $errors[] = 'Título deve ter pelo menos 3 caracteres.';
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

  private function validateActivation(int $questionCount, array $turmaIds): array
  {
    $errors = [];

    if ($questionCount < 1) {
      $errors[] = 'Adicione pelo menos uma questão antes de ativar o exercício.';
    }

    if (empty($turmaIds)) {
      $errors[] = 'Selecione pelo menos uma turma para ativação.';
    }

    foreach ($turmaIds as $turmaId) {
      if (!$this->turmas->belongsToTeacher($turmaId, Auth::id())) {
        $errors[] = 'Há turma inválida na ativação.';
        break;
      }
    }

    return $errors;
  }
}
