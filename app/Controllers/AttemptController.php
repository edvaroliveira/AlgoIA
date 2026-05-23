<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Exercise;
use App\Models\Question;
use App\Models\Attempt;
use App\Models\Answer;
use App\Services\AttemptGradingService;
use App\Services\AuditService;
use Core\Auth;
use Core\Request;
use Core\View;

class AttemptController
{
  private const PENDING_PER_PAGE = 20;

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
    $publication = $this->exercises->findOpenPublicationForStudent((int) $id, $studentId);

    if (!$publication) {
      global $session;
      $session->flash('error', 'Este exercício não está aberto para respostas.');
      View::redirect("/student/exercises/{$id}");
    }

    $exercise = $this->exercises->applyPublicationContext($exercise, $publication);
    $turmaId = (int) $publication['turma_id'];

    // Check attempt limit
    $submitted   = $this->attempts->countUsedAttempts($studentId, (int) $id, $turmaId);
    $maxAttempts = (int) $exercise['max_attempts'];

    if ($maxAttempts > 0 && $submitted >= $maxAttempts) {
      global $session;
      $session->flash('error', 'Você atingiu o número máximo de tentativas.');
      View::redirect("/student/exercises/{$id}");
    }

    // Reuse in-progress attempt or create new
    $inProgress = $this->attempts->getInProgress($studentId, (int) $id, $turmaId);
    $attemptId  = $inProgress ? (int) $inProgress['id'] : $this->attempts->start($studentId, (int) $id, $turmaId);

    View::redirect("/student/exercises/{$id}?attempt={$attemptId}");
  }

  /** POST /student/attempts/{id}/answer — auto-save draft */
  public function saveAnswer(string $id): void
  {
    Auth::requireStudent();
    Request::validateCsrf();

    $studentId = Auth::id();

    Auth::ensure($this->attempts->belongsToStudent((int) $id, $studentId), 'Acesso negado.', 403, true);

    $attempt = $this->attempts->find((int) $id);
    if (!$attempt || $attempt['status'] !== 'in_progress') {
      http_response_code(400);
      exit(json_encode(['ok' => false, 'error' => 'Tentativa inválida.']));
    }

    $this->ensureAttemptIsOpen($attempt, $studentId, true);

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

    Auth::ensure($attempt && (int) $attempt['student_id'] === $studentId && $attempt['status'] === 'in_progress', 'Tentativa inválida.');

    $exercise  = $this->ensureAttemptIsOpen($attempt, $studentId);
    $questions = $this->questions->findByExercise((int) $attempt['exercise_id']);

    // Save all answers from POST
    foreach ($questions as $q) {
      $text = trim((string) ($_POST["answer_{$q['id']}"] ?? ''));
      $this->answers->saveOrUpdate((int) $id, (int) $q['id'], $text);
    }

    $this->attempts->markSubmitted((int) $id);

    try {
      (new AttemptGradingService())->gradeSubmittedAttempt((int) $id);
    } catch (\Throwable $e) {
      error_log("Attempt evaluation failed for attempt {$id}: " . $e->getMessage());
      AuditService::record('student.attempt.grading_failed', 'attempt', (int) $id, [
        'exercise_id' => (int) ($attempt['exercise_id'] ?? 0),
        'student_id' => (int) ($attempt['student_id'] ?? 0),
        'error' => $e->getMessage(),
      ]);

      global $session;
      $session->flash('error', 'A avaliação automática está temporariamente indisponível. Sua tentativa foi enviada e ficou pendente de correção.');
      View::redirect("/student/exercises/{$attempt['exercise_id']}");
    }

    View::redirect("/student/attempts/{$id}/result");
  }

  public function regradeAdmin(string $id): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $this->regrade((int) $id, 'admin.attempt.regrade', '/admin/dashboard');
  }

  public function pendingAdmin(): void
  {
    Auth::requireAdmin();

    $filters = $this->pendingFiltersFromRequest();
    $pagination = $this->pendingPagination('/admin/attempts/pending', $filters, $this->attempts->countPendingGradingFiltered($filters));
    $attempts = $this->attempts->getPendingGradingForAdminFiltered($filters, $pagination['perPage'], $pagination['offset']);

    View::render('admin/attempts/pending', [
      'attempts' => $attempts,
      'filters' => $filters,
      'pagination' => $pagination,
    ]);
  }

  public function regradeTeacher(string $id): void
  {
    Auth::requireTeacher();
    Request::validateCsrf();

    Auth::ensure($this->attempts->belongsToTeacher((int) $id, (int) Auth::id()));
    $this->regrade((int) $id, 'teacher.attempt.regrade', '/teacher/dashboard');
  }

  public function resultAdmin(string $id): void
  {
    Auth::requireAdmin();
    $this->renderResult((int) $id, true, '/admin/exercises/');
  }

  public function resultTeacher(string $id): void
  {
    Auth::requireTeacher();
    Auth::ensure($this->attempts->belongsToTeacher((int) $id, (int) Auth::id()));
    $this->renderResult((int) $id, true, '/teacher/exercises/');
  }

  public function pendingTeacher(): void
  {
    Auth::requireTeacher();

    $teacherId = (int) Auth::id();
    $filters = $this->pendingFiltersFromRequest();
    $pagination = $this->pendingPagination('/teacher/attempts/pending', $filters, $this->attempts->countPendingGradingFiltered($filters, $teacherId));
    $attempts = $this->attempts->getPendingGradingForTeacherFiltered($teacherId, $filters, $pagination['perPage'], $pagination['offset']);

    View::render('teacher/attempts/pending', [
      'attempts' => $attempts,
      'filters' => $filters,
      'pagination' => $pagination,
    ]);
  }

  /** GET /student/attempts/{id}/result */
  public function result(string $id): void
  {
    Auth::requireStudent();

    $studentId = Auth::id();

    Auth::ensure($this->attempts->belongsToStudent((int) $id, $studentId));

    $this->renderResult((int) $id, false);
  }

  private function renderResult(int $attemptId, bool $internalReview = false, string $internalBasePath = ''): void
  {
    $attempt    = $this->attempts->getWithExercise($attemptId);
    if (!$attempt) {
      Auth::deny('Tentativa não encontrada.', 404);
    }

    if ((string) ($attempt['status'] ?? '') !== 'graded') {
      global $session;
      $message = (string) ($attempt['status'] ?? '') === 'submitted'
        ? 'A tentativa ainda está em correção. O resultado será exibido quando a avaliação for concluída.'
        : 'A tentativa ainda não foi enviada.';
      $session->flash('error', $message);
      View::redirect($internalReview ? $internalBasePath . (int) $attempt['exercise_id'] : "/student/exercises/{$attempt['exercise_id']}");
    }

    $answers    = $this->answers->findByAttempt($attemptId);
    $isClosed   = !empty($attempt['closes_at']) && strtotime($attempt['closes_at']) < time();
    $maxScore   = $this->questions->getTotalMaxScore((int) $attempt['exercise_id']);
    $attemptTurmaId = !empty($attempt['turma_id']) ? (int) $attempt['turma_id'] : null;
    $bestScore  = $this->attempts->getBestScore((int) $attempt['student_id'], (int) $attempt['exercise_id'], $attemptTurmaId);
    $usedTries  = $this->attempts->countUsedAttempts((int) $attempt['student_id'], (int) $attempt['exercise_id'], $attemptTurmaId);
    $maxTries   = (int) ($attempt['max_attempts'] ?? 0);
    $showReferenceAnswer = $internalReview || $isClosed || ($maxTries > 0 && $usedTries >= $maxTries);
    $showDeductionReasons = $internalReview;
    $resultBackUrl = $internalReview ? $internalBasePath . (int) $attempt['exercise_id'] : "/student/exercises/{$attempt['exercise_id']}";
    $resultAnswerLabel = $internalReview ? 'Resposta do aluno:' : 'Sua resposta:';

    View::render('student/results/show', compact(
      'attempt',
      'answers',
      'isClosed',
      'maxScore',
      'bestScore',
      'showReferenceAnswer',
      'showDeductionReasons',
      'resultBackUrl',
      'resultAnswerLabel'
    ));
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

  private function ensureAttemptIsOpen(array $attempt, int $studentId, bool $json = false): array
  {
    $exerciseId = (int) ($attempt['exercise_id'] ?? 0);
    $exercise = $this->getStudentExercise($exerciseId, $studentId);
    $publication = !empty($attempt['turma_id'])
      ? $this->exercises->findPublicationForStudentTurma($exerciseId, $studentId, (int) $attempt['turma_id'])
      : $this->exercises->findOpenPublicationForStudent($exerciseId, $studentId);

    $exercise = $this->exercises->applyPublicationContext($exercise, $publication);

    if ($this->exercises->isOpen($exercise)) {
      return $exercise;
    }

    $message = 'O prazo deste exercício encerrou. Não é mais possível enviar respostas.';

    if ($json) {
      http_response_code(403);
      header('Content-Type: application/json');
      exit(json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE));
    }

    global $session;
    $session->flash('error', $message);
    View::redirect("/student/exercises/{$exerciseId}");
  }

  private function regrade(int $attemptId, string $auditAction, string $fallbackPath): void
  {
    $attempt = $this->attempts->find($attemptId);
    global $session;

    if (!$attempt || (string) ($attempt['status'] ?? '') !== 'submitted') {
      $session->flash('error', 'Tentativa pendente de correção não encontrada.');
      View::redirect($this->safeReturnPath($fallbackPath));
    }

    try {
      $score = (new AttemptGradingService())->gradeSubmittedAttempt($attemptId);
      AuditService::record($auditAction, 'attempt', $attemptId, [
        'exercise_id' => (int) ($attempt['exercise_id'] ?? 0),
        'student_id' => (int) ($attempt['student_id'] ?? 0),
        'score' => $score,
      ]);
      $session->flash('success', 'Tentativa reprocessada com sucesso.');
    } catch (\Throwable $e) {
      error_log("Attempt regrade failed for attempt {$attemptId}: " . $e->getMessage());
      AuditService::record($auditAction . '.failed', 'attempt', $attemptId, [
        'exercise_id' => (int) ($attempt['exercise_id'] ?? 0),
        'student_id' => (int) ($attempt['student_id'] ?? 0),
        'error' => $e->getMessage(),
      ]);
      $session->flash('error', 'Não foi possível reprocessar a tentativa. Ela permanece pendente.');
    }

    View::redirect($this->safeReturnPath($fallbackPath));
  }

  private function safeReturnPath(string $fallbackPath): string
  {
    $returnTo = trim((string) Request::post('return_to', ''));

    if ($returnTo !== '' && str_starts_with($returnTo, '/') && strpos($returnTo, '://') === false) {
      return $returnTo;
    }

    return $fallbackPath;
  }

  private function pendingFiltersFromRequest(): array
  {
    return [
      'search' => trim((string) Request::get('search', '')),
      'from_date' => trim((string) Request::get('from_date', '')),
      'to_date' => trim((string) Request::get('to_date', '')),
      'min_age_hours' => max(0, (int) Request::get('min_age_hours', 0)),
    ];
  }

  private function pendingPagination(string $path, array $filters, int $totalItems): array
  {
    $requestedPage = max(1, (int) Request::get('page', 1));
    $totalPages = max(1, (int) ceil($totalItems / self::PENDING_PER_PAGE));
    $currentPage = min($requestedPage, $totalPages);

    return [
      'path' => $path,
      'query' => $filters,
      'perPage' => self::PENDING_PER_PAGE,
      'totalItems' => $totalItems,
      'totalPages' => $totalPages,
      'currentPage' => $currentPage,
      'offset' => ($currentPage - 1) * self::PENDING_PER_PAGE,
    ];
  }
}
