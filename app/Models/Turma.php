<?php

declare(strict_types=1);

namespace App\Models;

class Turma extends Model
{
  protected string $table = 'turmas';

  public function findByKey(string $key): array|false
  {
    return $this->db->fetchOne(
      "SELECT * FROM turmas WHERE access_key = ? AND active = 1",
      [strtoupper($key)]
    );
  }

  public function findByTeacher(int $teacherId): array
  {
    return $this->db->fetchAll(
      "SELECT t.*,
                    (SELECT COUNT(*) FROM student_turma st WHERE st.turma_id = t.id AND st.status = 'active')  AS active_count,
                    (SELECT COUNT(*) FROM student_turma st WHERE st.turma_id = t.id AND st.status = 'pending') AS pending_count
             FROM turmas t
             WHERE t.teacher_id = ?
             ORDER BY t.name",
      [$teacherId]
    );
  }

  public function create(int $teacherId, string $name): int
  {
    $key = $this->generateUniqueKey();
    return $this->db->insert(
      "INSERT INTO turmas (teacher_id, name, access_key) VALUES (?, ?, ?)",
      [$teacherId, $name, $key]
    );
  }

  public function regenerateKey(int $id): string
  {
    $key = $this->generateUniqueKey();
    $this->db->execute(
      "UPDATE turmas SET access_key = ? WHERE id = ?",
      [$key, $id]
    );
    return $key;
  }

  public function deactivate(int $id): void
  {
    $this->db->execute("UPDATE turmas SET active = 0 WHERE id = ?", [$id]);
  }

  // ── Enrollment ───────────────────────────────────────────────────────────

  public function enrollStudent(int $studentId, int $turmaId): void
  {
    $this->db->execute(
      "INSERT IGNORE INTO student_turma (student_id, turma_id, status) VALUES (?, ?, 'pending')",
      [$studentId, $turmaId]
    );
  }

  public function approveStudent(int $studentId, int $turmaId): void
  {
    $this->db->execute(
      "UPDATE student_turma SET status = 'active' WHERE student_id = ? AND turma_id = ?",
      [$studentId, $turmaId]
    );
    // Activate the user account if still pending
    $this->db->execute(
      "UPDATE users SET status = 'active' WHERE id = ? AND status = 'pending'",
      [$studentId]
    );
  }

  public function rejectStudent(int $studentId, int $turmaId): void
  {
    $this->db->execute(
      "DELETE FROM student_turma WHERE student_id = ? AND turma_id = ?",
      [$studentId, $turmaId]
    );
  }

  public function getPendingStudents(int $turmaId): array
  {
    return $this->db->fetchAll(
      "SELECT u.id, u.name, u.email, st.joined_at
             FROM users u
             JOIN student_turma st ON st.student_id = u.id
             WHERE st.turma_id = ? AND st.status = 'pending'
             ORDER BY st.joined_at",
      [$turmaId]
    );
  }

  public function getActiveStudents(int $turmaId): array
  {
    return $this->db->fetchAll(
      "SELECT u.id, u.name, u.email, st.joined_at
             FROM users u
             JOIN student_turma st ON st.student_id = u.id
             WHERE st.turma_id = ? AND st.status = 'active'
             ORDER BY u.name",
      [$turmaId]
    );
  }

  public function isStudentActive(int $studentId, int $turmaId): bool
  {
    $row = $this->db->fetchOne(
      "SELECT id FROM student_turma WHERE student_id = ? AND turma_id = ? AND status = 'active'",
      [$studentId, $turmaId]
    );
    return $row !== false;
  }

  public function getStudentTurmas(int $studentId): array
  {
    return $this->db->fetchAll(
      "SELECT t.*, st.status AS enrollment_status
             FROM turmas t
             JOIN student_turma st ON st.turma_id = t.id
             WHERE st.student_id = ?
             ORDER BY t.name",
      [$studentId]
    );
  }

  public function belongsToTeacher(int $turmaId, int $teacherId): bool
  {
    $row = $this->db->fetchOne(
      "SELECT id FROM turmas WHERE id = ? AND teacher_id = ?",
      [$turmaId, $teacherId]
    );
    return $row !== false;
  }

  // ── Private helpers ──────────────────────────────────────────────────────

  private function generateUniqueKey(): string
  {
    do {
      $key = strtoupper(bin2hex(random_bytes(3)));  // 6 hex chars
      $exists = $this->db->fetchOne(
        "SELECT id FROM turmas WHERE access_key = ?",
        [$key]
      );
    } while ($exists !== false);

    return $key;
  }
}
