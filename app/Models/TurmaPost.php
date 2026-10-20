<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Mural da Turma: recados do Docente para os Alunos. Fixados primeiro, depois
 * os mais novos. Recado expirado some para o Aluno, mas o Docente continua vendo.
 */
class TurmaPost extends Model
{
  public const TITLE_MAX = 150;
  public const BODY_MAX  = 5000;

  protected string $table = 'turma_posts';

  public function create(int $turmaId, int $teacherId, string $title, string $body, bool $pinned, ?string $expiresAt): int
  {
    return $this->db->insert(
      "INSERT INTO turma_posts (turma_id, teacher_id, title, body, pinned, expires_at) VALUES (?, ?, ?, ?, ?, ?)",
      [$turmaId, $teacherId, $title, $body, $pinned ? 1 : 0, $expiresAt]
    );
  }

  /** Todos os recados da Turma (inclusive expirados), para o Docente. */
  public function forTurma(int $turmaId): array
  {
    return $this->db->fetchAll(
      "SELECT id, title, body, pinned, expires_at, created_at,
              (expires_at IS NOT NULL AND expires_at < NOW()) AS expired
             FROM turma_posts
             WHERE turma_id = ?
             ORDER BY pinned DESC, created_at DESC, id DESC",
      [$turmaId]
    );
  }

  /** Recados vigentes das Turmas ativas em que o Aluno tem Matrícula ativa. */
  public function forStudent(int $studentId, ?int $limit = null): array
  {
    $limitSql = $limit !== null ? 'LIMIT ' . max(1, $limit) : '';
    return $this->db->fetchAll(
      "SELECT p.id, p.title, p.body, p.pinned, p.created_at, t.name AS turma_name, t.id AS turma_id
             FROM turma_posts p
             JOIN turmas t ON t.id = p.turma_id AND t.active = 1
             JOIN student_turma st ON st.turma_id = p.turma_id AND st.student_id = ? AND st.status = 'active'
             WHERE p.expires_at IS NULL OR p.expires_at >= NOW()
             ORDER BY p.pinned DESC, p.created_at DESC, p.id DESC
             {$limitSql}",
      [$studentId]
    );
  }

  public function togglePin(int $postId, int $turmaId): bool
  {
    return $this->db->execute(
      "UPDATE turma_posts SET pinned = 1 - pinned WHERE id = ? AND turma_id = ?",
      [$postId, $turmaId]
    ) > 0;
  }

  public function deleteFromTurma(int $postId, int $turmaId): bool
  {
    return $this->db->execute("DELETE FROM turma_posts WHERE id = ? AND turma_id = ?", [$postId, $turmaId]) > 0;
  }
}
