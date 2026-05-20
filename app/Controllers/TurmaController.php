<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Turma;
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
    Auth::requireTeacher();
    View::render('teacher/turmas/index', [
      'turmas' => $this->turmas->findByTeacher(Auth::id()),
    ]);
  }

  public function create(): void
  {
    Auth::requireTeacher();
    View::render('teacher/turmas/create');
  }

  public function store(): void
  {
    Auth::requireTeacher();
    Request::validateCsrf();

    $name = Request::str('name');
    if (mb_strlen($name) < 3) {
      View::render('teacher/turmas/create', [
        'error' => 'Nome da turma deve ter pelo menos 3 caracteres.',
        'old'   => ['name' => $name],
      ]);
      return;
    }

    $this->turmas->create(Auth::id(), $name);
    View::redirect('/teacher/turmas');
  }

  public function show(string $id): void
  {
    Auth::requireTeacher();
    $turma = $this->getOwnedTurma((int) $id);

    View::render('teacher/turmas/show', [
      'turma'    => $turma,
      'pending'  => $this->turmas->getPendingStudents((int) $id),
      'students' => $this->turmas->getActiveStudents((int) $id),
    ]);
  }

  public function regenerateKey(string $id): void
  {
    Auth::requireTeacher();
    Request::validateCsrf();
    $this->getOwnedTurma((int) $id);

    $newKey = $this->turmas->regenerateKey((int) $id);

    global $session;
    $session->flash('success', "Nova chave gerada: {$newKey}");
    View::redirect("/teacher/turmas/{$id}");
  }

  public function approveStudent(string $id, string $studentId): void
  {
    Auth::requireTeacher();
    Request::validateCsrf();
    $this->getOwnedTurma((int) $id);

    $this->turmas->approveStudent((int) $studentId, (int) $id);
    View::redirect("/teacher/turmas/{$id}");
  }

  public function rejectStudent(string $id, string $studentId): void
  {
    Auth::requireTeacher();
    Request::validateCsrf();
    $this->getOwnedTurma((int) $id);

    $this->turmas->rejectStudent((int) $studentId, (int) $id);
    View::redirect("/teacher/turmas/{$id}");
  }

  // ── Student: join additional turma ───────────────────────────────────────

  public function join(): void
  {
    Auth::requireStudent();
    Request::validateCsrf();

    $key   = strtoupper(trim(Request::str('turma_key')));
    $turma = $this->turmas->findByKey($key);

    global $session;

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
    if (!$turma || (int) $turma['teacher_id'] !== Auth::id()) {
      http_response_code(403);
      exit('Acesso negado.');
    }
    return $turma;
  }
}
