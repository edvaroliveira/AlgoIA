<?php

declare(strict_types=1);

namespace App\Models;

/**
 * @method void reactivate(int $id)
 * @method int countPendingEnrollmentsForAdmin()
 * @method array getPendingTurmasForAdmin(int $limit = 5)
 */
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

  public function getAllForAdmin(array $filters = [], ?int $limit = null, ?int $offset = null): array
  {
    ['where' => $where, 'params' => $params] = $this->buildAdminFilters($filters);
    $limitSql = $limit !== null ? ' LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, (int) $offset) : '';

    return $this->db->fetchAll(
      "SELECT t.*,
                    teacher.name AS teacher_name,
                    (SELECT COUNT(*) FROM student_turma st WHERE st.turma_id = t.id AND st.status = 'active') AS active_count,
                    (SELECT COUNT(*) FROM student_turma st WHERE st.turma_id = t.id AND st.status = 'pending') AS pending_count,
                    (SELECT COUNT(DISTINCT et.exercise_id) FROM exercise_turmas et WHERE et.turma_id = t.id) AS exercise_count
             FROM turmas t
             JOIN users teacher ON teacher.id = t.teacher_id
             {$where}
             ORDER BY teacher.name, t.name{$limitSql}",
      $params
    );
  }

  public function countForAdmin(array $filters = []): int
  {
    ['where' => $where, 'params' => $params] = $this->buildAdminFilters($filters);

    $row = $this->db->fetchOne(
      "SELECT COUNT(*) AS total
             FROM turmas t
             JOIN users teacher ON teacher.id = t.teacher_id
             {$where}",
      $params
    );

    return (int) ($row['total'] ?? 0);
  }

  public function countPendingEnrollmentsForAdmin(): int
  {
    $row = $this->db->fetchOne(
      "SELECT COUNT(*) AS total
             FROM student_turma st
             JOIN turmas t ON t.id = st.turma_id
             WHERE st.status = 'pending'"
    );

    return (int) ($row['total'] ?? 0);
  }

  public function getPendingTurmasForAdmin(int $limit = 5): array
  {
    $safeLimit = max(1, $limit);

    return $this->db->fetchAll(
      "SELECT t.id, t.name, t.access_key, teacher.name AS teacher_name,
                    COUNT(st.student_id) AS pending_count,
                    MIN(st.joined_at) AS oldest_pending_at
             FROM turmas t
             JOIN users teacher ON teacher.id = t.teacher_id
             JOIN student_turma st ON st.turma_id = t.id AND st.status = 'pending'
             GROUP BY t.id
             ORDER BY pending_count DESC, oldest_pending_at ASC, t.name ASC
             LIMIT {$safeLimit}"
    );
  }

  public function findForAdmin(int $id): array|false
  {
    return $this->db->fetchOne(
      "SELECT t.*, teacher.name AS teacher_name, teacher.email AS teacher_email,
                    (SELECT COUNT(*) FROM student_turma st WHERE st.turma_id = t.id AND st.status = 'active') AS active_count,
                    (SELECT COUNT(*) FROM student_turma st WHERE st.turma_id = t.id AND st.status = 'pending') AS pending_count,
                    (SELECT COUNT(DISTINCT et.exercise_id) FROM exercise_turmas et WHERE et.turma_id = t.id) AS exercise_count
             FROM turmas t
             JOIN users teacher ON teacher.id = t.teacher_id
             WHERE t.id = ?
             LIMIT 1",
      [$id]
    );
  }

  public function getExercisePublicationsForAdmin(int $turmaId): array
  {
    return $this->db->fetchAll(
      "SELECT e.id, e.title, e.status, teacher.name AS teacher_name,
                    et.opens_at, et.closes_at, et.max_attempts,
                    COUNT(DISTINCT a.id) AS attempt_count
             FROM exercise_turmas et
             JOIN exercises e ON e.id = et.exercise_id
             JOIN users teacher ON teacher.id = e.teacher_id
             LEFT JOIN attempts a ON a.exercise_id = e.id
             WHERE et.turma_id = ?
             GROUP BY et.exercise_id, et.turma_id
             ORDER BY et.opens_at DESC, e.title",
      [$turmaId]
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

  public function reactivate(int $id): void
  {
    $this->db->execute("UPDATE turmas SET active = 1 WHERE id = ?", [$id]);
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

  private function buildAdminFilters(array $filters): array
  {
    $conditions = [];
    $params = [];

    $status = (string) ($filters['status'] ?? '');
    if ($status === 'active') {
      $conditions[] = 't.active = 1';
    } elseif ($status === 'inactive') {
      $conditions[] = 't.active = 0';
    }

    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
      $conditions[] = '(t.name LIKE ? OR teacher.name LIKE ? OR t.access_key LIKE ?)';
      $params[] = '%' . $search . '%';
      $params[] = '%' . $search . '%';
      $params[] = '%' . strtoupper($search) . '%';
    }

    return [
      'where' => $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '',
      'params' => $params,
    ];
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
