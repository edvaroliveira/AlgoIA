<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Exercise;
use App\Models\Question;
use App\Models\Attempt;
use App\Models\Answer;
use App\Models\Turma;
use App\Services\OpenAIService;
use Core\Auth;
use Core\Database;
use Core\Request;
use Core\View;

class AttemptController
{
  private Exercise $exercises;
  private Question $questions;
  private Attempt  $attempts;
  private Answer   $answers;

  public function __construct()
  {
    $this->exercises = new Exercise();
    $this->questions = new Question();
    $this->attempts  = new Attempt();
    $this->answers   = new Answer();
  }

  /** POST /student/exercises/{id}/start */
  public function start(string $id): void
  {
    Auth::requireStudent();
    Request::validateCsrf();

    $studentId = Auth::id();
    $exercise  = $this->getStudentExercise((int) $id, $studentId);

    if (!$this->exercises->isOpen($exercise)) {
      global $session;
      $session->flash('error', 'Este exercício não está aberto para respostas.');
      View::redirect("/student/exercises/{$id}");
    }

    // Check attempt limit
    $submitted   = $this->attempts->countSubmitted($studentId, (int) $id);
    $maxAttempts = (int) $exercise['max_attempts'];

    if ($maxAttempts > 0 && $submitted >= $maxAttempts) {
      global $session;
      $session->flash('error', 'Você atingiu o número máximo de tentativas.');
      View::redirect("/student/exercises/{$id}");
    }

    // Reuse in-progress attempt or create new
    $inProgress = $this->attempts->getInProgress($studentId, (int) $id);
    $attemptId  = $inProgress ? (int) $inProgress['id'] : $this->attempts->start($studentId, (int) $id);

    View::redirect("/student/exercises/{$id}?attempt={$attemptId}");
  }

  /** POST /student/attempts/{id}/answer — auto-save draft */
  public function saveAnswer(string $id): void
  {
    Auth::requireStudent();
    Request::validateCsrf();

    $studentId = Auth::id();

    if (!$this->attempts->belongsToStudent((int) $id, $studentId)) {
      http_response_code(403);
      exit(json_encode(['ok' => false, 'error' => 'Acesso negado.']));
    }

    $attempt = $this->attempts->find((int) $id);
    if (!$attempt || $attempt['status'] !== 'in_progress') {
      http_response_code(400);
      exit(json_encode(['ok' => false, 'error' => 'Tentativa inválida.']));
    }

    $questionId    = Request::int('question_id');
    $studentAnswer = trim((string) ($_POST['answer'] ?? ''));

    if ($questionId <= 0 || $studentAnswer === '') {
      header('Content-Type: application/json');
      exit(json_encode(['ok' => false]));
    }

    if (!$this->questions->belongsToExercise($questionId, (int) $attempt['exercise_id'])) {
      http_response_code(400);
      header('Content-Type: application/json');
      exit(json_encode(['ok' => false, 'error' => 'Questão inválida para esta tentativa.']));
    }

    $this->answers->saveOrUpdate((int) $id, $questionId, $studentAnswer);
    header('Content-Type: application/json');
    exit(json_encode(['ok' => true]));
  }

  /** POST /student/attempts/{id}/submit */
  public function submit(string $id): void
  {
    Auth::requireStudent();
    Request::validateCsrf();

    $studentId = Auth::id();
    $attempt   = $this->attempts->find((int) $id);

    if (!$attempt || (int) $attempt['student_id'] !== $studentId || $attempt['status'] !== 'in_progress') {
      http_response_code(403);
      exit('Tentativa inválida.');
    }

    $exercise  = $this->exercises->getWithTurma((int) $attempt['exercise_id']);
    $questions = $this->questions->findByExercise((int) $attempt['exercise_id']);

    // Save all answers from POST
    foreach ($questions as $q) {
      $text = trim((string) ($_POST["answer_{$q['id']}"] ?? ''));
      if ($text !== '') {
        $this->answers->saveOrUpdate((int) $id, (int) $q['id'], $text);
      }
    }

    // Evaluate with OpenAI
    $ai         = new OpenAIService();
    $totalScore = 0.0;
    $db         = Database::getInstance();

    try {
      $db->beginTransaction();

      foreach ($questions as $q) {
        $ans = $this->answers->findByAttemptAndQuestion((int) $id, (int) $q['id']);

        if (!$ans || trim($ans['student_answer']) === '') {
          continue;
        }

        $result = $ai->evaluateAnswer(
          $q['text'],
          $q['expected_answer_hint'],
          $ans['student_answer'],
          (float) $q['max_score'],
          (int) $ans['id'],
          $studentId
        );

        $this->answers->updateAiResult(
          (int) $ans['id'],
          $result['score'],
          $result['feedback']
        );

        $totalScore += $result['score'];
      }

      $this->attempts->submit((int) $id, $totalScore);
      $db->commit();
    } catch (\RuntimeException $e) {
      if (method_exists($db, 'rollback')) {
        $db->rollback();
      }

      error_log("OpenAI evaluation failed for attempt {$id}: " . $e->getMessage());

      global $session;
      $session->flash('error', 'A avaliação automática está temporariamente indisponível. Sua tentativa foi preservada para nova submissão.');
      View::redirect("/student/exercises/{$attempt['exercise_id']}?attempt={$id}");
    }

    View::redirect("/student/attempts/{$id}/result");
  }

  /** GET /student/attempts/{id}/result */
  public function result(string $id): void
  {
    Auth::requireStudent();

    $studentId = Auth::id();

    if (!$this->attempts->belongsToStudent((int) $id, $studentId)) {
      http_response_code(403);
      exit('Acesso negado.');
    }

    $attempt    = $this->attempts->getWithExercise((int) $id);
    $answers    = $this->answers->findByAttempt((int) $id);
    $isClosed   = strtotime($attempt['closes_at']) < time();
    $maxScore   = array_sum(array_column($answers, 'max_score'));
    $bestScore  = $this->attempts->getBestScore($studentId, (int) $attempt['exercise_id']);

    View::render('student/results/show', compact(
      'attempt',
      'answers',
      'isClosed',
      'maxScore',
      'bestScore'
    ));
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
}
