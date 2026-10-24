<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Exercise;
use App\Models\Question;
use App\Services\AuditService;
use App\Services\OpenAIService;
use App\Services\PythonSolutionService;
use Core\Auth;
use Core\Request;
use Core\View;

class QuestionController
{
  // TEXT (MySQL) comporta ~65535 bytes; em utf8mb4 (4 bytes/char no pior
  // caso), 10000 caracteres cabe com folga e ainda é generoso para um
  // enunciado ou gabarito de exercício.
  private const MAX_TEXT_LENGTH = 10000;

  private Question $questions;
  private Exercise $exercises;

  public function __construct()
  {
    $this->questions = new Question();
    $this->exercises = new Exercise();
  }

  public function create(string $exerciseId): void
  {
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
    if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
      $errors[] = 'Enunciado da questão muito longo (máximo ' . self::MAX_TEXT_LENGTH . ' caracteres).';
    }
    if (mb_strlen($hint) < 5) {
      $errors[] = 'Gabarito/conceito esperado muito curto (mínimo 5 caracteres).';
    }
    if (mb_strlen($hint) > self::MAX_TEXT_LENGTH) {
      $errors[] = 'Gabarito/conceito esperado muito longo (máximo ' . self::MAX_TEXT_LENGTH . ' caracteres).';
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

  /**
   * POST /teacher/questions/{id}/python — Docente salva a Resposta em Python
   * (rascunho ou aprovada) ou aprova o rascunho da IA. Vale para Exercício
   * Concluído ou Publicado: só mexe no código, não no enunciado.
   */
  public function savePython(string $id): void
  {
    Request::validateCsrf();
    [$question, $exercise] = $this->ownedQuestionAfterDraft((int) $id);

    global $session;
    $back = '/teacher/exercises/' . $exercise['id'] . '#python-q' . (int) $question['id'];
    $code = Request::text('python_solution');
    $approve = Request::post('action', '') === 'approve';

    if ($code === '') {
      $session->flash('error', 'Escreva o código em Python antes de salvar.');
      View::redirect($back);
    }
    if (mb_strlen($code) > OpenAIService::PYTHON_SOLUTION_MAX_LENGTH) {
      $session->flash('error', 'O código passou de ' . OpenAIService::PYTHON_SOLUTION_MAX_LENGTH . ' caracteres.');
      View::redirect($back);
    }

    $python = new PythonSolutionService();
    $unchanged = OpenAIService::normalizePythonCode($code) === (string) $question['python_solution'];
    if ($approve && $unchanged) {
      $python->approve((int) $id, (int) Auth::id()); // mantém a origem (IA ou Docente)
    } else {
      $python->save((int) $id, $code, $approve, (int) Auth::id());
    }
    AuditService::record($approve ? 'teacher.question.python_approve' : 'teacher.question.python_save', 'question', (int) $id, [
      'exercise_id' => (int) $exercise['id'],
      'edited' => !$unchanged,
    ]);

    $session->flash('success', $approve
      ? 'Resposta em Python aprovada: o Aluno verá junto com a Resposta de referência.'
      : 'Rascunho da Resposta em Python salvo. Ainda não aparece para o Aluno.');
    View::redirect($back);
  }

  /** POST /teacher/questions/{id}/python/regenerate — descarta o código e pede outro à IA (consome tokens). */
  public function regeneratePython(string $id): void
  {
    Request::validateCsrf();
    [$question, $exercise] = $this->ownedQuestionAfterDraft((int) $id);

    (new PythonSolutionService())->requeue((int) $id);
    AuditService::record('teacher.question.python_regenerate', 'question', (int) $id, ['exercise_id' => (int) $exercise['id']]);

    global $session;
    $session->flash('success', 'Pedido enviado: a nova Resposta em Python fica pronta em alguns minutos.');
    View::redirect('/teacher/exercises/' . $exercise['id'] . '#python-q' . (int) $question['id']);
  }

  /** POST /teacher/exercises/{id}/python — põe na fila as Questões ainda sem Resposta em Python (Exercícios anteriores ao recurso). */
  public function generateExercisePython(string $id): void
  {
    Request::validateCsrf();
    $exercise = $this->getOwnedExercise((int) $id);

    global $session;
    if ($this->exercises->canEdit($exercise)) {
      $session->flash('error', 'Conclua o exercício antes: a Resposta em Python é gerada quando as questões não mudam mais.');
      View::redirect('/teacher/exercises/' . $exercise['id']);
    }

    $queued = (new PythonSolutionService())->queueExercise((int) $id);
    AuditService::record('teacher.exercise.python_queue', 'exercise', (int) $id, ['queued' => $queued]);
    $session->flash('success', $queued > 0
      ? "{$queued} questão(ões) na fila. A Resposta em Python fica pronta em alguns minutos para você revisar."
      : 'Todas as questões já têm Resposta em Python.');
    View::redirect('/teacher/exercises/' . $exercise['id'] . '#python');
  }

  /** @return array{0: array, 1: array} Questão e Exercício do Docente, já fora do Rascunho */
  private function ownedQuestionAfterDraft(int $questionId): array
  {
    Auth::ensure($this->questions->belongsToTeacher($questionId, Auth::id()));
    $question = $this->questions->find($questionId);
    $exercise = $this->getOwnedExercise((int) ($question['exercise_id'] ?? 0));

    if ($this->exercises->canEdit($exercise)) {
      global $session;
      $session->flash('error', 'Conclua o exercício antes de cuidar da Resposta em Python.');
      View::redirect('/teacher/exercises/' . $exercise['id']);
    }

    return [$question, $exercise];
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
