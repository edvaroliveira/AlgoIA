<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;

class User extends Model
{
  protected string $table = 'users';

  public function findByEmail(string $email): array|false
  {
    return $this->db->fetchOne(
      "SELECT * FROM users WHERE email = ?",
      [$email]
    );
  }

  public function create(
    string $name,
    string $email,
    string $password,
    string $role   = 'student',
    string $status = 'pending'
  ): int {
    return $this->db->insert(
      "INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)",
      [$name, $email, password_hash($password, PASSWORD_BCRYPT), $role, $status]
    );
  }

  public function verifyPassword(string $password, string $hash): bool
  {
    return password_verify($password, $hash);
  }

  public function updateStatus(int $id, string $status): void
  {
    $this->db->execute(
      "UPDATE users SET status = ? WHERE id = ?",
      [$status, $id]
    );
  }

  public function updatePassword(int $id, string $newPassword): void
  {
    $this->db->execute(
      "UPDATE users SET password_hash = ? WHERE id = ?",
      [password_hash($newPassword, PASSWORD_BCRYPT), $id]
    );
  }

  public function updateProfile(int $id, string $name, string $email): void
  {
    $this->db->execute(
      "UPDATE users SET name = ?, email = ? WHERE id = ?",
      [$name, $email, $id]
    );
  }

  public function getAllStudents(): array
  {
    return $this->db->fetchAll(
      "SELECT * FROM users WHERE role = 'student' ORDER BY name"
    );
  }

  public function getStudentsByTeacher(int $teacherId): array
  {
    return $this->db->fetchAll(
      "SELECT u.*,
                    GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', ') AS turma_names,
                    COUNT(DISTINCT st.turma_id) AS turma_count
             FROM users u
             JOIN student_turma st ON st.student_id = u.id
             JOIN turmas t ON t.id = st.turma_id
             WHERE u.role = 'student' AND t.teacher_id = ?
             GROUP BY u.id
             ORDER BY u.name",
      [$teacherId]
    );
  }

  public function getRecentStudentsByTeacher(int $teacherId, int $limit = 5): array
  {
    return $this->db->fetchAll(
      "SELECT u.*,
                    GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', ') AS turma_names
             FROM users u
             JOIN student_turma st ON st.student_id = u.id
             JOIN turmas t ON t.id = st.turma_id
             WHERE u.role = 'student' AND t.teacher_id = ?
             GROUP BY u.id
             ORDER BY u.created_at DESC
             LIMIT {$limit}",
      [$teacherId]
    );
  }

  public function belongsToTeacher(int $studentId, int $teacherId): bool
  {
    $row = $this->db->fetchOne(
      "SELECT u.id
             FROM users u
             JOIN student_turma st ON st.student_id = u.id
             JOIN turmas t ON t.id = st.turma_id
             WHERE u.id = ? AND u.role = 'student' AND t.teacher_id = ?
             LIMIT 1",
      [$studentId, $teacherId]
    );

    return $row !== false;
  }

  public function deleteStudentWithRelations(int $studentId, int $teacherId): bool
  {
    if (!$this->belongsToTeacher($studentId, $teacherId)) {
      return false;
    }

    $db = Database::getInstance();

    try {
      $db->beginTransaction();
      $db->execute('DELETE FROM injection_logs WHERE student_id = ?', [$studentId]);
      $db->execute('DELETE FROM attempts WHERE student_id = ?', [$studentId]);
      $db->execute('DELETE FROM student_turma WHERE student_id = ?', [$studentId]);
      $deleted = $db->execute("DELETE FROM users WHERE id = ? AND role = 'student'", [$studentId]);
      $db->commit();

      return $deleted > 0;
    } catch (\Throwable $e) {
      $db->rollback();
      throw $e;
    }
  }

  public function emailExistsForOther(string $email, int $excludeId): bool
  {
    $row = $this->db->fetchOne(
      "SELECT id FROM users WHERE email = ? AND id != ?",
      [$email, $excludeId]
    );
    return $row !== false;
  }
}
