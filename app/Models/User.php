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

  public function countActiveAdmins(): int
  {
    $row = $this->db->fetchOne(
      "SELECT COUNT(*) AS total FROM users WHERE role = 'admin' AND status = 'active'"
    );

    return (int) ($row['total'] ?? 0);
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

  public function getAllForAdmin(array $filters = [], ?int $limit = null, ?int $offset = null): array
  {
    ['where' => $where, 'params' => $params] = $this->buildAdminFilters($filters);
    $limitSql = $limit !== null ? ' LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, (int) $offset) : '';

    return $this->db->fetchAll(
      "SELECT u.*,
                    COUNT(DISTINCT CASE WHEN u.role = 'student' THEN st.turma_id END) AS turma_count,
                    GROUP_CONCAT(DISTINCT CASE WHEN u.role = 'student' THEN t.name END ORDER BY t.name SEPARATOR ', ') AS turma_names,
                    COUNT(DISTINCT CASE WHEN u.role = 'teacher' THEN tt.id END) AS owned_turma_count,
                    COUNT(DISTINCT CASE WHEN u.role = 'teacher' THEN e.id END) AS exercise_count
             FROM users u
             LEFT JOIN student_turma st ON st.student_id = u.id
             LEFT JOIN turmas t ON t.id = st.turma_id
             LEFT JOIN turmas tt ON tt.teacher_id = u.id
             LEFT JOIN exercises e ON e.teacher_id = u.id
             {$where}
             GROUP BY u.id
             ORDER BY FIELD(u.role, 'admin', 'teacher', 'student'), u.name{$limitSql}",
      $params
    );
  }

  public function findForAdmin(int $id): array|false
  {
    return $this->db->fetchOne(
      "SELECT u.*,
                    COUNT(DISTINCT CASE WHEN u.role = 'student' THEN st.turma_id END) AS turma_count,
                    GROUP_CONCAT(DISTINCT CASE WHEN u.role = 'student' THEN t.name END ORDER BY t.name SEPARATOR ', ') AS turma_names,
                    COUNT(DISTINCT CASE WHEN u.role = 'teacher' THEN tt.id END) AS owned_turma_count,
                    COUNT(DISTINCT CASE WHEN u.role = 'teacher' THEN e.id END) AS exercise_count,
                    COUNT(DISTINCT CASE WHEN u.role = 'student' THEN a.id END) AS attempt_count,
                    MAX(CASE WHEN u.role = 'student' THEN a.submitted_at END) AS last_attempt_at
             FROM users u
             LEFT JOIN student_turma st ON st.student_id = u.id
             LEFT JOIN turmas t ON t.id = st.turma_id
             LEFT JOIN turmas tt ON tt.teacher_id = u.id
             LEFT JOIN exercises e ON e.teacher_id = u.id
             LEFT JOIN attempts a ON a.student_id = u.id
             WHERE u.id = ?
             GROUP BY u.id
             LIMIT 1",
      [$id]
    );
  }

  public function countForAdmin(array $filters = []): int
  {
    ['where' => $where, 'params' => $params] = $this->buildAdminFilters($filters);

    $row = $this->db->fetchOne(
      "SELECT COUNT(DISTINCT u.id) AS total
             FROM users u
             LEFT JOIN student_turma st ON st.student_id = u.id
             LEFT JOIN turmas t ON t.id = st.turma_id
             LEFT JOIN turmas tt ON tt.teacher_id = u.id
             LEFT JOIN exercises e ON e.teacher_id = u.id
             {$where}",
      $params
    );

    return (int) ($row['total'] ?? 0);
  }

  public function updateAdminManagedProfile(int $id, string $name, string $email, string $role, string $status): void
  {
    $this->db->execute(
      "UPDATE users SET name = ?, email = ?, role = ?, status = ? WHERE id = ?",
      [$name, $email, $role, $status, $id]
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

  public function getTeacherTurmasForAdmin(int $teacherId): array
  {
    return $this->db->fetchAll(
      "SELECT t.*,
                    (SELECT COUNT(*) FROM student_turma st WHERE st.turma_id = t.id AND st.status = 'active') AS active_count,
                    (SELECT COUNT(*) FROM student_turma st WHERE st.turma_id = t.id AND st.status = 'pending') AS pending_count
             FROM turmas t
             WHERE t.teacher_id = ?
             ORDER BY t.name",
      [$teacherId]
    );
  }

  public function getTeacherExercisesForAdmin(int $teacherId): array
  {
    return $this->db->fetchAll(
      "SELECT e.*,
                    COALESCE(NULLIF(GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', '), ''), 'Pendente de finalização') AS turma_label,
                    COUNT(DISTINCT et.turma_id) AS turma_count,
                    COUNT(DISTINCT a.id) AS attempt_count,
                    MIN(et.opens_at) AS opens_at,
                    MAX(et.closes_at) AS closes_at
             FROM exercises e
             LEFT JOIN exercise_turmas et ON et.exercise_id = e.id
             LEFT JOIN turmas t ON t.id = et.turma_id
             LEFT JOIN attempts a ON a.exercise_id = e.id
             WHERE e.teacher_id = ?
             GROUP BY e.id
             ORDER BY e.created_at DESC",
      [$teacherId]
    );
  }

  public function getStudentTurmasForAdmin(int $studentId): array
  {
    return $this->db->fetchAll(
      "SELECT t.*, teacher.name AS teacher_name, st.status AS enrollment_status, st.joined_at
             FROM student_turma st
             JOIN turmas t ON t.id = st.turma_id
             JOIN users teacher ON teacher.id = t.teacher_id
             WHERE st.student_id = ?
             ORDER BY t.name",
      [$studentId]
    );
  }

  public function getStudentAttemptsForAdmin(int $studentId): array
  {
    return $this->db->fetchAll(
      "SELECT a.*, e.title AS exercise_title, teacher.name AS teacher_name
             FROM attempts a
             JOIN exercises e ON e.id = a.exercise_id
             JOIN users teacher ON teacher.id = e.teacher_id
             WHERE a.student_id = ?
             ORDER BY COALESCE(a.submitted_at, a.started_at) DESC",
      [$studentId]
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

  private function buildAdminFilters(array $filters): array
  {
    $conditions = [];
    $params = [];

    $role = (string) ($filters['role'] ?? '');
    if (in_array($role, ['admin', 'teacher', 'student'], true)) {
      $conditions[] = 'u.role = ?';
      $params[] = $role;
    }

    $status = (string) ($filters['status'] ?? '');
    if (in_array($status, ['active', 'pending', 'inactive'], true)) {
      $conditions[] = 'u.status = ?';
      $params[] = $status;
    }

    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
      $conditions[] = '(u.name LIKE ? OR u.email LIKE ?)';
      $params[] = '%' . $search . '%';
      $params[] = '%' . $search . '%';
    }

    return [
      'where' => $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '',
      'params' => $params,
    ];
  }
}
