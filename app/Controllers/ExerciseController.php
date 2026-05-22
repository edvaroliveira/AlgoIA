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
    View::render('teacher/exercises/create');
  }

  public function store(): void
  {
    Auth::requireTeacher();
    Request::validateCsrf();

    $title       = Request::str('title');
    $description = Request::text('description') ?: null;

    $errors = $this->validateExercise($title);

    if ($errors) {
      View::render('teacher/exercises/create', [
        'errors' => $errors,
        'old'    => compact('title', 'description'),
      ]);
      return;
    }

    $id = $this->exercises->createDraft(Auth::id(), $title, $description);
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
      'results'   => $this->exercises->isActive($exercise) ? $this->exercises->getResultsForTeacher((int) $id) : [],
      'maxScore'  => $this->questions->getTotalMaxScore((int) $id),
      'turmas'    => $this->turmas->findByTeacher(Auth::id()),
      'publicationDefaults' => $this->publicationDefaults(),
    ]);
  }

  public function edit(string $id): void
  {
    Auth::requireTeacher();
    $exercise = $this->getOwnedExercise((int) $id);
    $this->ensureDraftExercise($exercise);

    View::render('teacher/exercises/edit', ['exercise' => $exercise]);
  }

  public function update(string $id): void
  {
    Auth::requireTeacher();
    Request::validateCsrf();
    $exercise = $this->getOwnedExercise((int) $id);
    $this->ensureDraftExercise($exercise);

    $title       = Request::str('title');
    $description = Request::text('description') ?: null;

    $errors = $this->validateExercise($title);

    if ($errors) {
      $exercise = $this->exercises->getWithTurma((int) $id) ?: [];
      $exercise['title'] = $title;
      $exercise['description'] = $description;

      View::render('teacher/exercises/edit', [
        'errors'   => $errors,
        'exercise' => $exercise,
      ]);
      return;
    }

    $this->exercises->updateDraft((int) $id, $title, $description);
    AuditService::record('teacher.exercise.update', 'exercise', (int) $id, [
      'title' => $title,
      'status' => 'metadata-updated',
    ]);
    View::redirect("/teacher/exercises/{$id}");
  }

  public function complete(string $id): void
  {
    Auth::requireTeacher();
    Request::validateCsrf();

    $exercise = $this->getOwnedExercise((int) $id);
    $this->ensureDraftExercise($exercise);

    $questionCount = $this->questions->countByExercise((int) $id);
    if ($questionCount < 1) {
      global $session;
      $session->flash('error', 'Adicione pelo menos uma questão antes de concluir o exercício.');
      View::redirect("/teacher/exercises/{$id}");
    }

    $this->exercises->markReady((int) $id);
    AuditService::record('teacher.exercise.complete', 'exercise', (int) $id, [
      'question_count' => $questionCount,
      'status' => 'ready',
    ]);

    global $session;
    $session->flash('success', 'Cadastro do exercício concluído. Agora vincule a atividade às turmas desejadas.');
    View::redirect("/teacher/exercises/{$id}");
  }

  public function activate(string $id): void
  {
    Auth::requireTeacher();
    Request::validateCsrf();

    $exercise = $this->getOwnedExercise((int) $id);
    $this->ensureReadyExercise($exercise);
    $questions = $this->questions->countByExercise((int) $id);
    $publicationConfigs = $this->extractPublicationConfigs();

    $errors = $this->validateActivation($questions, $publicationConfigs);

    if ($errors) {
      View::render('teacher/exercises/show', [
        'exercise' => $exercise,
        'questions' => $this->questions->findByExercise((int) $id),
        'results' => $this->exercises->isActive($exercise) ? $this->exercises->getResultsForTeacher((int) $id) : [],
        'maxScore' => $this->questions->getTotalMaxScore((int) $id),
        'turmas' => $this->turmas->findByTeacher(Auth::id()),
        'activationErrors' => $errors,
        'activationTurmaIds' => array_keys($publicationConfigs),
        'publicationDefaults' => $this->publicationDefaults(),
        'publicationInput' => $publicationConfigs,
      ]);
      return;
    }

    $this->exercises->activate((int) $id, $publicationConfigs);
    AuditService::record('teacher.exercise.activate', 'exercise', (int) $id, [
      'turma_ids' => array_keys($publicationConfigs),
      'publication' => $publicationConfigs,
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
    $ex = $this->exercises->findForStudent($id, $studentId);
    if (!$ex) {
      Auth::deny('Exercício não encontrado.', 404);
    }

    Auth::ensure($this->exercises->studentHasAccess($id, $studentId), 'Você não tem acesso a este exercício.');

    return $ex;
  }

  private function validateExercise(
    string $title
  ): array {
    $errors = [];

    if (mb_strlen($title) < 3) {
      $errors[] = 'Título deve ter pelo menos 3 caracteres.';
    }

    return $errors;
  }

  private function validateActivation(int $questionCount, array $publicationConfigs): array
  {
    $errors = [];

    if ($questionCount < 1) {
      $errors[] = 'Adicione pelo menos uma questão antes de ativar o exercício.';
    }

    if (empty($publicationConfigs)) {
      $errors[] = 'Selecione pelo menos uma turma para publicação.';
    }

    foreach ($publicationConfigs as $turmaId => $config) {
      if (!$this->turmas->belongsToTeacher($turmaId, Auth::id())) {
        $errors[] = 'Há turma inválida na publicação.';
        break;
      }

      if (!strtotime($config['opens_at'] ?? '')) {
        $errors[] = 'Data de abertura inválida em uma das turmas selecionadas.';
      }

      if (!strtotime($config['closes_at'] ?? '')) {
        $errors[] = 'Data de fechamento inválida em uma das turmas selecionadas.';
      }

      if (
        !empty($config['opens_at'])
        && !empty($config['closes_at'])
        && strtotime($config['opens_at']) >= strtotime($config['closes_at'])
      ) {
        $errors[] = 'A data de fechamento deve ser posterior à data de abertura em todas as turmas selecionadas.';
      }

      if ((int) ($config['max_attempts'] ?? -1) < 0) {
        $errors[] = 'Número de tentativas inválido em uma das turmas selecionadas.';
      }
    }

    return array_values(array_unique($errors));
  }

  private function extractPublicationConfigs(): array
  {
    $raw = (array) ($_POST['publication'] ?? []);
    $configs = [];

    foreach ($raw as $turmaId => $config) {
      $id = (int) $turmaId;
      if ($id <= 0 || !is_array($config) || empty($config['enabled'])) {
        continue;
      }

      $configs[$id] = [
        'opens_at' => (string) ($config['opens_at'] ?? ''),
        'closes_at' => (string) ($config['closes_at'] ?? ''),
        'max_attempts' => max(0, (int) ($config['max_attempts'] ?? 1)),
      ];
    }

    return $configs;
  }

  private function ensureDraftExercise(array $exercise): void
  {
    if ($this->exercises->canEdit($exercise)) {
      return;
    }

    global $session;
    $session->flash('error', 'Este exercício já foi concluído ou publicado e não pode mais ser modificado.');
    View::redirect('/teacher/exercises/' . $exercise['id']);
  }

  private function ensureReadyExercise(array $exercise): void
  {
    if ($this->exercises->canPublish($exercise)) {
      return;
    }

    global $session;
    if (($exercise['admin_review_status'] ?? Exercise::REVIEW_APPROVED) === Exercise::REVIEW_BLOCKED) {
      $message = 'Este exercício foi bloqueado pela administração e não pode ser publicado no momento.';
      if (!empty($exercise['admin_review_note'])) {
        $message .= ' Motivo: ' . trim((string) $exercise['admin_review_note']);
      }

      $session->flash('error', $message);
      View::redirect('/teacher/exercises/' . $exercise['id']);
    }

    $session->flash('error', 'Conclua o cadastro das questões antes de publicar o exercício para as turmas.');
    View::redirect('/teacher/exercises/' . $exercise['id']);
  }

  private function publicationDefaults(): array
  {
    return [
      'opens_at' => date('Y-m-d\TH:i'),
      'closes_at' => date('Y-m-d\TH:i', strtotime('+7 days')),
      'max_attempts' => 1,
    ];
  }
}
