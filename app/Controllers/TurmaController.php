<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\LoginAttempt;
use App\Models\Turma;
use App\Services\AuditService;
use Core\Auth;
use Core\Request;
use Core\View;

class TurmaController
{
  private Turma $turmas;

  public function __construct()
  {
    $this->turmas = new Turma();
  }

  // ── Teacher: list ────────────────────────────────────────────────────────

  public function index(): void
  {
    View::render('teacher/turmas/index', [
      'turmas' => $this->turmas->findByTeacher(Auth::id()),
    ]);
  }

  public function create(): void
  {
    View::render('teacher/turmas/create');
  }

  public function store(): void
  {
    Request::validateCsrf();

    $name = Request::str('name');
    if (mb_strlen($name) < 3) {
      View::render('teacher/turmas/create', [
        'error' => 'Nome da turma deve ter pelo menos 3 caracteres.',
        'old'   => ['name' => $name],
      ]);
      return;
    }

    $turmaId = $this->turmas->create(Auth::id(), $name);
    AuditService::record('teacher.turma.create', 'turma', $turmaId, ['name' => $name]);
    View::redirect('/teacher/turmas');
  }

  public function show(string $id): void
  {
    $turma = $this->getOwnedTurma((int) $id);

    View::render('teacher/turmas/show', [
      'turma'    => $turma,
      'pending'  => $this->turmas->getPendingStudents((int) $id),
      'students' => $this->turmas->getActiveStudents((int) $id),
    ]);
  }

  public function regenerateKey(string $id): void
  {
    Request::validateCsrf();
    $this->ensureActiveTurma($this->getOwnedTurma((int) $id), 'teacher.turma.regenerate_key');

    $newKey = $this->turmas->regenerateKey((int) $id);
    AuditService::record('teacher.turma.regenerate_key', 'turma', (int) $id);

    global $session;
    $session->flash('success', "Nova chave gerada: {$newKey}");
    View::redirect("/teacher/turmas/{$id}");
  }

  public function approveStudent(string $id, string $studentId): void
  {
    Request::validateCsrf();
    $this->ensureActiveTurma($this->getOwnedTurma((int) $id), 'teacher.student.approve');

    global $session;

    if (!$this->turmas->approveStudent((int) $studentId, (int) $id)) {
      AuditService::record('teacher.student.approve_denied', 'student', (int) $studentId, ['turma_id' => (int) $id]);
      $session->flash('error', 'Aluno pendente não encontrado para esta turma.');
      View::redirect("/teacher/turmas/{$id}");
    }

    AuditService::record('teacher.student.approve', 'student', (int) $studentId, ['turma_id' => (int) $id]);
    $session->flash('success', 'Aluno aprovado com sucesso.');
    View::redirect("/teacher/turmas/{$id}");
  }

  public function rejectStudent(string $id, string $studentId): void
  {
    Request::validateCsrf();
    $this->ensureActiveTurma($this->getOwnedTurma((int) $id), 'teacher.student.reject');

    $this->turmas->rejectStudent((int) $studentId, (int) $id);
    AuditService::record('teacher.student.reject', 'student', (int) $studentId, ['turma_id' => (int) $id]);
    View::redirect("/teacher/turmas/{$id}");
  }

  // ── Student: join additional turma ───────────────────────────────────────

  public function join(): void
  {
    Request::validateCsrf();

    global $session;

    // Chave de turma tem 6 caracteres: sem limite, um aluno logado consegue
    // varrer o espaço de chaves e se inscrever em turmas que não são dele.
    if ($this->isJoinThrottled()) {
      $session->flash('error', 'Muitas tentativas de entrada em turma. Aguarde alguns minutos e tente novamente.');
      View::redirect('/student/dashboard');
    }
    $this->recordJoinAttempt();

    $key   = strtoupper(trim(Request::str('turma_key')));
    $turma = $this->turmas->findByKey($key);

    if (!$turma) {
      $session->flash('error', 'Chave de turma inválida ou inativa.');
    } else {
      $this->turmas->enrollStudent(Auth::id(), (int) $turma['id']);
      $session->flash('success', 'Solicitação enviada para a turma "' . $turma['name'] . '". Aguarde aprovação.');
    }

    View::redirect('/student/dashboard');
  }

  // ── Private helpers ──────────────────────────────────────────────────────

  private function getOwnedTurma(int $id): array
  {
    $turma = $this->turmas->find($id);
    Auth::ensure($turma && (int) $turma['teacher_id'] === Auth::id());
    return $turma;
  }

  private function ensureActiveTurma(array $turma, string $action): void
  {
    if ((bool) ($turma['active'] ?? false)) {
      return;
    }

    AuditService::record($action . '.blocked', 'turma', (int) $turma['id'], [
      'reason' => 'turma_inactive',
    ]);

    global $session;
    $session->flash('error', 'Esta turma está inativa e não aceita novas alterações.');
    View::redirect('/teacher/turmas/' . (int) $turma['id']);
  }

  private function isJoinThrottled(): bool
  {
    try {
      return (new LoginAttempt())->isActionRateLimited('turma_join', $this->clientIp());
    } catch (\Throwable $e) {
      error_log('Turma join throttle unavailable: ' . $e->getMessage());
      return false;
    }
  }

  private function recordJoinAttempt(): void
  {
    try {
      (new LoginAttempt())->recordAction(
        'turma_join',
        $this->clientIp(),
        (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
      );
    } catch (\Throwable $e) {
      error_log('Turma join throttle record unavailable: ' . $e->getMessage());
    }
  }

  private function clientIp(): string
  {
    return mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
  }
}
