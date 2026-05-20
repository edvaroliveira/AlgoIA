<?php

declare(strict_types=1);

namespace App\Models;

class Exercise extends Model
{
  protected string $table = 'exercises';

  public function findByTeacher(int $teacherId): array
  {
    return $this->db->fetchAll(
      "SELECT e.*, t.name AS turma_name
             FROM exercises e
             JOIN turmas t ON t.id = e.turma_id
             WHERE e.teacher_id = ?
             ORDER BY e.created_at DESC",
      [$teacherId]
    );
  }

  public function getWithTurma(int $id): array|false
  {
    return $this->db->fetchOne(
      "SELECT e.*, t.name AS turma_name, t.access_key AS turma_key
             FROM exercises e
             JOIN turmas t ON t.id = e.turma_id
             WHERE e.id = ?",
      [$id]
    );
  }

  public function findAvailableForStudent(int $studentId): array
  {
    $now = date('Y-m-d H:i:s');
    return $this->db->fetchAll(
      "SELECT e.*, t.name AS turma_name
             FROM exercises e
             JOIN turmas t ON t.id = e.turma_id
             JOIN student_turma st ON st.turma_id = e.turma_id
             WHERE st.student_id = ? AND st.status = 'active'
               AND e.opens_at <= ? AND e.closes_at >= ?
             ORDER BY e.closes_at ASC",
      [$studentId, $now, $now]
    );
  }

  public function findAllForStudent(int $studentId): array
  {
    return $this->db->fetchAll(
      "SELECT e.*, t.name AS turma_name
             FROM exercises e
             JOIN turmas t ON t.id = e.turma_id
             JOIN student_turma st ON st.turma_id = e.turma_id
             WHERE st.student_id = ? AND st.status = 'active'
             ORDER BY e.closes_at DESC",
      [$studentId]
    );
  }

  public function create(
    int     $teacherId,
    int     $turmaId,
    string  $title,
    ?string $description,
    string  $opensAt,
    string  $closesAt,
    int     $maxAttempts
  ): int {
    return $this->db->insert(
      "INSERT INTO exercises (teacher_id, turma_id, title, description, opens_at, closes_at, max_attempts)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
      [$teacherId, $turmaId, $title, $description, $opensAt, $closesAt, $maxAttempts]
    );
  }

  public function update(
    int     $id,
    string  $title,
    ?string $description,
    string  $opensAt,
    string  $closesAt,
    int     $maxAttempts
  ): void {
    $this->db->execute(
      "UPDATE exercises
             SET title = ?, description = ?, opens_at = ?, closes_at = ?, max_attempts = ?
             WHERE id = ?",
      [$title, $description, $opensAt, $closesAt, $maxAttempts, $id]
    );
  }

  public function belongsToTeacher(int $exerciseId, int $teacherId): bool
  {
    $row = $this->db->fetchOne(
      "SELECT id FROM exercises WHERE id = ? AND teacher_id = ?",
      [$exerciseId, $teacherId]
    );
    return $row !== false;
  }

  public function isOpen(array $exercise): bool
  {
    $now = time();
    return strtotime($exercise['opens_at']) <= $now
      && strtotime($exercise['closes_at']) >= $now;
  }

  public function isClosed(array $exercise): bool
  {
    return strtotime($exercise['closes_at']) < time();
  }

  public function getResultsForTeacher(int $exerciseId): array
  {
    return $this->db->fetchAll(
      "SELECT u.name, u.email,
                    MAX(a.total_score) AS best_score,
                    COUNT(a.id)        AS attempt_count
             FROM users u
             JOIN attempts a ON a.student_id = u.id
             WHERE a.exercise_id = ? AND a.status = 'graded'
             GROUP BY u.id
             ORDER BY best_score DESC",
      [$exerciseId]
    );
  }
}
